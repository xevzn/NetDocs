<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
// ==============================================================================
// MOTOR DE BLINDAJE, VALIDACIÓN Y AUTO-CORRECCIÓN DE DATOS (UNIVERSAL L2 / L3)
// Compatible con Cisco Catalyst (3650/2960), Routers ISR y GNS3 (IOU/Dynamips)
// ==============================================================================

function valorSeguro($valor, string $porDefecto = 'No especificado'): string {
    if ($valor === null) return $porDefecto;
    $limpio = trim((string)$valor);
    if ($limpio === '' || strtolower($limpio) === 'unknown' || strtolower($limpio) === 'null') {
        return $porDefecto;
    }
    return $limpio;
}

$puertos_l2 = [];
$puertos_l3 = [];
$equipos_detectados = [];
$l2_up_count = 0;
$l2_down_count = 0;
$vlan_counter = [];

// 1. Clasificación inteligente de Interfaces (Capa 2 vs Capa 3)
if (!empty($lista_puertos) && is_array($lista_puertos)) {
    foreach ($lista_puertos as $p) {
        $hostname = valorSeguro($p['hostname'] ?? '', 'Equipo-Sin-Nombre');
        $equipos_detectados[$hostname] = true;

        $nombre = valorSeguro($p['nombre_puerto'] ?? '', 'Interfaz-N/D');
        $nombre_low = strtolower($nombre);
        $modo_raw = valorSeguro($p['modo_puerto'] ?? '', '');
        $vlan_raw = valorSeguro($p['vlan'] ?? '', '1');
        $vlan_low = strtolower($vlan_raw);
        $destino_raw = valorSeguro($p['destino'] ?? '', '');
        $estado_raw = strtolower(valorSeguro($p['estado'] ?? '', 'down'));
        $es_activo = in_array($estado_raw, ['up', 'connected', 'activo']);

        // Detectar si pertenece a Capa 3 (Routers, Subinterfaces 802.1Q, SVIs, Seriales, Loopbacks o Puertos Enrutados)
        $es_l3 = false;
        if (in_array($modo_raw, ['Gateway Inter-VLAN', 'SVI (Switch Virtual)', 'Enlace WAN Serial', 'Loopback', 'Capa 3 (Enrutado)', 'Capa 3', 'Subinterfaz L3', 'Router-L3'])) {
            $es_l3 = true;
        } elseif (!in_array($modo_raw, ['Acceso', 'Troncal'])) {
            // Rescate heurístico si el controlador SQL no envió la columna 'modo_puerto'
            if (
                strpos($nombre_low, '.') !== false ||
                strpos($nombre_low, 'vlan') === 0 ||
                strpos($nombre_low, 'serial') === 0 ||
                strpos($nombre_low, 'se') === 0 ||
                strpos($nombre_low, 'lo') === 0 ||
                strpos($destino_raw, 'IP:') === 0 ||
                $destino_raw === 'Sin IP asignada' ||
                in_array($vlan_low, ['n/a', 'ruteado', 'routed'])
            ) {
                $es_l3 = true;
            }
        }

        if ($es_l3) {
            // Determinar el Rol exacto en Capa 3
            if (strpos($nombre_low, '.') !== false) {
                $rol_l3 = 'Gateway Inter-VLAN (802.1Q)';
                $sub_vlan = explode('.', $nombre)[1] ?? 'N/A';
                $tipo_l3 = "Subinterfaz (VLAN $sub_vlan)";
            } elseif (strpos($nombre_low, 'vlan') === 0) {
                $rol_l3 = 'SVI (Switch Virtual Interface)';
                $num_svi = preg_replace('/[^0-9]/', '', $nombre) ?: 'N/A';
                $tipo_l3 = "Pasarela Virtual (VLAN $num_svi)";
            } elseif (strpos($nombre_low, 'serial') === 0 || strpos($nombre_low, 'se') === 0) {
                $rol_l3 = 'Enlace WAN Serial';
                $tipo_l3 = 'Interfaz Serial Punto a Punto';
            } elseif (strpos($nombre_low, 'lo') === 0) {
                $rol_l3 = 'Interfaz Loopback';
                $tipo_l3 = 'Interfaz Lógica de Gestión';
            } else {
                $rol_l3 = 'Puerto Físico Enrutado (L3)';
                $tipo_l3 = valorSeguro($p['tipo_interfaz'] ?? '', 'Ethernet / Gigabit L3');
            }

            // Formatear Dirección IP o Estado de Asignación
            if (strpos($destino_raw, 'IP:') === 0) {
                $ip_mostrar = trim(substr($destino_raw, 3));
            } elseif (!empty($p['direccion_ip'])) {
                $ip_mostrar = valorSeguro($p['direccion_ip'], 'Sin IP asignada');
            } elseif ($destino_raw !== '' && $destino_raw !== 'Sin IP asignada') {
                $ip_mostrar = $destino_raw;
            } else {
                $ip_mostrar = 'Sin IP asignada';
            }

            $puertos_l3[] = [
                'hostname' => $hostname,
                'nombre_puerto' => $nombre,
                'activo' => $es_activo,
                'rol_l3' => $rol_l3,
                'tipo_l3' => $tipo_l3,
                'ip' => $ip_mostrar
            ];
        } else {
            // Procesamiento estricto para Capa 2 (Switches Físicos y Virtuales)
            $es_trunk = (
                strtolower($modo_raw) === 'troncal' ||
                strpos($vlan_low, 'trunk') !== false ||
                strpos($vlan_low, 'troncal') !== false ||
                strpos($vlan_raw, ',') !== false
            );

            $etiqueta_vlan = $es_trunk ? 'Trunk (802.1Q)' : ('VLAN ' . (is_numeric($vlan_raw) ? $vlan_raw : '1'));

            // Contabilizar estadísticas reales de Capa 2 para gráficas y KPIs
            if ($es_activo) {
                $l2_up_count++;
            } else {
                $l2_down_count++;
            }
            $vlan_counter[$etiqueta_vlan] = ($vlan_counter[$etiqueta_vlan] ?? 0) + 1;

            $duplex = valorSeguro($p['modo_duplex'] ?? '', 'Auto');
            if ($duplex === '-') $duplex = 'Auto';

            $velocidad = valorSeguro($p['velocidad'] ?? '', 'Auto');
            if ($velocidad === '-') $velocidad = 'Auto';

            $medio = valorSeguro($p['tipo_interfaz'] ?? '', 'Ethernet / RJ45');
            if ($medio === '-') $medio = 'Ethernet / Virtual';

            $puertos_l2[] = [
                'hostname' => $hostname,
                'nombre_puerto' => $nombre,
                'activo' => $es_activo,
                'es_trunk' => $es_trunk,
                'vlan_label' => $etiqueta_vlan,
                'duplex_vel' => strtoupper($duplex) . ' / ' . strtoupper($velocidad),
                'medio' => $medio,
                'alias' => valorSeguro($destino_raw, 'Puerto disponible / Sin alias')
            ];
        }
    }
}

