<?php
// app/models/Log.php
require_once __DIR__ . '/../core/database.php';

class Log {
    
    // Función para escribir en la bitácora
    public static function registrar($modulo, $accion, $detalles = '') {
        try {
            $db = Database::conectar();
            
            // Obtenemos quién hizo la acción y desde qué IP
            $id_usuario = $_SESSION['usuario_id'] ?? 1; // 1 por defecto si es sistema
            $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
            
            $stmt = $db->prepare("INSERT INTO logs_auditoria (id_usuario, modulo, accion, detalles, ip_origen) 
                                  VALUES (:id_usuario, :modulo, :accion, :detalles, :ip_origen)");
            
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':modulo', $modulo);
            $stmt->bindParam(':accion', $accion);
            $stmt->bindParam(':detalles', $detalles);
            $stmt->bindParam(':ip_origen', $ip_origen);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            // Un log fallido no debería detener el sistema entero, pero se puede registrar en error_log de PHP
            error_log("Error al guardar log de auditoría: " . $e->getMessage());
            return false;
        }
    }

    // Función para leer la bitácora (Solo Administradores)
    public static function obtenerTodos() {
        try {
            $db = Database::conectar();
            // Traemos el log y hacemos JOIN para saber el nombre de usuario y su rol
            $sql = "SELECT l.*, u.usuario, u.nombre_completo, r.nombre_rol 
                    FROM logs_auditoria l 
                    JOIN usuarios u ON l.id_usuario = u.id 
                    JOIN roles r ON u.id_rol = r.id
                    ORDER BY l.fecha DESC 
                    LIMIT 500"; // Límite de seguridad para no colapsar la memoria
            $stmt = $db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            die("Error al obtener logs: " . $e->getMessage());
        }
    }

    // Función para leer los bloqueos del Firewall (WAF)
    public static function obtenerAlertasWAF() {
        try {
            $db = Database::conectar();
            $stmt = $db->query("SELECT * FROM logs_waf ORDER BY fecha DESC LIMIT 500");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            die("Error al obtener alertas WAF: " . $e->getMessage());
        }
    }
}