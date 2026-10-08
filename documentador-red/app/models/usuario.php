<?php
// app/models/usuario.php
require_once __DIR__ . '/../core/database.php';

class Usuario {
    
    // Obtiene solo los usuarios activos (activo = 1)
    public static function obtenerTodos() {
        try {
            $db = Database::conectar();
            // NUEVO: Agregamos nombre_completo, correo, ultimo_acceso y filtramos por activo = 1
            $sql = "SELECT u.id, u.usuario, u.nombre_completo, u.correo, u.creado_en, u.ultimo_acceso, r.nombre_rol 
                    FROM usuarios u 
                    JOIN roles r ON u.id_rol = r.id 
                    WHERE u.activo = 1
                    ORDER BY u.id ASC";
            $stmt = $db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            die("Error al obtener usuarios: " . $e->getMessage());
        }
    }

    // Registro con los nuevos campos de identidad
    public static function registrar($usuario, $nombre_completo, $correo, $password, $id_rol) {
        try {
            $db = Database::conectar();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO usuarios (usuario, nombre_completo, correo, password_hash, id_rol) 
                                  VALUES (:usuario, :nombre, :correo, :hash, :rol)");
            $stmt->bindParam(':usuario', $usuario);
            $stmt->bindParam(':nombre', $nombre_completo);
            $stmt->bindParam(':correo', $correo);
            $stmt->bindParam(':hash', $hash);
            $stmt->bindParam(':rol', $id_rol);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { return false; } 
            die("Error al registrar usuario: " . $e->getMessage());
        }
    }

    // SOFT DELETE: Cambiamos a inactivo en lugar de borrar permanentemente
    public static function suspender($id) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("UPDATE usuarios SET activo = 0 WHERE id = :id");
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error al suspender usuario: " . $e->getMessage());
        }
    }

    public static function obtenerRoles() {
        try {
            $db = Database::conectar();
            $stmt = $db->query("SELECT * FROM roles ORDER BY id ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            die("Error al obtener roles: " . $e->getMessage());
        }
    }

    public static function actualizarPassword($id, $nueva_password) {
        try {
            $db = Database::conectar();
            $hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE usuarios SET password_hash = :hash WHERE id = :id");
            $stmt->bindParam(':hash', $hash);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error al actualizar la contraseña: " . $e->getMessage());
        }
    }
}