// Registrar equipos adicionales presentes en Rutas y Seguridad
if (!empty($lista_rutas) && is_array($lista_rutas)) {
    foreach ($lista_rutas as $r) {
        if (!empty($r['hostname'])) $equipos_detectados[trim($r['hostname'])] = true;
    }
}
if (!empty($lista_seguridad) && is_array($lista_seguridad)) {
    foreach ($lista_seguridad as $s) {
        if (!empty($s['hostname'])) $equipos_detectados[trim($s['hostname'])] = true;
    }
}

// 2. Auto-corrección de KPIs (Evita que salgan en 0 si la consulta SQL del controlador falló)
$kpi_equipos = max((int)($total_equipos ?? 0), count($equipos_detectados));
$kpi_puertos_l2_activos = ($l2_up_count > 0) ? $l2_up_count : (int)($puertos_activos ?? 0);
$kpi_rutas = max((int)($total_rutas ?? 0), is_array($lista_rutas ?? null) ? count($lista_rutas) : 0);

$riesgos_calculados = 0;
if (!empty($lista_seguridad) && is_array($lista_seguridad)) {
    foreach ($lista_seguridad as $s) {
        $telnet_on = strtolower(valorSeguro($s['telnet_enabled'] ?? '', 'false')) === 'true';
        $ssh_inseguro = valorSeguro($s['ssh_version'] ?? '', '2.0') !== '2.0';
        if ($telnet_on || $ssh_inseguro) {
            $riesgos_calculados++;
        }
    }
}
$kpi_riesgos = max((int)($riesgos_telnet ?? 0), $riesgos_calculados);

