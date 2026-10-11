<?php
// app/models/usuario.php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validar.php';

class Usuario {
    
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
            error_log("Error al obtener usuarios: " . $e->getMessage()); die("Error interno. Contacte al administrador.");
        }
    }

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
            error_log("Error al registrar usuario: " . $e->getMessage()); die("Error interno. Contacte al administrador.");
        }
    }

    public static function suspender($id) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("UPDATE usuarios SET activo = 0 WHERE id = :id");
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al suspender usuario: " . $e->getMessage()); die("Error interno. Contacte al administrador.");
        }
    }

    public static function obtenerRoles() {
        try {
            $db = Database::conectar();
            $stmt = $db->query("SELECT * FROM roles ORDER BY id ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error al obtener roles: " . $e->getMessage()); die("Error interno. Contacte al administrador.");
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
            error_log("Error al actualizar la contraseña: " . $e->getMessage()); die("Error interno. Contacte al administrador.");
        }
    }

    public static function inicializarAdminDefecto() {
        try {
            $db = Database::conectar();

            // Solo se crea el administrador inicial si no existe ninguno (rol = 1)
            $existeAdmin = $db->query("SELECT COUNT(*) FROM usuarios WHERE id_rol = 1")->fetchColumn();
            if ($existeAdmin != 0) {
                return;
            }

            // Sin valores por defecto: las credenciales iniciales deben venir del entorno (.env).
            $usuario  = getenv('INIT_ADMIN_USER');
            $password = getenv('INIT_ADMIN_PASS');
            $correo   = getenv('INIT_ADMIN_EMAIL') ?: 'admin@netdocs.local';

            if (!$usuario || !$password) {
                error_log('NetDocs: no hay administrador y faltan INIT_ADMIN_USER / INIT_ADMIN_PASS; no se crea ninguno.');
                return;
            }
            if (!Validar::usuario($usuario) || Auth::validarPassword($password) !== null) {
                error_log('NetDocs: INIT_ADMIN_USER o INIT_ADMIN_PASS no cumplen la política (usuario 3-50 caracteres, contraseña mínimo ' . Auth::PASSWORD_MIN . '); no se crea el administrador.');
                return;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtInsert = $db->prepare("INSERT INTO usuarios (id, usuario, nombre_completo, correo, password_hash, id_rol, activo)
                                        VALUES (1, :usuario, :nombre, :correo, :hash, 1, 1)");
            $stmtInsert->execute([
                ':usuario' => $usuario,
                ':nombre'  => 'Super Administrador Sistema',
                ':correo'  => $correo,
                ':hash'    => $hash,
            ]);
        } catch (PDOException $e) {
            error_log("Aviso: No se pudo inicializar el admin. Detalles: " . $e->getMessage());
        }
    }
}
