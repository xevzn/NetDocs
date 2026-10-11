<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validar.php';
// app/controllers/LogController.php
require_once __DIR__ . '/../models/log.php';

class LogController {
    
    public static function index() {
        Auth::require(Auth::ADMIN);

        // Extraemos ambas tablas
        $logs = Log::obtenerTodos();
        $alertas_waf = Log::obtenerAlertasWAF(); // NUEVO: Traemos los datos del WAF
        
        require_once __DIR__ . '/../views/auditoria/logs.php';
    }
}