// 3. Preparación limpia de datos para Gráficas L2 (Sin "VLAN N/A")
$chart_vlan_labels = !empty($vlan_counter) ? array_keys($vlan_counter) : ($vlans_labels ?? []);
$chart_vlan_data   = !empty($vlan_counter) ? array_values($vlan_counter) : ($vlans_data ?? []);

$chart_port_labels = ($l2_up_count + $l2_down_count > 0) ? ['Activos (UP)', 'Inactivos (DOWN)'] : ($puertos_labels ?? []);
$chart_port_data   = ($l2_up_count + $l2_down_count > 0) ? [$l2_up_count, $l2_down_count] : ($puertos_data ?? []);
$chart_port_colors = ['#10b981', '#ef4444'];
?>

<div class="main-content">
    <header class="topbar">
        <h2 style="margin:0; font-size:1.2em;">Dashboard NOC: Telemetría y Auditoría</h2>
        <div class="user-info">
            <?php
                $roles = [1 => 'Administrador', 2 => 'Técnico', 3 => 'Lector'];
                $nombreRol = $roles[$_SESSION['rol_id'] ?? 0] ?? 'Operador NOC';
            ?>
            <span style="color: var(--text-muted); font-size: 0.9em; margin-right: 15px;">
                Motor: <b style="color: #10b981;">Ansible + Python</b>
            </span>
            Rol de Sesión: <b style="color: var(--text-main);"><?php echo htmlspecialchars($nombreRol); ?></b>
            <a href="/documentador-red/logout" class="btn-logout" style="margin-left: 15px; text-decoration: none;">Cerrar Sesión</a>
        </div>
    </header>

    <main class="workspace">
        <style>
            .filter-bar { display: flex; gap: 15px; background: var(--panel-bg); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); align-items: flex-end; margin-bottom: 25px; flex-wrap: wrap; }
            .custom-select { background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); padding: 10px 12px; border-radius: 4px; outline: none; min-width: 220px; font-weight: 500; }
            .btn-filter { background: var(--primary); color: #000; border: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; transition: 0.2s; }
            .btn-filter:hover { background: var(--primary-hover); }
            
            .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 15px; margin-bottom: 25px; }
            .kpi-card { background: var(--panel-bg); border: 1px solid var(--border-color); padding: 20px; border-radius: 6px; border-left: 4px solid #3b82f6; }
            .kpi-title { color: var(--text-muted); font-size: 0.82em; text-transform: uppercase; font-weight: bold; margin-bottom: 8px; letter-spacing: 0.5px; }
            .kpi-value { color: white; font-size: 2em; font-family: monospace; font-weight: bold; }
            .kpi-sub { color: var(--text-muted); font-size: 0.78em; margin-top: 6px; }
            
            .tab-buttons { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; flex-wrap: wrap; }
            .tab-btn { background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); padding: 10px 20px; cursor: pointer; font-weight: bold; border-radius: 4px; transition: 0.2s; }
            .tab-btn.active { background: var(--primary); color: #000; border-color: var(--primary); }
            .tab-content { display: none; }
            .tab-content.active { display: block; animation: fadeIn 0.25s ease-in-out; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(3px); } to { opacity: 1; transform: translateY(0); } }

            .chart-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 25px; }
            .chart-box { background: var(--panel-bg); border: 1px solid var(--border-color); padding: 20px; border-radius: 8px; }
            .chart-title { color: #ffffff; margin-top: 0; font-size: 1.02em; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; }
            
            .data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85em; }
            .data-table th { background: var(--bg-body); color: var(--text-muted); padding: 12px; border-bottom: 2px solid var(--border-color); font-weight: bold; text-transform: uppercase; font-size: 0.8em; }
            .data-table td { padding: 12px; border-bottom: 1px solid var(--border-color); color: var(--text-main); vertical-align: middle; }
            .data-table tr:hover td { background: rgba(255,255,255,0.03); }
            
            .badge-green { background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid #10b981; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.82em; display: inline-block; }
            .badge-red { background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid #ef4444; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.82em; display: inline-block; }
            .badge-orange { background: rgba(245, 158, 11, 0.12); color: #f59e0b; border: 1px solid #f59e0b; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.82em; display: inline-block; }
            .badge-purple { background: rgba(139, 92, 246, 0.12); color: #a78bfa; border: 1px solid #8b5cf6; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.82em; display: inline-block; }
            .badge-blue { background: rgba(59, 130, 246, 0.12); color: #60a5fa; border: 1px solid #3b82f6; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.82em; display: inline-block; }
            .badge-cyan { background: rgba(6, 182, 212, 0.12); color: #22d3ee; border: 1px solid #06b6d4; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.82em; display: inline-block; }
            .badge-muted { background: rgba(148, 163, 184, 0.1); color: #94a3b8; border: 1px solid #475569; padding: 3px 8px; border-radius: 4px; font-size: 0.82em; display: inline-block; }

            .section-header { color: white; margin-top: 25px; font-size: 1.08em; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
            .empty-state { text-align: center; padding: 30px 20px; color: var(--text-muted); background: rgba(255,255,255,0.01); border: 1px dashed var(--border-color); border-radius: 6px; margin: 15px 0; }
        </style>

        <!-- BARRA DE FILTROS -->
        <form method="GET" class="filter-bar">
            <div>
                <label style="display:block; color:var(--text-muted); font-size:0.85em; font-weight:bold; margin-bottom:8px;">Filtro por Equipo (Hostname):</label>
                <select name="equipo" class="custom-select">
                    <option value="todos">Toda la Red Auditada</option>
                    <?php if (!empty($equipos_list) && is_array($equipos_list)): ?>
                        <?php foreach ($equipos_list as $eq): ?>
                            <option value="<?= htmlspecialchars($eq ?? '') ?>" <?= ($filtro_equipo ?? '') === $eq ? 'selected' : '' ?>><?= htmlspecialchars($eq ?? '') ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <label style="display:block; color:var(--text-muted); font-size:0.85em; font-weight:bold; margin-bottom:8px;">Filtro por Sistema / Marca:</label>
                <select name="marca" class="custom-select">
                    <option value="todas">Todos los Sistemas Operativos</option>
                    <?php if (!empty($marcas_list) && is_array($marcas_list)): ?>
                        <?php foreach ($marcas_list as $m): ?>
                            <option value="<?= htmlspecialchars($m ?? '') ?>" <?= ($filtro_marca ?? '') === $m ? 'selected' : '' ?>><?= htmlspecialchars($m ?? '') ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn-filter">Aplicar Filtros</button>
                <a href="?equipo=todos&marca=todas" style="color: var(--text-muted); text-decoration: none; margin-left: 15px; font-size: 0.9em; font-weight: 500;">Restablecer</a>
            </div>
        </form>

        <!-- TARJETAS KPI AUTO-VALIDADAS -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-title">Dispositivos Auditados</div>
                <div class="kpi-value"><?= $kpi_equipos ?></div>
                <div class="kpi-sub">Nodos respondiendo a Ansible</div>
            </div>
            <div class="kpi-card" style="border-left-color: #10b981;">
                <div class="kpi-title">Puertos Físicos Activos (L2)</div>
                <div class="kpi-value"><?= $kpi_puertos_l2_activos ?> <span style="font-size: 0.45em; color: var(--text-muted);">/ <?= count($puertos_l2) ?> total</span></div>
                <div class="kpi-sub">Enlaces de Switching en estado UP</div>
            </div>
            <div class="kpi-card" style="border-left-color: #8b5cf6;">
                <div class="kpi-title">Rutas e Interfaces L3</div>
                <div class="kpi-value"><?= $kpi_rutas ?> <span style="font-size: 0.45em; color: var(--text-muted);">(<?= count($puertos_l3) ?> GW/L3)</span></div>
                <div class="kpi-sub">Prefijos en tabla de enrutamiento</div>
            </div>
            <div class="kpi-card" style="border-left-color: <?= $kpi_riesgos > 0 ? '#f59e0b' : '#10b981' ?>;">
                <div class="kpi-title">Alertas de Seguridad</div>
                <div class="kpi-value" style="color: <?= $kpi_riesgos > 0 ? '#f59e0b' : '#10b981' ?>;"><?= $kpi_riesgos ?></div>
                <div class="kpi-sub">SSH v1.99 o Telnet activo</div>
            </div>
        </div>

        <?php if ($kpi_equipos == 0): ?>
            <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; color: #f59e0b; padding: 25px; border-radius: 6px; margin-bottom: 25px; text-align: center; font-weight: bold;">
                No se encontraron registros de auditoría para los filtros actuales. Ejecuta el Playbook de Ansible para sincronizar los datos operativos.
            </div>
        <?php else: ?>

            <!-- CONTENEDOR DE PESTAÑAS NOC -->
            <div style="background: var(--panel-bg); border-radius: 8px; border-top: 3px solid var(--primary); padding: 25px;">
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="openTab(event, 'tab-l2')">🔌 Monitor Capa 2 (Switching - <?= count($puertos_l2) ?>)</button>
                    <button class="tab-btn" onclick="openTab(event, 'tab-l3')">🔀 Monitor Capa 3 (Ruteo & SVIs - <?= count($puertos_l3) ?>)</button>
                    <button class="tab-btn" onclick="openTab(event, 'tab-sec')">🛡️ Postura de Seguridad (<?= is_array($lista_seguridad ?? null) ? count($lista_seguridad) : 0 ?>)</button>
                </div>

                <!-- ========================================== -->
                <!-- PESTAÑA 1: CAPA 2 (SWITCHING)              -->
                <!-- ========================================== -->
                <div id="tab-l2" class="tab-content active">
                    <div class="chart-row">
                        <div class="chart-box">
                            <h3 class="chart-title">Distribución de VLANs (Acceso vs Troncales)</h3>
                            <?php if (!empty($chart_vlan_data)): ?>
                                <div style="height: 220px;"><canvas id="vlanChart"></canvas></div>
                            <?php else: ?>
                                <div class="empty-state">Sin datos de VLANs de Capa 2 para graficar en esta selección.</div>
                            <?php endif; ?>
                        </div>
                        <div class="chart-box">
                            <h3 class="chart-title">Estado Operativo de Puertos Físicos (L2)</h3>
                            <?php if (!empty($chart_port_data)): ?>
                                <div style="height: 220px;"><canvas id="puertosChart"></canvas></div>
                            <?php else: ?>
                                <div class="empty-state">Sin métricas de puertos físicos L2 disponibles.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h3 class="section-header">
                        <span>Auditoría de Interfaces de Switching (Catalyst / IOU L2)</span>
                        <span style="font-size: 0.8em; color: var(--text-muted); font-weight: normal;">Mostrando <?= count($puertos_l2) ?> interfaces físicas</span>
                    </h3>

                    <?php if (empty($puertos_l2)): ?>
                        <div class="empty-state">
                            Este dispositivo no opera con puertos de conmutación Capa 2 (o el filtro actual corresponde exclusivamente a un Router de Capa 3).<br>
                            Consulta la pestaña <b>"Monitor Capa 3 (Ruteo & SVIs)"</b> para ver sus interfaces.
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Switch Hostname</th>
                                        <th>Puerto Físico</th>
                                        <th>Estado Enlace</th>
                                        <th>Modo / VLAN</th>
                                        <th>Dúplex / Velocidad</th>
                                        <th>Medio Físico</th>
                                        <th>Descripción / Destino</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($puertos_l2 as $p): ?>
                                        <tr>
                                            <td style="font-weight:bold; color:white;"><?= htmlspecialchars($p['hostname']) ?></td>
                                            <td style="color: var(--primary); font-family: monospace; font-weight: bold;"><?= htmlspecialchars($p['nombre_puerto']) ?></td>
                                            <td>
                                                <?php if ($p['activo']): ?>
                                                    <span class="badge-green">CONNECTED (UP)</span>
                                                <?php else: ?>
                                                    <span class="badge-red">DOWN</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($p['es_trunk']): ?>
                                                    <span class="badge-purple">TRUNK (802.1Q)</span>
                                                <?php else: ?>
                                                    <span class="badge-blue"><?= htmlspecialchars($p['vlan_label']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-family: monospace; color: var(--text-muted);"><?= htmlspecialchars($p['duplex_vel']) ?></td>
                                            <td style="color: var(--text-muted);"><?= htmlspecialchars($p['medio']) ?></td>
                                            <td>
                                                <?php if ($p['alias'] === 'Puerto disponible / Sin alias'): ?>
                                                    <span style="color: #64748b; font-style: italic;"><?= htmlspecialchars($p['alias']) ?></span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-main); font-weight: 500;"><?= htmlspecialchars($p['alias']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ========================================== -->
                <!-- PESTAÑA 2: CAPA 3 (RUTEO, SUBINT & SVIs)   -->
                <!-- ========================================== -->
                <div id="tab-l3" class="tab-content">
                    <h3 class="section-header">
                        <span>Pasarelas L3, Subinterfaces Router-on-a-Stick, Enlaces Seriales y SVIs</span>
                        <span style="font-size: 0.8em; color: var(--text-muted); font-weight: normal;"><?= count($puertos_l3) ?> interfaces registradas</span>
                    </h3>

                    <?php if (empty($puertos_l3)): ?>
                        <div class="empty-state">
                            No se encontraron interfaces de Capa 3, subinterfaces 802.1Q ni SVIs activas para el filtro seleccionado.
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto; margin-bottom: 35px;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Equipo L3</th>
                                        <th>Interfaz Lógica / Física</th>
                                        <th>Estado Protocolo</th>
                                        <th>Función de Capa 3</th>
                                        <th>Tipo de Interfaz</th>
                                        <th>Direccionamiento IPv4</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($puertos_l3 as $p): ?>
                                        <tr>
                                            <td style="font-weight:bold; color:white;"><?= htmlspecialchars($p['hostname']) ?></td>
                                            <td style="color: #22d3ee; font-family: monospace; font-weight: bold; font-size: 1.05em;"><?= htmlspecialchars($p['nombre_puerto']) ?></td>
                                            <td>
                                                <?php if ($p['activo']): ?>
                                                    <span class="badge-green">UP / UP</span>
                                                <?php else: ?>
                                                    <span class="badge-red">ADMIN DOWN / DOWN</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge-cyan"><?= htmlspecialchars($p['rol_l3']) ?></span></td>
                                            <td style="color: var(--text-muted);"><?= htmlspecialchars($p['tipo_l3']) ?></td>
                                            <td style="font-family: monospace; font-weight: bold;">
                                                <?php if ($p['ip'] === 'Sin IP asignada'): ?>
                                                    <span class="badge-muted">Sin IP asignada (Unassigned)</span>
                                                <?php else: ?>
                                                    <span style="color: #10b981;"><?= htmlspecialchars($p['ip']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <h3 class="section-header">
                        <span>Tabla de Enrutamiento Global (RIB)</span>
                        <span style="font-size: 0.8em; color: var(--text-muted); font-weight: normal;"><?= $kpi_rutas ?> rutas aprendidas</span>
                    </h3>

                    <?php if (empty($lista_rutas)): ?>
                        <div class="empty-state">No hay rutas estáticas ni dinámicas registradas para esta selección.</div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Equipo Hostname</th>
                                        <th>Protocolo de Origen</th>
                                        <th>Prefijo / Red Destino</th>
                                        <th>Siguiente Salto (Next-Hop)</th>
                                        <th>Interfaz de Salida</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista_rutas as $r): 
                                        $proto_map = [
                                            'C' => 'Connected (Directa)',
                                            'L' => 'Local (Host /32)',
                                            'S' => 'Static (Estática)',
                                            'S*' => 'Static Default (0.0.0.0/0)',
                                            'O' => 'OSPF Intra-Area',
                                            'O IA' => 'OSPF Inter-Area',
                                            'D' => 'EIGRP',
                                            'B' => 'BGP',
                                            'R' => 'RIP'
                                        ];
                                        $codigo_proto = strtoupper(valorSeguro($r['protocolo'] ?? '', 'S'));
                                        $nombre_proto = $proto_map[$codigo_proto] ?? "Protocolo ($codigo_proto)";
                                        $red_dest = valorSeguro($r['red_destino'] ?? '', '0.0.0.0/0');
                                        $next_hop = valorSeguro($r['next_hop'] ?? '', '');
                                        $int_salida = valorSeguro($r['interfaz_salida'] ?? '', '');
                                    ?>
                                        <tr>
                                            <td style="font-weight:bold; color:white;"><?= htmlspecialchars(valorSeguro($r['hostname'] ?? '', 'Router-L3')) ?></td>
                                            <td>
                                                <?php if (strpos($codigo_proto, 'S') === 0): ?>
                                                    <span class="badge-purple"><?= htmlspecialchars($nombre_proto) ?></span>
                                                <?php elseif ($codigo_proto === 'C' || $codigo_proto === 'L'): ?>
                                                    <span class="badge-blue"><?= htmlspecialchars($nombre_proto) ?></span>
                                                <?php else: ?>
                                                    <span class="badge-cyan"><?= htmlspecialchars($nombre_proto) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-family: monospace; font-size:1.05em; color:white; font-weight:bold;"><?= htmlspecialchars($red_dest) ?></td>
                                            <td style="font-family: monospace; color: var(--primary);">
                                                <?= ($next_hop !== '' && $next_hop !== '-') ? 'Via ' . htmlspecialchars($next_hop) : '<span style="color:#10b981;">Directamente Conectado</span>' ?>
                                            </td>
                                            <td style="font-family: monospace; color: var(--text-muted);">
                                                <?= ($int_salida !== '' && $int_salida !== '-') ? htmlspecialchars($int_salida) : 'Resolución por Tabla ARP / Next-Hop' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ========================================== -->
                <!-- PESTAÑA 3: SEGURIDAD Y CUMPLIMIENTO        -->
                <!-- ========================================== -->
                <div id="tab-sec" class="tab-content">
                    <h3 class="section-header">Auditoría de Plano de Control y Accesos Administrativos</h3>
                    <?php if (empty($lista_seguridad)): ?>
                        <div class="empty-state">No hay registros de auditoría de seguridad para los filtros seleccionados.</div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Equipo Hostname</th>
                                        <th>Cifrado SSH</th>
                                        <th>Estado de Telnet</th>
                                        <th>Usuarios Locales Configurados</th>
                                        <th>Listas de Control de Acceso (ACLs)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista_seguridad as $s): 
                                        $ssh_v = valorSeguro($s['ssh_version'] ?? '', '1.99');
                                        $telnet_on = strtolower(valorSeguro($s['telnet_enabled'] ?? '', 'false')) === 'true';
                                        $num_users = (int) filter_var($s['users_list'] ?? '0', FILTER_SANITIZE_NUMBER_INT);
                                        $acls = (int) ($s['acl_count'] ?? 0);
                                    ?>
                                        <tr>
                                            <td style="font-weight:bold; color:white;"><?= htmlspecialchars(valorSeguro($s['hostname'] ?? '', 'Equipo')) ?></td>
                                            <td>
                                                <?php if ($ssh_v === '2.0'): ?>
                                                    <span class="badge-green">SSH v2.0 (Cumple Estándar)</span>
                                                <?php else: ?>
                                                    <span class="badge-orange">SSH v<?= htmlspecialchars($ssh_v) ?> (Actualizar a v2)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($telnet_on): ?>
                                                    <span class="badge-red">Habilitado (Texto Plano / Crítico)</span>
                                                <?php else: ?>
                                                    <span class="badge-green">Deshabilitado (Solo SSH)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($num_users === 0): ?>
                                                    <span class="badge-red">0 Cuentas (Riesgo de Bloqueo)</span>
                                                <?php else: ?>
                                                    <span class="badge-blue"><?= $num_users ?> Cuenta(s) Privilegiada(s)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($acls > 0): ?>
                                                    <span class="badge-cyan"><?= $acls ?> ACL(s) Activa(s)</span>
                                                <?php else: ?>
                                                    <span class="badge-muted">0 Reglas ACL configuradas</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>
    </main>
</div>

<!-- LIBRERÍA CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
function openTab(evt, tabName) {
    let i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
        tabcontent[i].classList.remove("active");
    }
    tablinks = document.getElementsByClassName("tab-btn");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($kpi_equipos > 0): ?>
    
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.plugins.legend.labels.color = '#f8fafc';

    // Gráfica VLANs L2 (Excluye puertos L3/N/A automáticamente)
    <?php if (!empty($chart_vlan_data)): ?>
    const vlanCanvas = document.getElementById('vlanChart');
    if (vlanCanvas) {
        new Chart(vlanCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chart_vlan_labels) ?>,
                datasets: [{
                    data: <?= json_encode($chart_vlan_data) ?>,
                    backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#06b6d4', '#ec4899'],
                    borderColor: '#111111',
                    borderWidth: 3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '72%' }
        });
    }
    <?php endif; ?>

    // Gráfica Estado de Puertos L2
    <?php if (!empty($chart_port_data)): ?>
    const portCanvas = document.getElementById('puertosChart');
    if (portCanvas) {
        new Chart(portCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chart_port_labels) ?>,
                datasets: [{
                    data: <?= json_encode($chart_port_data) ?>,
                    backgroundColor: <?= json_encode($chart_port_colors) ?>,
                    borderColor: '#111111',
                    borderWidth: 3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '72%' }
        });
    }
    <?php endif; ?>

    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
