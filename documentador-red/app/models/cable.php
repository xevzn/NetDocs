<?php
// C:\xampp\htdocs\documentador-red\app\models\Cable.php

require_once __DIR__ . '/../core/database.php';

class Cable {
    
    // Obtiene todos los cables documentados (puertos con destino)
    public static function obtenerConexiones() {
        try {
            $db = Database::conectar();
            // NUEVO: Agregamos e.ubicacion al SELECT y al ORDER BY
            $sql = "SELECT p.id, e.hostname, e.ubicacion, p.nombre_puerto, p.destino 
                    FROM puertos p 
                    JOIN equipos e ON p.id_equipo = e.id 
                    WHERE p.destino IS NOT NULL AND p.destino != ''
                    ORDER BY e.ubicacion ASC, e.hostname ASC, p.id ASC";
            $stmt = $db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            die("Error al obtener conexiones: " . $e->getMessage());
        }
    }

    // Obtiene una sola conexión para imprimir su etiqueta
    public static function obtenerConexionPorId($id_puerto) {
        try {
            $db = Database::conectar();
            // CORRECCIÓN: Agregamos id_equipo, vlan y direccion_ip a la consulta
            $sql = "SELECT p.id, p.id_equipo, e.hostname, p.nombre_puerto, p.destino, p.vlan, p.direccion_ip 
                    FROM puertos p 
                    JOIN equipos e ON p.id_equipo = e.id 
                    WHERE p.id = :id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id_puerto);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            die("Error al obtener el cable: " . $e->getMessage());
        }
    }
}