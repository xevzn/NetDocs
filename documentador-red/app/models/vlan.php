<?php
// C:\xampp\htdocs\documentador-red\app\models\Vlan.php

require_once __DIR__ . '/../../config/database.php';

class Vlan {
    
    public static function obtenerTodas() {
        try {
            $db = Database::conectar();
            $stmt = $db->query("SELECT * FROM vlans ORDER BY numero_vlan ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            die("Error al obtener VLANs: " . $e->getMessage());
        }
    }

    public static function registrar($numero, $nombre, $subred, $gateway, $descripcion) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("INSERT INTO vlans (numero_vlan, nombre_vlan, subred, gateway, descripcion) 
                                  VALUES (:numero, :nombre, :subred, :gateway, :descripcion)");
            
            $stmt->bindParam(':numero', $numero);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':subred', $subred);
            $stmt->bindParam(':gateway', $gateway);
            $stmt->bindParam(':descripcion', $descripcion);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { return false; } // VLAN duplicada
            die("Error al registrar VLAN: " . $e->getMessage());
        }
    }

    public static function eliminar($id) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("DELETE FROM vlans WHERE id = :id");
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error al eliminar la vlan: " . $e->getMessage());
        }
    }

    // Obtener una VLAN específica por su ID
    public static function obtenerPorId($id) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("SELECT * FROM vlans WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            die("Error al obtener VLAN: " . $e->getMessage());
        }
    }

    // Obtener SOLO las IPs que están en uso dentro de esa VLAN
    public static function obtenerIpsOcupadas($id_vlan) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("SELECT * FROM direcciones_ip WHERE id_vlan = :id");
            $stmt->bindParam(':id', $id_vlan);
            $stmt->execute();
            $resultados = $stmt->fetchAll();
            
            // Convertimos los resultados en un arreglo donde la "Llave" es la dirección IP
            // Esto hará que PHP encuentre las IPs ocupadas instantáneamente
            $ips_indexadas = [];
            foreach ($resultados as $r) {
                $ips_indexadas[$r['direccion_ip']] = $r;
            }
            return $ips_indexadas;
            
        } catch (PDOException $e) {
            die("Error al obtener IPs: " . $e->getMessage());
        }
    }
    // Registrar o actualizar el estado de una IP

    // Registrar o actualizar el estado de una IP
    public static function registrarIp($id_vlan, $ip, $estado, $dispositivo, $mac) {
        try {
            $db = Database::conectar();
            $sql = "INSERT INTO direcciones_ip (id_vlan, direccion_ip, estado, dispositivo, mac_address) 
                    VALUES (:id_vlan, :ip, :estado, :dispositivo, :mac)
                    ON DUPLICATE KEY UPDATE estado = :estado_upd, dispositivo = :dispositivo_upd, mac_address = :mac_upd";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id_vlan', $id_vlan);
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':dispositivo', $dispositivo);
            $stmt->bindParam(':mac', $mac);
            // Parámetros para actualización
            $stmt->bindParam(':estado_upd', $estado);
            $stmt->bindParam(':dispositivo_upd', $dispositivo);
            $stmt->bindParam(':mac_upd', $mac);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error al registrar la IP: " . $e->getMessage());
        }
    }

    // Liberar una IP (Eliminarla de la base de datos para que vuelva a estar libre)
    public static function liberarIp($id_vlan, $ip) {
        try {
            $db = Database::conectar();
            $stmt = $db->prepare("DELETE FROM direcciones_ip WHERE id_vlan = :id_vlan AND direccion_ip = :ip");
            $stmt->bindParam(':id_vlan', $id_vlan);
            $stmt->bindParam(':ip', $ip);
            return $stmt->execute();
        } catch (PDOException $e) {
            die("Error al liberar la IP: " . $e->getMessage());
        }
    }
}