<?php
// app/models/Topologia.php
require_once __DIR__ . '/../core/database.php';

class Topologia {

    public static function obtenerElementosMapa() {
        $db = Database::conectar();

        // 1. INVENTARIO DE EQUIPOS
        $stmt = $db->query("SELECT id, hostname, marca, modelo, tipo, plantilla_conexion, ip_gestion, ubicacion, comentarios FROM equipos ORDER BY hostname");
        $inventario = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $eq) {
            $hostname = strtoupper(trim($eq['hostname']));
            $inventario[$hostname] = $eq;
        }

        // 2. ÚLTIMA LECTURA DE SALUD
        $stmt = $db->query("
            SELECT hs.id_equipo, hs.cpu_load, hs.memory_used, hs.memory_free, hs.temperatura, hs.timestamp
            FROM historial_salud hs
            INNER JOIN (
                SELECT id_equipo, MAX(timestamp) AS ultima FROM historial_salud GROUP BY id_equipo
            ) ult ON ult.id_equipo = hs.id_equipo AND ult.ultima = hs.timestamp
        ");
        $salud = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $salud[$h['id_equipo']] = $h;
        }

        // 3. TOPOLOGÍA
        $stmt = $db->query("SELECT origen, puerto_local, destino, puerto_remoto, protocolo, fecha_descubrimiento FROM topologia_enlaces ORDER BY origen, destino");
        $enlaces_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $nodos_unicos = [];
        $vecinos_por_nodo = [];
        foreach ($enlaces_db as $e) {
            $o = strtoupper(trim($e['origen']));
            $d = strtoupper(trim($e['destino']));
            if (!$o || !$d || $o === $d) continue;
            
            $nodos_unicos[$o] = true;
            $nodos_unicos[$d] = true;
            
            $vecinos_por_nodo[$o][] = ['puerto' => $e['puerto_local'], 'vecino' => $d, 'puerto_vecino' => $e['puerto_remoto']];
            $vecinos_por_nodo[$d][] = ['puerto' => $e['puerto_remoto'], 'vecino' => $o, 'puerto_vecino' => $e['puerto_local']];
        }
        $nodos_unicos = array_keys($nodos_unicos);

        // 4. CONSTRUCCIÓN DEL JSON PARA CYTOSCAPE
        $cy_elements = [];
        $aristas_dibujadas = [];

        foreach ($nodos_unicos as $nodo) {
            $u = strtoupper($nodo);
            $eq = $inventario[$u] ?? null;
            $tipo = self::normalizarTipo($eq['tipo'] ?? '', $u);
            $idEquipo = $eq['id'] ?? null;
            $h = $idEquipo ? ($salud[$idEquipo] ?? null) : null;
            $estado = self::estadoSalud($h);
            
            $cy_elements[] = [
                'data' => [
                    'id' => $u,
                    'label' => $u,
                    'ip' => $eq['ip_gestion'] ?? 'Desconocida',
                    'tipo' => strtoupper($eq['tipo'] ?? $tipo),
                    'role' => strtoupper($tipo),
                    'marca' => $eq['marca'] ?? 'No registrada',
                    'modelo' => $eq['modelo'] ?? 'No registrado',
                    'os' => strtoupper(str_replace('_', ' ', $eq['plantilla_conexion'] ?? 'No registrada')),
                    'ubicacion' => $eq['ubicacion'] ?? 'No registrada',
                    'comentarios' => $eq['comentarios'] ?? '',
                    'status' => $estado,
                    'statusText' => self::textoEstado($estado),
                    'cpu' => $h['cpu_load'] ?? null,
                    'memory' => $h['memory_used'] ?? null,
                    'memoryFree' => $h['memory_free'] ?? null,
                    'temperature' => $h['temperatura'] ?? null,
                    'lastRead' => $h['timestamp'] ?? null,
                    'neighbors' => $vecinos_por_nodo[$u] ?? []
                ],
                'classes' => "$tipo $estado"
            ];
        }

        foreach ($enlaces_db as $e) {
            $src = strtoupper(trim($e['origen']));
            $dst = strtoupper(trim($e['destino']));
            if (!$src || !$dst || $src === $dst) continue;
            
            $proto = strtolower(trim($e['protocolo'] ?? 'cdp'));
            $ids = [$src, $dst];
            sort($ids);
            $hash = implode('_', $ids);
            
            if (isset($aristas_dibujadas[$hash])) continue;
            
            $ancho = stripos($e['puerto_local'], 'te') !== false ? 4 : 2;
            $cy_elements[] = [
                'data' => [
                    'id' => $hash,
                    'source' => $src,
                    'target' => $dst,
                    'proto' => strtoupper($proto),
                    'psrc' => $e['puerto_local'],
                    'pdst' => $e['puerto_remoto'],
                    'width' => $ancho,
                    'date' => $e['fecha_descubrimiento']
                ],
                'classes' => $proto
            ];
            $aristas_dibujadas[$hash] = true;
        }

        return [
            'json_elements' => json_encode($cy_elements, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'nodos_unicos' => $nodos_unicos
        ];
    }

    // Funciones Auxiliares Privadas
    private static function normalizarTipo($tipo, $hostname = '') {
        $tipo = strtoupper(trim((string)$tipo));
        $hostname = strtoupper($hostname);
        if (strpos($tipo, 'CORE') !== false || strpos($hostname, 'CORE') !== false) return 'core';
        if (strpos($tipo, 'FIREWALL') !== false || strpos($tipo, 'FW') !== false) return 'firewall';
        if (strpos($tipo, 'ROUTER') !== false || strpos($tipo, 'RTR') !== false) return 'router';
        if (strpos($tipo, 'SWITCH') !== false || strpos($tipo, 'SW') !== false) return 'switch';
        return 'switch';
    }

    private static function estadoSalud($h) {
        if (!$h) return 'unknown';
        $cpu = is_numeric($h['cpu_load']) ? (float)$h['cpu_load'] : null;
        $mem = is_numeric($h['memory_used']) ? (float)$h['memory_used'] : null;
        $temp = is_numeric($h['temperatura']) ? (float)$h['temperatura'] : null;
        if (($cpu !== null && $cpu >= 90) || ($mem !== null && $mem >= 90) || ($temp !== null && $temp >= 80)) return 'critical';
        if (($cpu !== null && $cpu >= 75) || ($mem !== null && $mem >= 80) || ($temp !== null && $temp >= 65)) return 'warning';
        return 'ok';
    }

    private static function textoEstado($estado) {
        return [
            'ok' => 'OK',
            'warning' => 'WARNING',
            'critical' => 'CRÍTICO',
            'unknown' => 'SIN DATOS'
        ][$estado] ?? 'SIN DATOS';
    }
}