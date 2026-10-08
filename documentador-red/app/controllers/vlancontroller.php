<?php
// C:\xampp\htdocs\documentador-red\app\controllers\vlancontroller.php

require_once __DIR__ . '/../models/vlan.php';
require_once __DIR__ . '/../models/log.php';

class VlanController {
    
    public static function index() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: /documentador-red/");
            exit();
        }

        $vlans = Vlan::obtenerTodas();
        require_once __DIR__ . '/../views/infraestructura/vlans.php';
    }

    public static function guardar() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) { header("Location: /documentador-red/"); exit(); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $numero = intval($_POST['numero_vlan']);
            $nombre = trim($_POST['nombre_vlan']);
            $subred = trim($_POST['subred']);
            $gateway = trim($_POST['gateway']);
            $descripcion = trim($_POST['descripcion']);

            $exito = Vlan::registrar($numero, $nombre, $subred, $gateway, $descripcion);

            if ($exito) {
                require_once __DIR__ . '/../models/log.php';
                Log::registrar('VLANs', 'Crear VLAN', "VLAN {$_POST['numero_vlan']} ({$_POST['nombre_vlan']}) agregada. Subred: {$_POST['subred']}, GW: {$_POST['gateway']}.");
                header("Location: /documentador-red/vlans?mensaje=exito");
            } else {
                header("Location: /documentador-red/vlans?mensaje=error");
            }
            exit();
        }
    }

    // Función auxiliar para calcular detalles de una subred (CIDR)
    public static function calcularCIDR($cidr) {
        $partes = explode('/', $cidr);
        if (count($partes) != 2) return null;
        
        $ip = $partes[0];
        $mascara = intval($partes[1]);
        
        // Calculamos el total de IPs (2 elevado a la (32 - mascara))
        $total_ips = pow(2, (32 - $mascara));
        $ips_usables = $total_ips - 2; // Descontamos Red y Broadcast
        
        return [
            'mascara_bits' => $mascara,
            'total_usables' => $ips_usables,
            'rango_inicio' => long2ip(ip2long($ip) + 1),
            'rango_fin' => long2ip(ip2long($ip) + $ips_usables)
        ];
    }

    // Muestra la cuadrícula visual de IPs
    public static function mapa() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) { header("Location: /documentador-red/"); exit(); }

        if (!isset($_GET['id'])) { die("Error: Red no especificada."); }
        
        $id_vlan = $_GET['id'];
        $vlan = Vlan::obtenerPorId($id_vlan);
        if (!$vlan) { die("Error: La VLAN no existe."); }

        $calculo = self::calcularCIDR($vlan['subred']);
        
        // Seguro contra redes gigantes (Ej. un /8 o /16)
        // Bloqueamos el renderizado visual si la red tiene más de 2048 IPs (un /21)
        if ($calculo['total_usables'] > 2048) {
            die("<div style='background:#1e1e2f; color:white; padding:40px; text-align:center; font-family:sans-serif;'>
                    <h2>Red Demasiado Grande</h2>
                    <p>La red <b>" . $vlan['subred'] . "</b> tiene " . $calculo['total_usables'] . " IPs utilizables.</p>
                    <p>Dibujar un mapa tan grande colapsaría el navegador. Usa prefijos /22 o menores para el mapa visual.</p>
                    <a href='/documentador-red/vlans' style='color:#00d2ff;'>Volver</a>
                 </div>");
        }

        $ips_ocupadas = Vlan::obtenerIpsOcupadas($id_vlan);

        require_once __DIR__ . '/../views/infraestructura/mapa_ips.php';
    }

    // Procesa el formulario emergente del mapa de IPs
    public static function guardarIp() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) { header("Location: /documentador-red/"); exit(); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_vlan = $_POST['id_vlan'];
            $ip = trim($_POST['direccion_ip']);
            
            // Verificamos qué botón se presionó
            $accion = isset($_POST['accion']) ? $_POST['accion'] : 'guardar';

            if ($accion === 'liberar') {
                // Si presionó el botón rojo, borramos el registro
                Vlan::liberarIp($id_vlan, $ip);
            } else {
                // Si presionó guardar, registramos o actualizamos
                $estado = $_POST['estado'];
                $dispositivo = trim($_POST['dispositivo']);
                $mac = trim($_POST['mac_address']);
                Vlan::registrarIp($id_vlan, $ip, $estado, $dispositivo, $mac);
            }
            
            // Recargamos el mapa
            header("Location: /documentador-red/mapa-ips?id=" . $id_vlan);
            exit();
        }
    }

    public static function eliminarVlan() {
    session_start();
    if (!isset($_SESSION['usuario_id'])) { header("Location: /documentador-red/"); exit(); }

    // --- BLOQUEO DE SEGURIDAD CRÍTICO ---
    if ($_SESSION['rol_id'] != 1) {
        header("Location: /documentador-red/vlans");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_vlan'])) {
        // CORRECCIÓN: Llamamos al modelo Vlan, no al Equipo
        Vlan::eliminar($_POST['id_vlan']); 
        
        require_once __DIR__ . '/../models/log.php';
        Log::registrar('VLANs', 'Eliminar VLAN', "Se eliminó de la base de datos la VLAN con ID interno: {$_POST['id_vlan']}.");

        header("Location: /documentador-red/vlans?mensaje=eliminado"); 
        exit();
    }
}
}
