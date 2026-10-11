<?php
// C:\xampp\htdocs\documentador-red\app\controllers\vlancontroller.php

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validar.php';
require_once __DIR__ . '/../models/vlan.php';
require_once __DIR__ . '/../models/log.php';

class VlanController {
    
    public static function index() {
        Auth::start();

        $vlans = Vlan::obtenerTodas();
        require_once __DIR__ . '/../views/infraestructura/vlans.php';
    }

    public static function guardar() {
        Auth::require(Auth::STAFF);

        $numero      = Validar::vlan(Validar::post('numero_vlan'));
        $nombre      = Validar::texto(Validar::post('nombre_vlan'), 50);
        $subred      = Validar::post('subred');
        $gateway     = Validar::post('gateway');
        $descripcion = Validar::texto(Validar::post('descripcion'), 255);

        if ($numero === null || $nombre === '' || !Validar::cidr($subred) || !Validar::ipv4($gateway)) {
            header("Location: /documentador-red/vlans?mensaje=error");
            exit();
        }

        $exito = Vlan::registrar($numero, $nombre, $subred, $gateway, $descripcion);

        if ($exito) {
            Log::registrar('VLANs', 'Crear VLAN', "VLAN {$numero} ({$nombre}) agregada. Subred: {$subred}, GW: {$gateway}.");
            header("Location: /documentador-red/vlans?mensaje=exito");
        } else {
            header("Location: /documentador-red/vlans?mensaje=error");
        }
        exit();
    }

    // Función auxiliar para calcular detalles de una subred (CIDR)
    public static function calcularCIDR($cidr) {
        if (!Validar::cidr((string)$cidr)) return null;
        $partes = explode('/', $cidr);
        
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
        Auth::start();

        $id_vlan = Validar::entero($_GET['id'] ?? null);
        if ($id_vlan === null) { http_response_code(400); exit("Error: Red no especificada."); }

        $vlan = Vlan::obtenerPorId($id_vlan);
        if (!$vlan) { http_response_code(404); exit("Error: La VLAN no existe."); }

        $calculo = self::calcularCIDR($vlan['subred']);
        if ($calculo === null) {
            http_response_code(422);
            exit("Error: la subred registrada para esta VLAN no es válida.");
        }

        // Seguro contra redes gigantes (Ej. un /8 o /16): no se dibuja si hay más de 2048 IPs
        if ($calculo['total_usables'] > 2048) {
            http_response_code(413);
            exit("<div style='background:#1e1e2f; color:white; padding:40px; text-align:center; font-family:sans-serif;'>
                    <h2>Red Demasiado Grande</h2>
                    <p>La red <b>" . Validar::h($vlan['subred']) . "</b> tiene " . (int)$calculo['total_usables'] . " IPs utilizables.</p>
                    <p>Dibujar un mapa tan grande colapsaría el navegador. Usa prefijos /22 o menores para el mapa visual.</p>
                    <a href='/documentador-red/vlans' style='color:#00d2ff;'>Volver</a>
                 </div>");
        }

        $ips_ocupadas = Vlan::obtenerIpsOcupadas($id_vlan);

        require_once __DIR__ . '/../views/infraestructura/mapa_ips.php';
    }

    // Procesa el formulario emergente del mapa de IPs
    public static function guardarIp() {
        Auth::require(Auth::STAFF);

        $id_vlan = Validar::entero($_POST['id_vlan'] ?? null);
        $ip      = Validar::post('direccion_ip');
        $accion  = Validar::post('accion', 'guardar');

        if ($id_vlan === null || !Validar::ipv4($ip) || !Vlan::obtenerPorId($id_vlan)) {
            header("Location: /documentador-red/vlans?mensaje=error");
            exit();
        }

        if ($accion === 'liberar') {
            Vlan::liberarIp($id_vlan, $ip);
        } else {
            $estado      = Validar::post('estado');
            $dispositivo = Validar::texto(Validar::post('dispositivo'), 100);
            $mac         = Validar::texto(Validar::post('mac_address'), 20);

            if (!Validar::enLista($estado, ['estatica', 'dhcp', 'reservada'])
                || ($mac !== '' && !Validar::mac($mac))) {
                header("Location: /documentador-red/mapa-ips?id=" . $id_vlan);
                exit();
            }
            Vlan::registrarIp($id_vlan, $ip, $estado, $dispositivo, $mac);
        }

        header("Location: /documentador-red/mapa-ips?id=" . $id_vlan);
        exit();
    }

    public static function eliminarVlan() {
        Auth::require(Auth::ADMIN);

        $id = Validar::entero($_POST['id_vlan'] ?? null);
        if ($id === null) {
            header("Location: /documentador-red/vlans");
            exit();
        }

        Vlan::eliminar($id);
        Log::registrar('VLANs', 'Eliminar VLAN', "Se eliminó de la base de datos la VLAN con ID interno: {$id}.");

        header("Location: /documentador-red/vlans?mensaje=eliminado");
        exit();
    }
}
