<?php
// public/index.php  — enrutador único.
// Toda petición dinámica pasa por aquí: sesión → CSRF → autenticación/rol → controlador.

// --- 1. ERRORES: nunca se muestran al usuario (solo al log). APP_DEBUG=1 para desarrollo local. ---
$debug = getenv('APP_DEBUG') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
ini_set('expose_php', '0');

set_exception_handler(function (Throwable $e) {
    error_log('Excepción no controlada: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'Error interno del servidor.';
});

require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/validar.php';
require_once __DIR__ . '/../app/controllers/waf.php';

// --- 2. CABECERAS DE SEGURIDAD ---
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
require_once __DIR__ . '/../app/core/assets.php';
$cdn = Assets::todoLocal() ? '' : ' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com';
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'" . $cdn . "; "
     . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
     . "img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
header('Cache-Control: no-store');

// --- 2b. WAF (capa adicional de detección) ---
WAF::proteger();

// --- 3. RUTA SOLICITADA ---
$ruta = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (strpos($ruta, Auth::BASE_URL) === 0) {
    $ruta = substr($ruta, strlen(Auth::BASE_URL));
}
$ruta = '/' . trim($ruta, '/');
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- 4. ARCHIVOS ESTÁTICOS (solo dentro de public/ y con extensión permitida) ---
$tipos = [
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
    'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
    'css' => 'text/css', 'js' => 'application/javascript',
];
$real = realpath(__DIR__ . $ruta);
$raizPublica = realpath(__DIR__);
if ($real !== false && $raizPublica !== false && is_file($real)
    && strpos($real, $raizPublica . DIRECTORY_SEPARATOR) === 0
    && basename($real) !== 'index.php') {
    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    if (isset($tipos[$ext])) {
        header('Content-Type: ' . $tipos[$ext]);
        header('Cache-Control: public, max-age=3600');
        readfile($real);
        exit;
    }
}

// --- 5. SESIÓN Y CSRF (toda petición POST, incluido el login) ---
Auth::start();
if ($metodo === 'POST') {
    Auth::verificarCsrf();
}

// --- 6. DESPACHO POR TABLA DE RUTAS ---
$rutas = require __DIR__ . '/../app/core/routes.php';

if (isset($rutas[$ruta])) {
    $def = $rutas[$ruta];
    $permitidos = (array)$def['m'];

    if (!in_array($metodo, $permitidos, true)) {
        http_response_code(405);
        header('Allow: ' . implode(', ', $permitidos));
        exit('Método no permitido.');
    }

    if ($def['r'] !== null) {
        Auth::require($def['r'], !empty($def['api']));
    }

    if (isset($def['v'])) {
        require __DIR__ . '/../app/views/' . $def['v'];
        exit;
    }

    [$archivo, $clase, $accion] = $def['c'];
    require_once __DIR__ . '/../app/controllers/' . $archivo . '.php';
    $clase::$accion();
    exit;
}

// --- 7. RUTA POR DEFECTO: pantalla de login (o dashboard si ya hay sesión) ---
if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . Auth::BASE_URL . '/dashboard');
    exit;
}

try {
    Database::conectar();
    require_once __DIR__ . '/../app/models/usuario.php';
    Usuario::inicializarAdminDefecto();
    require_once __DIR__ . '/../app/views/auth/login.php';
} catch (Throwable $e) {
    error_log('Login: ' . $e->getMessage());
    http_response_code(503);
    echo 'Servicio no disponible. Contacte al administrador.';
}
