<?php
// C:\xampp\htdocs\documentador-red\app\controllers\dashboardcontroller.php

require_once __DIR__ . '/../models/equipo.php';

class DashboardController {
    
    public static function index() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: /documentador-red/");
            exit();
        }

        // 1. Obtenemos todos los equipos
        $equipos_db = Equipo::obtenerTodos();
        
        // Variables para nuestras métricas
        $total_equipos = count($equipos_db);
        $puertos_up = 0;
        $puertos_down = 0;
        
        // Agruparemos los equipos por ubicación (Rack) y les inyectaremos sus puertos
        $infraestructura = [];

        foreach ($equipos_db as $eq) {
            $rack = !empty($eq['ubicacion']) ? $eq['ubicacion'] : 'Sin Ubicación';
            
            // Obtenemos los puertos reales de este equipo
            $puertos = Equipo::obtenerPuertos($eq['id']);
            $eq['puertos'] = $puertos;
            
            // Contabilizamos el estado para las métricas globales
            foreach ($puertos as $p) {
                if ($p['estado'] === 'up') $puertos_up++;
                else $puertos_down++;
            }

            // Lo guardamos en nuestro arreglo agrupado
            $infraestructura[$rack][] = $eq;
        }

        // 3. Cargamos la vista pasando toda esta información procesada
        require_once __DIR__ . '/../views/infraestructura/panel.php';
    }
}