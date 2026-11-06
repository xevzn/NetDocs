<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validar.php';
require_once __DIR__ . '/../models/busqueda.php';

class BusquedaController {
    public static function buscar() {
        Auth::start();
        if (!isset($_SESSION['usuario_id'])) { header("Location: /documentador-red/"); exit(); }
        
        $termino = Validar::texto(Validar::get('q'), 100);
        $resultados = Busqueda::global($termino);
        
        require_once __DIR__ . '/../views/infraestructura/resultados.php';
    }
}