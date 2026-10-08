<?php
// app/models/Busqueda.php
require_once __DIR__ . '/../core/database.php';

class Busqueda {
    public static function global($termino) {
        $db = Database::conectar();
        $q = "%" . $termino . "%";
        
        // 1. BÚSQUEDA EN EQUIPOS (Ampliamos a ubicación, marca y modelo)
        $equipos = $db->prepare("SELECT id, hostname, ip_gestion, tipo, marca, modelo, ubicacion 
                                 FROM equipos 
                                 WHERE hostname LIKE ? OR ip_gestion LIKE ? OR ubicacion LIKE ? OR modelo LIKE ?");
        $equipos->execute([$q, $q, $q, $q]);
        
        // 2. BÚSQUEDA EN IPs y MACs (Se mantiene intacta, está muy bien)
        $ips = $db->prepare("SELECT d.*, v.numero_vlan, v.nombre_vlan 
                             FROM direcciones_ip d 
                             JOIN vlans v ON d.id_vlan = v.id 
                             WHERE d.direccion_ip LIKE ? OR d.mac_address LIKE ? OR d.dispositivo LIKE ?");
        $ips->execute([$q, $q, $q]);

        // 3. NUEVO: BÚSQUEDA EN PUERTOS Y CABLEADO (Ej. Buscar "Patch Panel", "Router ISP" o una IP de Capa 3)
        $puertos = $db->prepare("SELECT p.id, p.id_equipo, p.nombre_puerto, p.estado, p.destino, p.direccion_ip, e.hostname 
                                 FROM puertos p 
                                 JOIN equipos e ON p.id_equipo = e.id 
                                 WHERE p.nombre_puerto LIKE ? OR p.destino LIKE ? OR p.direccion_ip LIKE ?");
        $puertos->execute([$q, $q, $q]);

        // 4. NUEVO: BÚSQUEDA EN VLANs (Ej. Buscar "Datos_Admin", "192.168.10.0" o "10")
        $vlans = $db->prepare("SELECT id, numero_vlan, nombre_vlan, subred 
                               FROM vlans 
                               WHERE nombre_vlan LIKE ? OR subred LIKE ? OR numero_vlan LIKE ?");
        $vlans->execute([$q, $q, $q]);
        
        return [
            'equipos' => $equipos->fetchAll(PDO::FETCH_ASSOC),
            'ips'     => $ips->fetchAll(PDO::FETCH_ASSOC),
            'puertos' => $puertos->fetchAll(PDO::FETCH_ASSOC),
            'vlans'   => $vlans->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
}