<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) {
    header("Location: /documentador-red/dashboard");
    exit();
}

require_once __DIR__ . '/../../core/database.php';
$db = Database::conectar();
$stmt = $db->query("SELECT * FROM topologia_enlaces ORDER BY origen ASC");
$enlaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="main-content">
    <header class="topbar">
        <h2 style="margin: 0; font-size: 1.2em;">Descubrimiento de Topología (NAPALM)</h2>
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
            /* Estilos Corporativos OLED */
            details { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 25px; border-left: 4px solid #3b82f6; }
            summary { padding: 15px 20px; font-weight: bold; color: white; cursor: pointer; outline: none; user-select: none; list-style: none; display: flex; justify-content: space-between; align-items: center; }
            summary::-webkit-details-marker { display: none; }
            summary::after { content: '+'; color: #3b82f6; font-size: 1.2em; font-weight: bold; }
            details[open] summary::after { content: '-'; }
            details[open] summary { border-bottom: 1px solid var(--border-color); }
            .details-content { padding: 20px; color: var(--text-muted); font-size: 0.9em; line-height: 1.6; background: var(--bg-body); border-radius: 0 0 6px 6px; }
            .details-content b { color: var(--text-main); }

            .btn-run {
                background: var(--primary); color: #000; border: none; font-weight: bold; 
                font-size: 1em; padding: 12px 30px; border-radius: 4px; cursor: pointer; 
                display: inline-flex; align-items: center; justify-content: center; 
                transition: background 0.2s ease;
            }
            .btn-run:hover { background: var(--primary-hover); }

            .data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85em; }
            .data-table th { background: var(--bg-body); color: var(--text-muted); padding: 12px; border-bottom: 2px solid var(--border-color); font-weight: bold; }
            .data-table td { padding: 12px; border-bottom: 1px solid var(--border-color); color: var(--text-main); }
            .data-table tr:hover td { background: rgba(255,255,255,0.02); }
        </style>

        <!-- DOCUMENTACIÓN EXPANDIBLE -->
        <details>
            <summary>Documentación Técnica: Motor de Descubrimiento (CDP/LLDP)</summary>
            <div class="details-content">
                Esta herramienta utiliza la librería <b>NAPALM</b> (Python) para automatizar el mapeo de la capa física de la red.<br><br>
                1. <b>Proceso:</b> El script se conecta concurrentemente vía SSH a los equipos gestionados en el inventario.<br>
                2. <b>Extracción:</b> Se consultan las tablas de vecinos construidas mediante los protocolos <b>CDP</b> (Cisco Discovery Protocol) o <b>LLDP</b> (Link Layer Discovery Protocol).<br>
                3. <b>Requisitos:</b> Los equipos deben contar con credenciales válidas en la bóveda de NetDocs y tener habilitado el protocolo de descubrimiento correspondiente en sus interfaces.<br>
                4. <b>Impacto:</b> Cada escaneo sobrescribe la tabla de topología de enlaces. Esta acción queda registrada en la bitácora de auditoría.
            </div>
        </details>

        <!-- PANEL DE EJECUCIÓN -->
        <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary); margin-bottom: 25px;">
            <h3 style="margin-top: 0; color: white;">Mapeo Físico Real de la Infraestructura</h3>
            <p style="color: var(--text-muted); margin-bottom: 25px; font-size: 0.9em; line-height: 1.5;">Al iniciar el escaneo, el sistema conectará con los dispositivos para extraer las tablas de vecinos y reconstruir los enlaces físicos (Uplinks/Downlinks) de la red.</p>
            
            <form action="/documentador-red/ejecutar-napalm" method="POST" onsubmit="document.getElementById('loading').style.display='flex'; document.getElementById('btn-container').style.display='none';">
                <div id="btn-container">
                    <button type="submit" class="btn-run">Ejecutar Escáner de Red</button>
                </div>
                
                <div id="loading" style="display: none; color: var(--primary); font-weight: bold; padding: 15px; background: rgba(255, 255, 255, 0.05); border-radius: 4px; border: 1px dashed var(--primary); align-items: center; gap: 15px;">
                    Interrogando equipos y analizando enlaces... Por favor no cierre esta ventana.
                </div>
            </form>
        </div>

        <!-- NOTIFICACIONES Y ERRORES ESTANDARIZADOS -->
        <?php if (isset($_SESSION['napalm_error_fatal'])): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--port-down); color: var(--port-down); padding: 15px; border-radius: 4px; margin-bottom: 25px;">
                <b>Error de Ejecución:</b> <?php echo htmlspecialchars($_SESSION['napalm_error_fatal']); unset($_SESSION['napalm_error_fatal']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['napalm_success'])): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--port-up); color: var(--port-up); padding: 15px; border-radius: 4px; margin-bottom: 25px; font-weight: bold;">
                <?php echo htmlspecialchars($_SESSION['napalm_success']); unset($_SESSION['napalm_success']); ?>
            </div>
            
            <?php if (!empty($_SESSION['napalm_errors'])): ?>
                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; padding: 15px; border-radius: 4px; margin-bottom: 25px;">
                    <h4 style="color: #f59e0b; margin-top: 0; margin-bottom: 10px; font-size: 0.9em; text-transform: uppercase;">Advertencias durante el escaneo:</h4>
                    <ul style="color: #f59e0b; margin: 0; padding-left: 20px; font-size: 0.85em;">
                        <?php foreach ($_SESSION['napalm_errors'] as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php unset($_SESSION['napalm_errors']); ?>
        <?php endif; ?>

        <!-- TABLA DE RESULTADOS -->
        <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid #8b5cf6;">
            <h3 style="color: white; margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">Enlaces Físicos Detectados</h3>
            
            <?php if (empty($enlaces)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 20px;">No existen enlaces en la base de datos actual. Ejecuta el escáner para poblarlos.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Equipo Origen</th>
                                <th style="width: 20%;">Puerto Local</th>
                                <th style="width: 10%; text-align: center;">Dirección</th>
                                <th style="width: 20%;">Equipo Destino</th>
                                <th style="width: 20%;">Puerto Remoto</th>
                                <th style="width: 10%;">Protocolo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enlaces as $enlace): ?>
                                <tr>
                                    <td><strong style="color: white;"><?php echo htmlspecialchars($enlace['origen']); ?></strong></td>
                                    <td style="color: var(--primary); font-family: monospace;"><?php echo htmlspecialchars($enlace['puerto_local']); ?></td>
                                    
                                    <td style="text-align: center; color: #555555; font-weight: bold;">-></td>
                                    
                                    <td><strong style="color: white;"><?php echo htmlspecialchars($enlace['destino']); ?></strong></td>
                                    <td style="color: var(--primary); font-family: monospace;"><?php echo htmlspecialchars($enlace['puerto_remoto']); ?></td>
                                    
                                    <td>
                                        <span style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-muted); padding: 3px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold;">
                                            <?php echo htmlspecialchars($enlace['protocolo']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>