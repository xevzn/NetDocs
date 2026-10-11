<?php
// app/core/auth.php
// Punto único de autenticación, autorización por rol, sesiones endurecidas,
// protección CSRF y límite de intentos de login.

require_once __DIR__ . '/database.php';

class Auth
{
    public const BASE_URL = '/documentador-red';

    public const ROL_ADMIN   = 1;
    public const ROL_TECNICO = 2;
    public const ROL_LECTOR  = 3;

    public const TODOS = [1, 2, 3];
    public const STAFF = [1, 2];   // Administrador y Técnico
    public const ADMIN = [1];

    public const IDLE_TIMEOUT        = 7200; // segundos sin actividad antes de cerrar sesión
    public const VENTANA_SEGUNDOS    = 900;  // ventana para contar intentos fallidos
    public const MAX_FALLOS_USUARIO  = 5;    // fallos por (usuario + IP) dentro de la ventana
    public const MAX_FALLOS_IP       = 30;   // fallos por IP dentro de la ventana
    public const PASSWORD_MIN        = 12;

    // ------------------------------------------------------------------
    // SESIÓN
    // ------------------------------------------------------------------
    public static function isHttps(): bool
    {
        $forzado = getenv('SESSION_COOKIE_SECURE');
        if ($forzado !== false && $forzado !== '') {
            return in_array(strtolower($forzado), ['1', 'true', 'yes', 'on'], true);
        }
        return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    }

    /** Inicia la sesión una sola vez, con cookies endurecidas. Reemplaza a session_start(). */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Llamar justo después de validar usuario y contraseña. */
    public static function iniciarSesionUsuario(array $usuario): void
    {
        self::start();
        session_regenerate_id(true); // evita fijación de sesión
        $_SESSION = [];
        $_SESSION['usuario_id']      = (int)$usuario['id'];
        $_SESSION['rol_id']          = (int)$usuario['id_rol'];
        $_SESSION['nombre_usuario']  = (string)$usuario['usuario'];
        $_SESSION['last_activity']   = time();
    }

    public static function destruirSesion(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // ------------------------------------------------------------------
    // AUTORIZACIÓN
    // ------------------------------------------------------------------
    /**
     * Exige sesión válida, usuario activo y rol dentro de la lista blanca.
     * El rol y el estado "activo" se leen de la BD en cada petición, de modo que
     * suspender una cuenta o cambiar su rol surte efecto de inmediato.
     */
    public static function require(array $roles, bool $api = false): void
    {
        self::start();

        if (empty($_SESSION['usuario_id'])) {
            self::denegar(401, $api, true);
        }

        if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > self::IDLE_TIMEOUT) {
            self::destruirSesion();
            self::denegar(401, $api, true);
        }

        $fila = self::cargarUsuario((int)$_SESSION['usuario_id']);
        $rol  = $fila ? (int)($fila['id_rol'] ?? 0) : 0;
        $rolesValidos = [self::ROL_ADMIN, self::ROL_TECNICO, self::ROL_LECTOR];

        if (!$fila || (int)$fila['activo'] !== 1 || !in_array($rol, $rolesValidos, true)) {
            self::destruirSesion();
            self::denegar(401, $api, true);
        }

        $_SESSION['rol_id']        = $rol;
        $_SESSION['last_activity'] = time();

        if (!in_array($rol, $roles, true)) {
            require_once __DIR__ . '/../models/log.php';
            Log::registrar('Seguridad', 'Intento de Acceso Denegado',
                'Acceso sin permisos a ' . self::rutaActual() . ' (rol ' . $rol . ').');
            self::denegar(403, $api, false);
        }
    }

