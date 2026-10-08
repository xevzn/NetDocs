<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) {
    header("Location: /documentador-red/dashboard");
    exit();
}

if (empty($_SESSION['csrf_vlan_token'])) {
    $_SESSION['csrf_vlan_token'] = bin2hex(random_bytes(32));
}

$tab_activa = $_GET['tab'] ?? 'manual';
$info = $_SESSION['vlan_info'] ?? null;
$trace = $_SESSION['vlan_trace'] ?? null;
$error = $_SESSION['vlan_error'] ?? null;
$success = $_SESSION['vlan_success'] ?? null;

unset($_SESSION['vlan_error'], $_SESSION['vlan_success']);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="main-content">
    <header class="topbar">
        <h2 style="margin: 0; font-size: 1.2em;">Asignación de Puertos y VLANs</h2>
            <div class="user-info">
                <?php
                    $roles = [
                        1 => 'Administrador',
                        2 => 'Técnico',
                        3 => 'Lector'
                    ];

                    $nombreRol = $roles[$_SESSION['rol_id']] ?? 'Rol desconocido';
                ?>
                Rol de Sesión: <b style="color: var(--text-main);">
                    <?php echo htmlspecialchars($nombreRol); ?>
                </b>
                <a href="/documentador-red/logout" class="btn-logout" style="margin-left: 15px; text-decoration: none;">Cerrar Sesión</a>
            </div>
    </header>

    <main class="workspace">
        <style>
            /* Variables estandarizadas OLED */
            .custom-input { width: 100%; padding: 12px; background-color: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; outline: none; font-family: monospace; font-size: 1.05em; transition: 0.3s; }
            .custom-input:focus { border-color: var(--primary); }
            
            .btn-run { background: var(--primary); color: #000; border: none; font-weight: bold; padding: 12px 25px; border-radius: 4px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 10px; font-size: 1em; }
            .btn-run:hover { background: var(--primary-hover); }
            .btn-run.success { background: var(--port-up); color: #000; }
            
            /* Pestañas Estandarizadas */
            .tab-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
            .tab-btn { background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); padding: 10px 20px; cursor: pointer; font-weight: bold; border-radius: 4px; transition: 0.3s; }
            .tab-btn.active { background: var(--primary); color: #000; border-color: var(--primary); }
            .tab-content { display: none; }
            .tab-content.active { display: block; animation: fadeIn 0.3s; }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

            .metric-card { background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 6px; padding: 20px; text-align: left; flex: 1; border-left: 4px solid var(--port-up); }
            .metric-title { color: var(--text-muted); font-size: 0.85em; font-weight: bold; margin-bottom: 8px; text-transform: uppercase; }
            .metric-value { color: white; font-size: 1.4em; font-family: Consolas, monospace; font-weight: bold; }
            
            /* Acordeón Documentación */
            details { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 25px; border-left: 4px solid #3b82f6; }
            summary { padding: 15px 20px; font-weight: bold; color: white; cursor: pointer; outline: none; user-select: none; list-style: none; display: flex; justify-content: space-between; align-items: center; }
            summary::-webkit-details-marker { display: none; }
            summary::after { content: '+'; color: #3b82f6; font-size: 1.2em; font-weight: bold; }
            details[open] summary::after { content: '-'; }
            details[open] summary { border-bottom: 1px solid var(--border-color); }
            .details-content { padding: 20px; color: var(--text-muted); font-size: 0.9em; line-height: 1.6; background: var(--bg-body); border-radius: 0 0 6px 6px; }
            .details-content b { color: var(--text-main); }
        </style>

        <!-- DOCUMENTACIÓN EXPANDIBLE -->
        <details>
            <summary>Documentación Técnica: Inyección Netmiko y Algoritmo de Rastreo</summary>
            <div class="details-content">
                Esta herramienta permite provisionar puertos individuales en switches de la red (cambiar la VLAN asignada) utilizando scripts de <b>Netmiko</b> por debajo.<br><br>
                1. <b>Modo Manual:</b> Ideal si conoces el switch y el puerto físico exacto que deseas reconfigurar. El sistema conectará, verificará el estado del puerto, y te permitirá inyectar la configuración.<br>
                2. <b>Modo Automático:</b> Utiliza el script <code>arp_tracer.py</code>. Si el usuario te reporta un problema y solo tienes su IP, este modo rastreará dinámicamente el árbol spanning-tree (CDP/LLDP) desde el Gateway hasta encontrar el switch de acceso y puerto donde el dispositivo está conectado físicamente. Una vez localizado, podrás reconfigurarlo al instante.<br>
                3. <b>Auditoría:</b> Toda inyección de configuración realizada a través de este módulo quedará registrada en el Centro de Seguridad (logs) asociando la acción al usuario que la ejecutó.
            </div>
        </details>

        <!-- ALERTAS GLOBALES -->
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--port-down); color: var(--port-down); padding: 15px; border-radius: 4px; margin-bottom: 25px; font-weight: bold;">
                Error de Ejecución: <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--port-up); color: var(--port-up); padding: 15px; border-radius: 4px; margin-bottom: 25px; font-weight: bold;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- PESTAÑAS (TABS) -->
        <div class="tab-buttons">
            <button class="tab-btn <?php echo $tab_activa === 'manual' ? 'active' : ''; ?>" onclick="openTab(event, 'tab-manual')">Modo Manual (Directo)</button>
            <button class="tab-btn <?php echo $tab_activa === 'auto' ? 'active' : ''; ?>" onclick="openTab(event, 'tab-auto')">Modo Automático (Rastreo Inteligente)</button>
        </div>

        <!-- ============================================== -->
        <!-- MODO MANUAL -->
        <!-- ============================================== -->
        <div id="tab-manual" class="tab-content <?php echo $tab_activa === 'manual' ? 'active' : ''; ?>" style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary);">
            <p style="color: var(--text-muted); margin-bottom: 25px;">Introduce la IP de gestión del switch y el identificador del puerto físico para consultar su estado actual antes de modificar su asignación de VLAN.</p>
            
            <form action="/documentador-red/ejecutar-vlan-info" method="POST" style="display: flex; gap: 15px; align-items: flex-end; margin-bottom: 25px; flex-wrap: wrap;" onsubmit="document.getElementById('loading-info').style.display='inline-flex';">
                <div style="flex: 2; min-width: 200px;">
                    <label style="color: var(--text-muted); font-weight: bold; margin-bottom: 8px; display:block; font-size: 0.85em;">IP del Switch Objetivo:</label>
                    <input type="text" name="switch_ip" class="custom-input" placeholder="Ej. 192.168.10.5" value="<?php echo htmlspecialchars($_POST['switch_ip'] ?? $info['switch_ip'] ?? ''); ?>" required>
                </div>
                <div style="flex: 2; min-width: 200px;">
                    <label style="color: var(--text-muted); font-weight: bold; margin-bottom: 8px; display:block; font-size: 0.85em;">Interfaz (Ej. GigabitEthernet1/0/1):</label>
                    <input type="text" name="port" class="custom-input" placeholder="Ej. Gi1/0/1" value="<?php echo htmlspecialchars($_POST['port'] ?? $info['port'] ?? ''); ?>" required>
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <button type="submit" class="btn-run" style="width: 100%;">Consultar Estado</button>
                </div>
            </form>
            
            <div id="loading-info" style="display:none; color: var(--primary); font-weight:bold; margin-bottom: 20px; padding: 10px; background: rgba(255,255,255,0.05); border: 1px dashed var(--primary); border-radius: 4px;">
                Conectando al switch y extrayendo configuración (SSH)...
            </div>

            <!-- RESULTADO DE CONSULTA MANUAL -->
            <?php if ($info && $tab_activa === 'manual'): ?>
                <div style="border-top: 1px solid var(--border-color); padding-top: 25px;">
                    <h4 style="margin-top: 0; color: white; margin-bottom: 15px;">Estado Actual de la Interfaz</h4>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 25px;">
                        <div class="metric-card"><div class="metric-title">Switch</div><div class="metric-value"><?php echo htmlspecialchars($info['switch_ip']); ?></div></div>
                        <div class="metric-card" style="border-left-color: var(--primary);"><div class="metric-title">Puerto Local</div><div class="metric-value" style="color:var(--primary);"><?php echo htmlspecialchars($info['port']); ?></div></div>
                        <div class="metric-card"><div class="metric-title">VLAN Configurada</div><div class="metric-value"><?php echo htmlspecialchars($info['current_vlan']); ?></div></div>
                    </div>

                    <form action="/documentador-red/ejecutar-vlan-cambio" method="POST" style="background: var(--bg-body); padding: 25px; border-radius: 6px; border: 1px solid var(--border-color);" onsubmit="document.getElementById('loading-set-manual').style.display='inline-flex'; document.getElementById('btn-set-manual').style.display='none';">
                        <input type="hidden" name="csrf_vlan_token" value="<?php echo htmlspecialchars($_SESSION['csrf_vlan_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="tab_origen" value="manual">
                        <input type="hidden" name="switch_ip" value="<?php echo htmlspecialchars($info['switch_ip']); ?>">
                        <input type="hidden" name="port" value="<?php echo htmlspecialchars($info['port']); ?>">
                        
                        <label style="color: var(--text-main); font-weight: bold; display:block; margin-bottom: 12px; font-size: 0.95em;">Asignar Nueva VLAN a este puerto:</label>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <input type="number" name="new_vlan" class="custom-input" placeholder="Ej: 20" style="flex:2; min-width: 150px;" required>
                            <button type="submit" id="btn-set-manual" class="btn-run success" style="flex:1; min-width: 200px;">Aplicar y Reiniciar Puerto</button>
                        </div>
                        <div id="loading-set-manual" style="display:none; color: var(--port-up); font-weight:bold; margin-top: 15px; background: rgba(16, 185, 129, 0.1); padding: 10px; border: 1px dashed var(--port-up); border-radius: 4px;">
                            Inyectando configuración y renegociando enlace...
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============================================== -->
        <!-- MODO AUTOMÁTICO (RASTREO) -->
        <!-- ============================================== -->
        <div id="tab-auto" class="tab-content <?php echo $tab_activa === 'auto' ? 'active' : ''; ?>" style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary);">
            <p style="color: var(--text-muted); margin-bottom: 25px;">Introduce la IP del dispositivo final y el Gateway. El motor rastreará dinámicamente la MAC a través de la topología L2 hasta aislar el puerto de acceso físico.</p>
            
            <form action="/documentador-red/ejecutar-vlan-rastreo" method="POST" style="display: flex; gap: 15px; align-items: flex-end; margin-bottom: 25px; flex-wrap: wrap;" onsubmit="document.getElementById('loading-trace').style.display='inline-flex';">
                <div style="flex: 2; min-width: 200px;">
                    <label style="color: var(--text-muted); font-weight: bold; margin-bottom: 8px; display:block; font-size: 0.85em;">IP Objetivo (PC/Servidor/Host):</label>
                    <input type="text" name="target_ip" class="custom-input" placeholder="Ej: 192.168.99.21" required>
                </div>
                <div style="flex: 2; min-width: 200px;">
                    <label style="color: var(--text-muted); font-weight: bold; margin-bottom: 8px; display:block; font-size: 0.85em;">IP del Gateway (Router L3):</label>
                    <input type="text" name="gateway_ip" class="custom-input" placeholder="Ej: 10.10.0.1" required>
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <button type="submit" class="btn-run" style="width: 100%;">Rastrear Físicamente</button>
                </div>
            </form>
            
            <div id="loading-trace" style="display:none; color: var(--primary); font-weight:bold; margin-bottom: 20px; padding: 10px; background: rgba(255,255,255,0.05); border: 1px dashed var(--primary); border-radius: 4px;">
                Siguiendo el rastro CDP/LLDP a través de la red (Esto puede tomar 1-2 minutos)...
            </div>

            <!-- RESULTADO DEL RASTREO -->
            <?php if ($trace && $tab_activa === 'auto'): ?>
                <div style="border-top: 1px solid var(--border-color); padding-top: 25px;">
                    <h4 style="color: var(--port-up); margin-top: 0; margin-bottom: 15px; font-size: 1.1em;">Dispositivo Localizado con Éxito</h4>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 25px;">
                        <div class="metric-card"><div class="metric-title">Switch de Acceso</div><div class="metric-value"><?php echo htmlspecialchars($trace['switch_ip']); ?></div></div>
                        <div class="metric-card" style="border-left-color: var(--primary);"><div class="metric-title">Puerto Final</div><div class="metric-value" style="color:var(--primary);"><?php echo htmlspecialchars($trace['port']); ?></div></div>
                        <div class="metric-card"><div class="metric-title">VLAN Actual</div><div class="metric-value"><?php echo htmlspecialchars($trace['current_vlan']); ?></div></div>
                        <div class="metric-card"><div class="metric-title">MAC Address Localizada</div><div class="metric-value" style="font-size: 1.1em;"><?php echo htmlspecialchars($trace['mac']); ?></div></div>
                    </div>

                    <form action="/documentador-red/ejecutar-vlan-cambio" method="POST" style="background: var(--bg-body); padding: 25px; border-radius: 6px; border: 1px solid var(--border-color);" onsubmit="document.getElementById('loading-set-auto').style.display='inline-flex'; document.getElementById('btn-set-auto').style.display='none';">
                        <input type="hidden" name="csrf_vlan_token" value="<?php echo htmlspecialchars($_SESSION['csrf_vlan_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="tab_origen" value="auto">
                        <input type="hidden" name="switch_ip" value="<?php echo htmlspecialchars($trace['switch_ip']); ?>">
                        <input type="hidden" name="port" value="<?php echo htmlspecialchars($trace['port']); ?>">
                        
                        <label style="color: var(--text-main); font-weight: bold; display:block; margin-bottom: 12px; font-size: 0.95em;">Inyectar VLAN en el puerto de acceso detectado:</label>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <input type="number" name="new_vlan" class="custom-input" placeholder="Ej: 30" style="flex:2; min-width: 150px;" required>
                            <button type="submit" id="btn-set-auto" class="btn-run success" style="flex:1; min-width: 200px;">Provisionar Automáticamente</button>
                        </div>
                        <div id="loading-set-auto" style="display:none; color: var(--port-up); font-weight:bold; margin-top: 15px; background: rgba(16, 185, 129, 0.1); padding: 10px; border: 1px dashed var(--port-up); border-radius: 4px;">
                            Aplicando configuración en el equipo de acceso...
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
    // Script JS para manejo de pestañas
    function openTab(evt, tabName) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        evt.currentTarget.classList.add('active');
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>