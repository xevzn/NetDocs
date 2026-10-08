<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) {
    header("Location: /documentador-red/dashboard");
    exit();
}

$resultado = $_SESSION['arp_result'] ?? null;
$error_msg = $_SESSION['arp_error'] ?? null;

unset($_SESSION['arp_result']);
unset($_SESSION['arp_error']);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="main-content">
    <header class="topbar">
        <h2 style="margin: 0; font-size: 1.2em;">Buscador de Dispositivos (ARP + CDP Trace)</h2>
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
            .custom-input { width: 100%; padding: 12px; background-color: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; outline: none; font-family: monospace; font-size: 1.05em; transition: 0.3s; }
            .custom-input:focus { border-color: var(--primary); }
            
            .metric-card { background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 6px; padding: 20px; text-align: left; flex: 1; border-left: 4px solid var(--port-up); }
            .metric-title { color: var(--text-muted); font-size: 0.85em; font-weight: bold; margin-bottom: 8px; text-transform: uppercase; }
            .metric-value { color: white; font-size: 1.4em; font-family: Consolas, monospace; font-weight: bold; }
            .metric-value.highlight { color: var(--primary); }
            
            .terminal-log { background: var(--bg-body); border: 1px solid var(--border-color); padding: 20px; border-radius: 6px; font-family: monospace; color: var(--text-muted); font-size: 0.9em; max-height: 300px; overflow-y: auto; line-height: 1.6; }
            
            /* Estilo Corporativo para Documentación Expandible */
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
            <summary>Documentación Técnica: Requisitos de Topología L2/L3</summary>
            <div class="details-content">
                Para que el algoritmo de rastreo en Python logre localizar el puerto físico final, la red debe cumplir con las siguientes condiciones operativas:<br><br>
                1. <b>Punto de Partida (Gateway):</b> La IP de Capa 3 ingresada debe ser el dispositivo (Router o L3 Switch) que enruta la VLAN del dispositivo buscado. Python extraerá la MAC desde la tabla ARP de este equipo.<br>
                2. <b>Protocolo de Vecinos:</b> Todos los switches intermedios en la cascada deben tener habilitado <b>CDP</b> (Cisco Discovery Protocol) o <b>LLDP</b> para permitir el salto de nodo en nodo.<br>
                3. <b>Cadena de Confianza:</b> El sistema NetDocs debe tener almacenadas en la base de datos credenciales SSH válidas para <b>todos</b> los switches en la ruta de rastreo.<br>
                4. <b>Tabla MAC Activa:</b> El dispositivo buscado debe haber transmitido tráfico recientemente (ej. responder a un Ping ICMP) para que los switches mantengan su dirección MAC en sus tablas CAM.
            </div>
        </details>

        <!-- FORMULARIO DE BÚSQUEDA -->
        <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary); margin-bottom: 25px;">
            <h3 style="margin-top: 0; color: white;">Localización Física L2</h3>
            <p style="color: var(--text-muted); margin-bottom: 25px;">Ejecuta el script de rastreo MAC/ARP saltando puerto a puerto desde el Gateway hasta encontrar la ubicación física del dispositivo final.</p>
            
            <form action="/documentador-red/ejecutar-rastreo-arp" method="POST" onsubmit="document.getElementById('loading').style.display='flex'; document.getElementById('btn-run-container').style.display='none';">
                <div style="display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <label style="display: block; color: var(--text-muted); font-weight: bold; margin-bottom: 8px; font-size: 0.85em;">IP del Dispositivo Objetivo:</label>
                        <input type="text" name="target_ip" class="custom-input" placeholder="Ej: 192.168.99.21" required>
                    </div>
                    <div style="flex: 1; min-width: 250px;">
                        <label style="display: block; color: var(--text-muted); font-weight: bold; margin-bottom: 8px; font-size: 0.85em;">IP del Gateway / Core (Capa 3):</label>
                        <input type="text" name="gateway_ip" class="custom-input" placeholder="Ej: 10.10.0.10" required>
                    </div>
                </div>

                <div id="btn-run-container">
                    <button type="submit" style="background: var(--primary); color: #000; border: none; font-weight: bold; padding: 12px 30px; border-radius: 4px; cursor: pointer; transition: 0.2s; font-size: 1em;">
                        Iniciar Algoritmo de Rastreo
                    </button>
                </div>
                
                <div id="loading" style="display: none; color: var(--primary); font-weight: bold; padding: 15px; background: rgba(255, 255, 255, 0.05); border-radius: 4px; border: 1px dashed var(--primary); align-items: center; gap: 15px;">
                    Interrogando tablas ARP y saltando por CDP... Por favor no cierre la ventana.
                </div>
            </form>
        </div>

        <!-- MANEJO DE ERRORES -->
        <?php if ($error_msg): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--port-down); color: var(--port-down); padding: 15px; border-radius: 6px; margin-bottom: 25px;">
                <b>Error de Rastreo:</b> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- RESULTADOS DEL RASTREO -->
        <?php if ($resultado): ?>
            
            <?php if (isset($resultado['success']) && $resultado['success'] === true && isset($resultado['final_location'])): ?>
                <div style="margin-bottom: 25px; background: var(--panel-bg); padding: 25px; border-radius: 8px; border-left: 4px solid var(--port-up);">
                    <h3 style="color: white; margin-top: 0; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">Ubicación Física Confirmada</h3>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px;">
                        <div class="metric-card">
                            <div class="metric-title">Switch Actual</div>
                            <div class="metric-value"><?php echo htmlspecialchars($resultado['final_location']['switch_ip']); ?></div>
                        </div>
                        <div class="metric-card" style="border-left-color: var(--primary);">
                            <div class="metric-title">Puerto Local</div>
                            <div class="metric-value highlight"><?php echo htmlspecialchars($resultado['final_location']['port']); ?></div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-title">VLAN</div>
                            <div class="metric-value"><?php echo htmlspecialchars($resultado['final_location']['current_vlan']); ?></div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-title">MAC Address</div>
                            <div class="metric-value" style="font-size: 1.1em;"><?php echo htmlspecialchars($resultado['final_location']['mac']); ?></div>
                        </div>
                    </div>
                </div>
            <?php elseif (isset($resultado['error'])): ?>
                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; color: #f59e0b; padding: 15px; border-radius: 6px; margin-bottom: 25px;">
                    <b>Rastro Perdido:</b> <?php echo htmlspecialchars($resultado['error']); ?>
                </div>
            <?php endif; ?>

            <!-- CONSOLA DE SALTOS (TRACE LOG) -->
            <?php if (isset($resultado['trace']) && is_array($resultado['trace'])): ?>
                <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px;">
                    <h4 style="margin-top: 0; color: white;">Log de Saltos L2</h4>
                    <div class="terminal-log">
                        <?php foreach ($resultado['trace'] as $log): ?>
                            <div style="margin-bottom: 6px;">
                                <?php 
                                    // Colorear ligeramente ciertas palabras clave
                                    $log_safe = htmlspecialchars($log);
                                    $log_safe = str_replace(['✅', '🎯'], '<span style="color:var(--port-up);">[OK]</span>', $log_safe);
                                    $log_safe = str_replace(['❌', '⚠️'], '<span style="color:var(--port-down);">[WARN]</span>', $log_safe);
                                    $log_safe = str_replace('➡', '<span style="color:var(--primary);">-></span>', $log_safe);
                                    echo $log_safe; 
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>