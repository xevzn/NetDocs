<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <style>
        .tab-btn { background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); padding: 10px 20px; cursor: pointer; font-weight: bold; border-radius: 4px; transition: 0.3s; }
        .tab-btn.active { background: var(--primary); color: #000; border-color: var(--primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.2em;">Centro de Seguridad y Auditoría</h2>
            <div class="user-info">
                <?php
                    $roles_nombres = [1 => 'Administrador', 2 => 'Técnico', 3 => 'Lector'];
                    $nombreRol = $roles_nombres[$_SESSION['rol_id']] ?? 'Administrador';
                ?>
                Rol de Sesión: <b style="color: var(--port-up); margin-right: 15px;"> <?php echo htmlspecialchars($nombreRol); ?></b>
                <a href="/documentador-red/logout" class="btn-logout" style="text-decoration: none;">Cerrar Sesión</a>
            </div>
        </header>

        <main class="workspace">
            
            <!-- Selector de Pestañas -->
            <div style="margin-bottom: 20px; display: flex; gap: 10px;">
                <button class="tab-btn active" onclick="switchTab('tab-internos', this)"> Eventos Internos (Auditoría)</button>
                <button class="tab-btn" onclick="switchTab('tab-waf', this)"> Firewall WAF (Amenazas Externas)</button>
            </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 1: EVENTOS INTERNOS (La que ya tenías) -->
            <!-- ========================================== -->
            <div id="tab-internos" class="tab-content active">
                <div style="background: var(--panel-bg); padding: 20px; border-radius: 8px; border-left: 4px solid var(--primary); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0 0 5px 0; color: white;">Monitor de Eventos del Sistema</h3>
                        <p style="margin: 0; color: var(--text-muted); font-size: 0.9em;">Registro inmutable de acciones realizadas por usuarios autenticados.</p>
                    </div>
                    <div style="background: var(--bg-body); padding: 10px 15px; border-radius: 4px; border: 1px solid var(--border-color);">
                        <span style="color: var(--text-muted); font-size: 0.85em; display: block;">Total Registros</span>
                        <strong style="color: white; font-size: 1.2em;"><?php echo count($logs); ?> Eventos</strong>
                    </div>
                </div>

                <div style="background: var(--panel-bg); border-radius: 8px; overflow-x: auto; border: 1px solid var(--border-color);">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85em;">
                        <thead>
                            <tr style="background: var(--bg-body); border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 12px; color: var(--text-muted); width: 140px;">Fecha y Hora</th>
                                <th style="padding: 12px; color: var(--text-muted);">Usuario</th>
                                <th style="padding: 12px; color: var(--text-muted);">Módulo</th>
                                <th style="padding: 12px; color: var(--text-muted);">Acción</th>
                                <th style="padding: 12px; color: var(--text-muted);">Detalles Técnicos</th>
                                <th style="padding: 12px; color: var(--text-muted); text-align: right;">IP Origen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="6" style="padding: 20px; text-align: center; color: var(--text-muted);">No hay eventos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr style="border-bottom: 1px solid #1a1a2e;">
                                        <td style="padding: 10px 12px; color: #888; font-family: monospace;"><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></td>
                                        <td style="padding: 10px 12px;">
                                            <strong style="color: white;"><?php echo htmlspecialchars($log['usuario']); ?></strong><br>
                                            <span style="font-size: 0.8em; color: <?php echo $log['nombre_rol'] == 'Administrador' ? 'var(--port-up)' : 'var(--primary)'; ?>;"><?php echo htmlspecialchars($log['nombre_rol']); ?></span>
                                        </td>
                                        <td style="padding: 10px 12px; color: #a1a1aa; font-weight: bold;"><?php echo htmlspecialchars($log['modulo']); ?></td>
                                        <td style="padding: 10px 12px;">
                                            <?php 
                                                $colorAccion = 'white';
                                                if (stripos($log['accion'], 'elimina') !== false || stripos($log['accion'], 'suspend') !== false) $colorAccion = 'var(--port-down)';
                                                if (stripos($log['accion'], 'crea') !== false || stripos($log['accion'], 'agrega') !== false) $colorAccion = 'var(--port-up)';
                                                if (stripos($log['accion'], 'actualiza') !== false || stripos($log['accion'], 'edit') !== false) $colorAccion = '#fbbf24';
                                            ?>
                                            <span style="color: <?php echo $colorAccion; ?>; font-weight: 600;"><?php echo htmlspecialchars($log['accion']); ?></span>
                                        </td>
                                        <td style="padding: 10px 12px; color: var(--text-muted); max-width: 300px; word-wrap: break-word;"><?php echo htmlspecialchars($log['detalles']); ?></td>
                                        <td style="padding: 10px 12px; font-family: monospace; text-align: right; color: #555;"><?php echo htmlspecialchars($log['ip_origen']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 2: FIREWALL WAF (La nueva tabla) -->
            <!-- ========================================== -->
            <div id="tab-waf" class="tab-content">
                <div style="background: var(--panel-bg); padding: 20px; border-radius: 8px; border-left: 4px solid var(--port-down); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0 0 5px 0; color: white;">Alertas de Intrusión (WAF)</h3>
                        <p style="margin: 0; color: var(--text-muted); font-size: 0.9em;">Bloqueos automáticos de escaneos y payloads maliciosos.</p>
                    </div>
                    <div style="background: var(--bg-body); padding: 10px 15px; border-radius: 4px; border: 1px solid var(--port-down);">
                        <span style="color: var(--port-down); font-size: 0.85em; display: block; font-weight: bold;">Amenazas Bloqueadas</span>
                        <strong style="color: white; font-size: 1.2em;"><?php echo count($alertas_waf); ?> Incidentes</strong>
                    </div>
                </div>

                <div style="background: var(--panel-bg); border-radius: 8px; overflow-x: auto; border: 1px solid var(--border-color);">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85em;">
                        <thead>
                            <tr style="background: var(--bg-body); border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 12px; color: var(--text-muted); width: 140px;">Fecha y Hora</th>
                                <th style="padding: 12px; color: var(--text-muted);">IP Atacante</th>
                                <th style="padding: 12px; color: var(--text-muted);">Amenaza Detectada</th>
                                <th style="padding: 12px; color: var(--text-muted);">URI Solicitada</th>
                                <th style="padding: 12px; color: var(--text-muted);">Payload Detonador</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alertas_waf)): ?>
                                <tr><td colspan="5" style="padding: 20px; text-align: center; color: var(--port-up); font-weight: bold;">No se han detectado amenazas recientes. Escudo activo.</td></tr>
                            <?php else: ?>
                                <?php foreach ($alertas_waf as $waf): ?>
                                    <tr style="border-bottom: 1px solid #1a1a2e;">
                                        <td style="padding: 10px 12px; color: #888; font-family: monospace;"><?php echo date('d/m/Y H:i:s', strtotime($waf['fecha'])); ?></td>
                                        
                                        <td style="padding: 10px 12px;">
                                            <span style="background: #3f0000; color: #ff6b6b; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-family: monospace;">
                                                <?php echo htmlspecialchars($waf['ip_origen']); ?>
                                            </span>
                                        </td>
                                        
                                        <td style="padding: 10px 12px; color: var(--port-down); font-weight: bold;">
                                            ⚠️ <?php echo htmlspecialchars($waf['tipo_amenaza']); ?>
                                        </td>
                                        
                                        <td style="padding: 10px 12px; color: var(--primary); font-family: monospace; font-size: 0.9em;">
                                            <?php echo htmlspecialchars($waf['uri_solicitada']); ?>
                                        </td>
                                        
                                        <td style="padding: 10px 12px; color: var(--text-muted); font-family: monospace; max-width: 250px; word-wrap: break-word; background: rgba(255,255,255,0.02);">
                                            <?php echo htmlspecialchars($waf['payload_detectado']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Script para cambiar entre pestañas -->
    <script>
        function switchTab(tabId, element) {
            // Ocultar todos los contenidos
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            // Quitar clase active de todos los botones
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Mostrar la pestaña seleccionada y marcar el botón
            document.getElementById(tabId).classList.add('active');
            element.classList.add('active');
        }
    </script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>