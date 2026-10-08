<?php
// app/controllers/LogController.php
require_once __DIR__ . '/../models/log.php';

class LogController {
    
    public static function index() {
        session_start();
        
        // Ciberseguridad: Si no es admin (1), lo expulsamos de inmediato
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) { 
            if(isset($_SESSION['usuario_id'])) {
                Log::registrar('Seguridad', 'Intento de Acceso Denegado', 'El usuario intentó acceder al Centro de Seguridad sin permisos.');
            }
            header("Location: /documentador-red/dashboard"); 
            exit(); 
        }

        // Extraemos ambas tablas
        $logs = Log::obtenerTodos();
        $alertas_waf = Log::obtenerAlertasWAF(); // NUEVO: Traemos los datos del WAF
        
        require_once __DIR__ . '/../views/auditoria/logs.php';
    }
}