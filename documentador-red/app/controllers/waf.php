<?php
// app/core/WAF.php
require_once __DIR__ . '/../core/database.php'; // Ajusta la ruta a tu database.php
class WAF {
    
    public static function proteger() {
        $uri = $_SERVER['REQUEST_URI'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
        
        // 1. DICCIONARIO DE FIRMAS MALICIOSAS
        // Aquí definimos los patrones que los atacantes usan para buscar vulnerabilidades
        $patrones_sospechosos = [
            'Directory Traversal' => ['../', '..\\', '/etc/passwd', 'c:\windows'],
            'XSS (Cross-Site Scripting)' => ['<script>', 'javascript:', 'onerror=', 'onload='],
            'Inyección SQL' => ['UNION SELECT', 'OR 1=1', 'DROP TABLE', '--', 'WAITFOR DELAY']
        ];

        // 2. REVISAR LA URL (URI)
        foreach ($patrones_sospechosos as $amenaza => $patrones) {
            foreach ($patrones as $patron) {
                if (stripos($uri, $patron) !== false) {
                    self::registrarAlerta($ip, $uri, $amenaza, "Detectado en URL: " . $patron, $user_agent);
                    self::bloquearAtaque();
                }
            }
        }

        // 3. REVISAR LOS FORMULARIOS ($_POST y $_GET)
        $datos_entrantes = array_merge($_POST, $_GET);
        foreach ($datos_entrantes as $campo => $valor) {
            if (is_array($valor)) continue; // Evitar errores con arrays múltiples
            
            foreach ($patrones_sospechosos as $amenaza => $patrones) {
                foreach ($patrones as $patron) {
                    if (stripos($valor, $patron) !== false) {
                        self::registrarAlerta($ip, $uri, $amenaza, "Detectado en input '{$campo}': " . $patron, $user_agent);
                        self::bloquearAtaque();
                    }
                }
            }
        }
    }

    private static function registrarAlerta($ip, $uri, $amenaza, $payload, $user_agent) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("INSERT INTO logs_waf (ip_origen, uri_solicitada, tipo_amenaza, payload_detectado, user_agent) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ip, $uri, $amenaza, $payload, substr($user_agent, 0, 250)]);
        } catch (PDOException $e) {
            error_log("Fallo crítico en el WAF: " . $e->getMessage());
        }
    }

    private static function bloquearAtaque() {
        // Le devolvemos un error genérico 403 (Prohibido) al atacante para no darle pistas
        http_response_code(403);
        die("<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style='background:#000; color:#ff1744; text-align:center; padding-top:50px; font-family:sans-serif;'><h1>403 Forbidden</h1><p>El firewall de la aplicación ha bloqueado esta solicitud por motivos de seguridad.</p></body></html>");
    }
}