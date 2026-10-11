<?php
// app/controllers/waf.php
// Capa adicional de detección (NO sustituye a la validación de entrada ni al escape de salida).
// Registra y bloquea firmas obvias; las defensas reales están en Auth, Validar y el escape en las vistas.
require_once __DIR__ . '/../core/database.php';

class WAF {

    // Campos con credenciales: no se inspeccionan ni se guardan en logs_waf.
    private const CAMPOS_SENSIBLES = ['password', 'nueva_password', 'ssh_pass', 'csrf_token', 'csrf_vlan_token'];

    private const PATRONES = [
        'Directory Traversal'        => ['../', '..\\', '/etc/passwd', 'c:\windows'],
        'XSS (Cross-Site Scripting)' => ['<script', '</script', 'javascript:', 'onerror=', 'onload=', 'onfocus=', 'onmouseover='],
        'Inyección SQL'              => ['UNION SELECT', 'OR 1=1', 'DROP TABLE', 'WAITFOR DELAY'],
    ];

    public static function proteger() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

        // 1. URL (también decodificada, para detectar %3Cscript%3E, %2e%2e/, etc.)
        self::inspeccionar($uri, 'URL', $ip, $uri, $user_agent);
        self::inspeccionar(rawurldecode($uri), 'URL', $ip, $uri, $user_agent);

        // 2. Formularios ($_POST y $_GET), incluyendo arreglos anidados
        self::recorrer(array_merge($_POST, $_GET), '', $ip, $uri, $user_agent);
    }

    private static function recorrer($datos, $prefijo, $ip, $uri, $user_agent) {
        foreach ($datos as $campo => $valor) {
            if (in_array((string)$campo, self::CAMPOS_SENSIBLES, true)) {
                continue;
            }
            $nombre = $prefijo . $campo;
            if (is_array($valor)) {
                self::recorrer($valor, $nombre . '.', $ip, $uri, $user_agent);
            } elseif (is_string($valor)) {
                self::inspeccionar($valor, "input '{$nombre}'", $ip, $uri, $user_agent);
            }
        }
    }

    private static function inspeccionar($texto, $origen, $ip, $uri, $user_agent) {
        foreach (self::PATRONES as $amenaza => $patrones) {
            foreach ($patrones as $patron) {
                if (stripos($texto, $patron) !== false) {
                    self::registrarAlerta($ip, $uri, $amenaza, "Detectado en {$origen}: " . $patron, $user_agent);
                    self::bloquearAtaque();
                }
            }
        }
    }

    private static function registrarAlerta($ip, $uri, $amenaza, $payload, $user_agent) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("INSERT INTO logs_waf (ip_origen, uri_solicitada, tipo_amenaza, payload_detectado, user_agent)
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                substr($ip, 0, 45), substr($uri, 0, 255), substr($amenaza, 0, 50),
                substr($payload, 0, 500), substr($user_agent, 0, 250),
            ]);
        } catch (PDOException $e) {
            error_log("Fallo en el WAF: " . $e->getMessage());
        }
    }

    private static function bloquearAtaque() {
        http_response_code(403);
        die("<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style='background:#000; color:#ff1744; text-align:center; padding-top:50px; font-family:sans-serif;'><h1>403 Forbidden</h1><p>El firewall de la aplicación ha bloqueado esta solicitud por motivos de seguridad.</p></body></html>");
    }
}
