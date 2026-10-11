<?php
// app/models/reporteansible.php
require_once __DIR__ . '/../core/database.php';

class ReporteAnsible {

    // Extrae las opciones para llenar los "selects" de los filtros
    public static function obtenerFiltros() {
        $db = Database::conectar();
        $equipos = $db->query("SELECT hostname FROM equipos ORDER BY hostname")->fetchAll(PDO::FETCH_COLUMN);
        $marcas = $db->query("SELECT DISTINCT plantilla_conexion FROM equipos WHERE plantilla_conexion IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        
        return ['equipos' => $equipos, 'marcas' => $marcas];
    }

    // Ejecuta todas las consultas matemáticas y devuelve un arreglo limpio
    public static function obtenerEstadisticas($filtro_equipo, $filtro_marca) {
        $db = Database::conectar();
        $where_clause = "1=1";
        $params = [];

        if ($filtro_equipo !== 'todos') {
            $where_clause .= " AND e.hostname = :hostname";
            $params[':hostname'] = $filtro_equipo;
        }
        if ($filtro_marca !== 'todas') {
            $where_clause .= " AND e.plantilla_conexion = :marca";
            $params[':marca'] = $filtro_marca;
        }

        // ==========================================
        // 1. KPI: Total de Equipos (CORREGIDO)
        // Ahora solo cuenta equipos que realmente tengan datos en la tabla de auditoría
        // ==========================================
        $stmt = $db->prepare("SELECT COUNT(DISTINCT e.id) as total FROM equipos e INNER JOIN puertos_operativos p ON e.id = p.id_equipo WHERE $where_clause");
        $stmt->execute($params);
        $total_equipos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // 2. INFRAESTRUCTURA
        $stmt = $db->prepare("SELECT COUNT(*) as activos FROM puertos_operativos p INNER JOIN equipos e ON p.id_equipo = e.id WHERE p.estado = 'up' AND $where_clause");
        $stmt->execute($params);
        $puertos_activos = $stmt->fetch(PDO::FETCH_ASSOC)['activos'] ?? 0;

        // Gráfica VLANs
        $stmt = $db->prepare("SELECT p.vlan, COUNT(*) as total FROM puertos_operativos p INNER JOIN equipos e ON p.id_equipo = e.id WHERE p.vlan IS NOT NULL AND p.vlan != '' AND p.modo_puerto != 'Router-L3' AND $where_clause GROUP BY p.vlan ORDER BY total DESC LIMIT 5");
        $stmt->execute($params);
        $vlans_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $vlans_labels = []; $vlans_data = [];
        foreach ($vlans_db as $v) { 
            $vlans_labels[] = "VLAN " . $v['vlan']; 
            $vlans_data[] = $v['total']; 
        }

        // Gráfica Puertos
        $stmt = $db->prepare("SELECT p.estado, COUNT(*) as total FROM puertos_operativos p INNER JOIN equipos e ON p.id_equipo = e.id WHERE p.estado IS NOT NULL AND $where_clause GROUP BY p.estado");
        $stmt->execute($params);
        $estados_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $puertos_labels = []; $puertos_data = []; $puertos_colors = [];
        foreach ($estados_db as $est) {
            $estado_clean = strtoupper($est['estado']);
            $puertos_labels[] = $estado_clean;
            $puertos_data[] = $est['total'];
            $puertos_colors[] = ($estado_clean === 'UP') ? '#10b981' : '#ef4444';
        }

        // Detalle Puertos
        $stmt = $db->prepare("SELECT e.hostname, p.nombre_puerto, p.estado, p.vlan, p.destino, p.modo_duplex, p.velocidad, p.tipo_interfaz FROM puertos_operativos p INNER JOIN equipos e ON p.id_equipo = e.id WHERE $where_clause ORDER BY e.hostname, p.nombre_puerto LIMIT 100");
        $stmt->execute($params);
        $lista_puertos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. SEGURIDAD
        $stmt = $db->prepare("SELECT COUNT(*) as riesgos FROM auditoria_seguridad s INNER JOIN equipos e ON s.id_equipo = e.id WHERE s.telnet_enabled = 'True' AND $where_clause");
        $stmt->execute($params);
        $riesgos_telnet = $stmt->fetch(PDO::FETCH_ASSOC)['riesgos'] ?? 0;

        $stmt = $db->prepare("SELECT e.hostname, s.ssh_version, s.telnet_enabled, s.users_list, s.acl_count FROM auditoria_seguridad s INNER JOIN equipos e ON s.id_equipo = e.id WHERE $where_clause ORDER BY e.hostname");
        $stmt->execute($params);
        $lista_seguridad = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. RUTEO
        $stmt = $db->prepare("SELECT COUNT(*) as total_rutas FROM tabla_enrutamiento r INNER JOIN equipos e ON r.id_equipo = e.id WHERE $where_clause");
        $stmt->execute($params);
        $total_rutas = $stmt->fetch(PDO::FETCH_ASSOC)['total_rutas'] ?? 0;

        $stmt = $db->prepare("SELECT e.hostname, r.protocolo, r.red_destino, r.next_hop, r.interfaz_salida FROM tabla_enrutamiento r INNER JOIN equipos e ON r.id_equipo = e.id WHERE $where_clause ORDER BY e.hostname, r.red_destino LIMIT 100");
        $stmt->execute($params);
        $lista_rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Devolvemos todo en un solo paquete estructurado
        return [
            'total_equipos'   => $total_equipos,
            'puertos_activos' => $puertos_activos,
            'vlans_labels'    => $vlans_labels,
            'vlans_data'      => $vlans_data,
            'puertos_labels'  => $puertos_labels,
            'puertos_data'    => $puertos_data,
            'puertos_colors'  => $puertos_colors,
            'lista_puertos'   => $lista_puertos,
            'riesgos_telnet'  => $riesgos_telnet,
            'lista_seguridad' => $lista_seguridad,
            'total_rutas'     => $total_rutas,
            'lista_rutas'     => $lista_rutas
        ];
    }
}