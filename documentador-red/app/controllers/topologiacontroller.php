<?php
// app/controllers/TopologiaController.php
require_once __DIR__ . '/../models/topologia.php';
class TopologiaController {
    
    public static function index() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: /documentador-red/dashboard");
            exit();
        }

        // Pedimos los datos procesados al modelo
        $datosMapa = Topologia::obtenerElementosMapa();
        
        $json_elements = $datosMapa['json_elements'];
        $nodos_unicos = $datosMapa['nodos_unicos'];

        // Cargamos la vista
        require_once __DIR__ . '/../views/automatizacion/mapa_de_red.php';
    }
}