    private static function cargarUsuario(int $id): ?array
    {
        try {
            $stmt = Database::conectar()->prepare('SELECT id, id_rol, activo FROM usuarios WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            return $fila ?: null;
        } catch (PDOException $e) {
            error_log('Auth::cargarUsuario: ' . $e->getMessage());
            http_response_code(503);
            exit('Servicio no disponible.');
        }
    }

    public static function rutaActual(): string
    {
        $uri = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return substr($uri, 0, 120);
    }

    /** Solo se permite volver a rutas internas simples de la propia aplicación. */
    public static function rutaSegura(string $uri): bool
    {
        return preg_match('#^/documentador-red/[A-Za-z0-9_\-/]*(\?[A-Za-z0-9_\-=&%.]*)?$#', $uri) === 1;
    }

    private static function denegar(int $codigo, bool $api, bool $sinSesion): void
    {
        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($api) {
            http_response_code($codigo);
            header('Content-Type: application/json');
            echo json_encode(['error' => $codigo === 401 ? 'No autorizado' : 'Prohibido']);
            exit;
        }

        if ($metodo === 'GET') {
            if ($sinSesion) {
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                if (session_status() === PHP_SESSION_ACTIVE && self::rutaSegura($uri)) {
                    $_SESSION['redirect_to'] = $uri;
                }
                header('Location: ' . self::BASE_URL . '/');
            } else {
                header('Location: ' . self::BASE_URL . '/dashboard');
            }
            exit;
        }

        http_response_code($codigo);
        exit('Acceso denegado.');
    }

    // ------------------------------------------------------------------
    // CSRF
    // ------------------------------------------------------------------
    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Verifica el token en toda petición POST (campo de formulario o cabecera X-CSRF-Token). */
    public static function verificarCsrf(): void
    {
        self::start();
        $esperado = $_SESSION['csrf_token'] ?? '';
        $recibido = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!is_string($recibido) || $esperado === '' || !hash_equals($esperado, $recibido)) {
            http_response_code(403);
            exit('Solicitud no válida o sesión expirada. Recargue la página e inténtelo de nuevo.');
        }
    }

    // ------------------------------------------------------------------
    // LÍMITE DE INTENTOS DE LOGIN
    // ------------------------------------------------------------------
    private static function asegurarTablaIntentos(PDO $db): void
    {
        static $hecho = false;
        if ($hecho) {
            return;
        }
        $db->exec("CREATE TABLE IF NOT EXISTS login_intentos (
            id INT NOT NULL AUTO_INCREMENT,
            usuario VARCHAR(50) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            exito TINYINT(1) NOT NULL DEFAULT 0,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_usuario_ip (usuario, ip, fecha),
            KEY idx_ip (ip, fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $hecho = true;
    }

    public static function loginBloqueado(string $usuario, string $ip): bool
    {
        try {
            $db = Database::conectar();
            self::asegurarTablaIntentos($db);
            $usuario = mb_substr($usuario, 0, 50);

            $stmt = $db->prepare("SELECT COUNT(*) FROM login_intentos
                WHERE usuario = :u AND ip = :ip AND exito = 0
                AND fecha >= DATE_SUB(NOW(), INTERVAL " . self::VENTANA_SEGUNDOS . " SECOND)");
            $stmt->execute([':u' => $usuario, ':ip' => $ip]);
            if ((int)$stmt->fetchColumn() >= self::MAX_FALLOS_USUARIO) {
                return true;
            }

            $stmt = $db->prepare("SELECT COUNT(*) FROM login_intentos
                WHERE ip = :ip AND exito = 0
                AND fecha >= DATE_SUB(NOW(), INTERVAL " . self::VENTANA_SEGUNDOS . " SECOND)");
            $stmt->execute([':ip' => $ip]);
            return (int)$stmt->fetchColumn() >= self::MAX_FALLOS_IP;
        } catch (PDOException $e) {
            error_log('Auth::loginBloqueado: ' . $e->getMessage());
            return false;
        }
    }

    public static function registrarIntentoLogin(string $usuario, string $ip, bool $exito): void
    {
        try {
            $db = Database::conectar();
            self::asegurarTablaIntentos($db);
            $usuario = mb_substr($usuario, 0, 50);

            if ($exito) {
                $db->prepare('DELETE FROM login_intentos WHERE usuario = :u AND ip = :ip AND exito = 0')
                   ->execute([':u' => $usuario, ':ip' => $ip]);
                return;
            }

            $db->prepare('INSERT INTO login_intentos (usuario, ip, exito) VALUES (:u, :ip, 0)')
               ->execute([':u' => $usuario, ':ip' => $ip]);

            if (mt_rand(1, 50) === 1) { // limpieza ocasional
                $db->exec('DELETE FROM login_intentos WHERE fecha < DATE_SUB(NOW(), INTERVAL 1 DAY)');
            }
        } catch (PDOException $e) {
            error_log('Auth::registrarIntentoLogin: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // CONTRASEÑAS
    // ------------------------------------------------------------------
    /** Devuelve un mensaje de error, o null si la contraseña cumple la política. */
    public static function validarPassword(string $password): ?string
    {
        if (mb_strlen($password) < self::PASSWORD_MIN) {
            return 'La contraseña debe tener al menos ' . self::PASSWORD_MIN . ' caracteres.';
        }
        if (strlen($password) > 72) {
            return 'La contraseña no puede superar 72 caracteres.';
        }
        return null;
    }
}
