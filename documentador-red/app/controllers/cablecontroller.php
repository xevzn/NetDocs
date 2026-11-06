<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validar.php';
// C:\xampp\htdocs\documentador-red\app\controllers\cablecontroller.php

require_once __DIR__ . '/../models/cable.php';

class CableController {
    
    // Muestra la lista de todos los patch cords / conexiones
    public static function index() {
        Auth::start();
        if (!isset($_SESSION['usuario_id'])) { header("Location: /documentador-red/"); exit(); }

        $conexiones = Cable::obtenerConexiones();
        require_once __DIR__ . '/../views/infraestructura/cableado.php';
    }

    // Muestra la etiqueta tipo bandera lista para imprimir
    public static function etiqueta() {
        Auth::start();
        if (!isset($_SESSION['usuario_id'])) { header("Location: /documentador-red/"); exit(); }

        $id = Validar::entero($_GET['id'] ?? null);
        if ($id === null) { http_response_code(400); exit("Error: No se especificó la conexión."); }
        $cable = Cable::obtenerConexionPorId($id);

        if (!$cable) { die("Error: La conexión no existe o está vacía."); }

        require_once __DIR__ . '/../views/infraestructura/etiqueta_cable.php';
    }
}