<?php
require_once __DIR__ . '/../models/busqueda.php';

class BusquedaController {
    public static function buscar() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) { header("Location: /documentador-red/"); exit(); }
        
        $termino = isset($_GET['q']) ? trim($_GET['q']) : '';
        $resultados = Busqueda::global($termino);
        
        require_once __DIR__ . '/../views/infraestructura/resultados.php';
    }
}