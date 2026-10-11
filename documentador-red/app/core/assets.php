<?php
// app/core/assets.php
// Librerías JS de terceros: se sirven desde public/js/vendor/ si existen (recomendado);
// si no, se usa la CDN con versión fija como respaldo y el CSP permite solo esas CDN.

class Assets
{
    /** Archivo => URL de respaldo (versión fija). Ejecutar scripts/vendor-js.sh para alojarlos localmente. */
    public const LIBS = [
        'chart.umd.min.js'     => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
        'cytoscape.min.js'     => 'https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.27.0/cytoscape.min.js',
        'qrcode-generator.js'  => 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js',
    ];

    private static function ruta(string $archivo): string
    {
        return __DIR__ . '/../../public/js/vendor/' . $archivo;
    }

    public static function local(string $archivo): bool
    {
        return is_file(self::ruta($archivo));
    }

    /** true si todas las librerías están alojadas localmente (el CSP entonces no necesita CDN). */
    public static function todoLocal(): bool
    {
        foreach (array_keys(self::LIBS) as $a) {
            if (!self::local($a)) {
                return false;
            }
        }
        return true;
    }

    public static function script(string $archivo): string
    {
        if (!isset(self::LIBS[$archivo])) {
            return '';
        }
        $src = self::local($archivo)
            ? Auth::BASE_URL . '/js/vendor/' . rawurlencode($archivo)
            : self::LIBS[$archivo];
        return '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    /** URL base pública para los QR. Definir APP_BASE_URL (p. ej. http://192.168.1.10) en el .env. */
    public static function urlBase(): string
    {
        $env = getenv('APP_BASE_URL');
        if (is_string($env) && preg_match('#^https?://[A-Za-z0-9.\-]+(:\d{1,5})?$#', rtrim($env, '/'))) {
            return rtrim($env, '/') . Auth::BASE_URL;
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
            $host = 'localhost';
        }
        return (Auth::isHttps() ? 'https://' : 'http://') . $host . Auth::BASE_URL;
    }
}
