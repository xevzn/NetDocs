<?php
// app/controllers/authcontroller.php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validar.php';
require_once __DIR__ . '/../models/log.php';

class AuthController {

    // Hash bcrypt de relleno: se verifica cuando el usuario no existe para que el tiempo
    // de respuesta no revele si la cuenta existe.
    private const HASH_RELLENO = '$2y$10$vbwvpr4oNDJLgY/JTLWZA.g2h6mHfpE1LGg0stKhvfMMI1re0.0si';

    // --- PROCESADOR DE LOGIN ---
    public static function procesarLogin() {
        Auth::start();

        $usuarioIngresado  = Validar::post('usuario');
        $passwordIngresado = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($usuarioIngresado === '' || $passwordIngresado === '') {
            header('Location: ' . Auth::BASE_URL . '/?error=credenciales');
            exit();
        }

        if (Auth::loginBloqueado($usuarioIngresado, $ip)) {
            header('Location: ' . Auth::BASE_URL . '/?error=bloqueado');
            exit();
        }

        try {
            $db = Database::conectar();

            // Solo cuentas activas pueden iniciar sesión.
            $stmt = $db->prepare("SELECT id, usuario, password_hash, id_rol FROM usuarios WHERE usuario = :usuario AND activo = 1");
            $stmt->execute([':usuario' => $usuarioIngresado]);
            $usuarioDb = $stmt->fetch();

            $hash = $usuarioDb ? $usuarioDb['password_hash'] : self::HASH_RELLENO;
            $passwordOk = password_verify($passwordIngresado, $hash);

            if ($usuarioDb && $passwordOk && in_array((int)$usuarioDb['id_rol'], [1, 2, 3], true)) {
                Auth::registrarIntentoLogin($usuarioIngresado, $ip, true);
                Auth::iniciarSesionUsuario($usuarioDb);

                if (password_needs_rehash($usuarioDb['password_hash'], PASSWORD_DEFAULT)) {
                    $db->prepare("UPDATE usuarios SET password_hash = :h WHERE id = :id")
                       ->execute([':h' => password_hash($passwordIngresado, PASSWORD_DEFAULT), ':id' => $usuarioDb['id']]);
                }
                $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id")
                   ->execute([':id' => $usuarioDb['id']]);

                Log::registrar('Autenticación', 'Inicio de Sesión', "El usuario accedió al sistema desde la IP de gestión.");

                // Solo se vuelve a rutas internas de la propia aplicación (evita redirecciones abiertas).
                $destino = $_SESSION['redirect_to'] ?? null;
                unset($_SESSION['redirect_to']);
                if (is_string($destino) && Auth::rutaSegura($destino)) {
                    header('Location: ' . $destino);
                } else {
                    header('Location: ' . Auth::BASE_URL . '/dashboard');
                }
                exit();
            }

            Auth::registrarIntentoLogin($usuarioIngresado, $ip, false);
            header('Location: ' . Auth::BASE_URL . '/?error=credenciales');
            exit();

        } catch (PDOException $e) {
            error_log('Login: ' . $e->getMessage());
            http_response_code(503);
            exit('Servicio no disponible. Contacte al administrador.');
        }
    }

    // --- LOGOUT (solo POST con token CSRF; lo exige el enrutador) ---
    public static function logout() {
        Auth::start();

        if (isset($_SESSION['usuario_id'])) {
            Log::registrar('Autenticación', 'Cierre de Sesión', "El usuario cerró sesión voluntariamente.");
        }

        Auth::destruirSesion();

        header('Location: ' . Auth::BASE_URL . '/');
        exit();
    }
}
