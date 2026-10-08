<?php
// C:\xampp\htdocs\documentador-red\app\models\Equipo.php

require_once __DIR__ . '/../core/database.php';

class Equipo {

    // 1. Obtener todos los equipos (Para listarlos en una tabla)
    public static function obtenerTodos() {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("SELECT * FROM equipos ORDER BY id DESC");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            die("Error al obtener equipos: " . $e->getMessage());
        }
    }

    // 2. Registrar un nuevo equipo en la base de datos (¡Con Plantillas y Vault!)
    public static function registrar($hostname, $marca, $modelo, $tipo, $plantilla_conexion, $ip_gestion, $ubicacion, $comentarios, $ssh_user = null, $ssh_password_encrypted = null) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("INSERT INTO equipos (hostname, marca, modelo, tipo, plantilla_conexion, ip_gestion, ubicacion, comentarios, ssh_user, ssh_password_encrypted) 
                                  VALUES (:hostname, :marca, :modelo, :tipo, :plantilla, :ip, :ubicacion, :comentarios, :ssh_user, :ssh_password_encrypted)");
            
            $stmt->bindParam(':hostname', $hostname);
            $stmt->bindParam(':marca', $marca);
            $stmt->bindParam(':modelo', $modelo);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':plantilla', $plantilla_conexion); // <- Enlace de la nueva variable
            $stmt->bindParam(':ip', $ip_gestion);
            $stmt->bindParam(':ubicacion', $ubicacion);
            $stmt->bindParam(':comentarios', $comentarios);
            $stmt->bindParam(':ssh_user', $ssh_user);
            $stmt->bindParam(':ssh_password_encrypted', $ssh_password_encrypted);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return false; 
            }
            die("Error al registrar equipo: " . $e->getMessage());
        }
    }

    // 3. Obtener los puertos de un equipo específico (ej. los puertos de un Catalyst o un ISR 4221)
    public static function obtenerPuertos($id_equipo) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("SELECT * FROM puertos WHERE id_equipo = :id_equipo ORDER BY id ASC");
            $stmt->bindParam(':id_equipo', $id_equipo);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            die("Error al obtener puertos: " . $e->getMessage());
        }
    }


    // 4. Obtener un solo equipo por su ID (Para generar su etiqueta QR)
    public static function obtenerPorId($id) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("SELECT * FROM equipos WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            die("Error al obtener el equipo: " . $e->getMessage());
        }
    }

    // 5. Registrar un nuevo puerto para un equipo
    public static function registrarPuerto($id_equipo, $nombre_puerto, $estado, $modo_puerto, $vlan, $destino) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("INSERT INTO puertos (id_equipo, nombre_puerto, estado, modo_puerto, vlan, destino) 
                                    VALUES (:id_equipo, :nombre, :estado, :modo_puerto, :vlan, :destino)");
            $stmt->bindParam(':id_equipo', $id_equipo);
            $stmt->bindParam(':nombre', $nombre_puerto);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':modo_puerto', $modo_puerto); // <-- Faltaba este parámetro
            $stmt->bindParam(':vlan', $vlan);
            $stmt->bindParam(':destino', $destino);
            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error al registrar puerto: " . $e->getMessage());
        }
    }

// 6. Actualizar un puerto existente
    public static function actualizarPuerto($id_puerto, $nombre_puerto, $estado, $modo_puerto, $vlan, $direccion_ip, $destino) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("UPDATE puertos SET nombre_puerto = :nombre, estado = :estado, modo_puerto = :modo_puerto, vlan = :vlan, direccion_ip = :direccion_ip, destino = :destino WHERE id = :id");
            $stmt->bindParam(':id', $id_puerto);
            $stmt->bindParam(':nombre', $nombre_puerto);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':modo_puerto', $modo_puerto);
            $stmt->bindParam(':vlan', $vlan);
            $stmt->bindParam(':direccion_ip', $direccion_ip); // <-- Se guarda la nueva IP
            $stmt->bindParam(':destino', $destino);
            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error al actualizar puerto: " . $e->getMessage());
        }
    }

    // 7. Eliminar un equipo por su ID
    public static function eliminar($id) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("DELETE FROM equipos WHERE id = :id");
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error al eliminar el equipo: " . $e->getMessage());
        }
    }

    // 8. Actualizar los datos completos de un equipo
    public static function actualizar($id, $hostname, $marca, $modelo, $tipo, $plantilla_conexion, $ip_gestion, $ubicacion, $comentarios, $ssh_user, $ssh_password_encrypted) {
        try {
            $db = Database::conectar();
            
            // Si hay contraseña nueva, actualizamos esa columna también
            if ($ssh_password_encrypted !== null) {
                $sql = "UPDATE equipos SET 
                        hostname = :hostname, marca = :marca, modelo = :modelo, tipo = :tipo, 
                        plantilla_conexion = :plantilla, ip_gestion = :ip, ubicacion = :ubicacion, 
                        comentarios = :comentarios, ssh_user = :ssh_user, ssh_password_encrypted = :ssh_password_encrypted 
                        WHERE id = :id";
            } else {
                // Si la contraseña viene nula, NO tocamos la columna ssh_password_encrypted
                $sql = "UPDATE equipos SET 
                        hostname = :hostname, marca = :marca, modelo = :modelo, tipo = :tipo, 
                        plantilla_conexion = :plantilla, ip_gestion = :ip, ubicacion = :ubicacion, 
                        comentarios = :comentarios, ssh_user = :ssh_user 
                        WHERE id = :id";
            }

            $stmt = $db->prepare($sql);
            
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':hostname', $hostname);
            $stmt->bindParam(':marca', $marca);
            $stmt->bindParam(':modelo', $modelo);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':plantilla', $plantilla_conexion);
            $stmt->bindParam(':ip', $ip_gestion);
            $stmt->bindParam(':ubicacion', $ubicacion);
            $stmt->bindParam(':comentarios', $comentarios);
            $stmt->bindParam(':ssh_user', $ssh_user);
            
            if ($ssh_password_encrypted !== null) {
                $stmt->bindParam(':ssh_password_encrypted', $ssh_password_encrypted);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error al actualizar el equipo: " . $e->getMessage());
        }
    }
}