<?php require_once __DIR__ . '/../layouts/header.php'; ?>

    <style>
        /* Modificadores para Vista Móvil (Escáner QR) */
        .sidebar { display: none !important; }
        body { display: block; overflow-y: auto; padding: 10px; background: var(--bg-body); margin: 0; }
        .main-content { margin: 0 auto; max-width: 800px; display: block; padding: 0; }
        .topbar { border-radius: 8px 8px 0 0; padding: 15px; }
        
        /* Ajuste para que los puertos quepan bien en móvil */
        .port-grid { grid-template-columns: repeat(auto-fill, minmax(35px, 1fr)) !important; justify-content: center; } 

        /* --- ESTILO PERSONALIZADO DEL SCROLLBAR (OLED) --- */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: #555555; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
        * { scrollbar-width: thin; scrollbar-color: #555555 var(--bg-body); }

        /* Tablas Estandarizadas (Versión Compacta para Móvil) */
        .data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85em; }
        .data-table th { background: var(--bg-body); color: var(--text-muted); padding: 10px; border-bottom: 2px solid var(--border-color); font-weight: bold; }
        .data-table td { padding: 10px; border-bottom: 1px solid var(--border-color); color: var(--text-main); }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(255,255,255,0.02); }
    </style>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.1em; color: var(--primary); font-family: monospace;">
                <?php echo htmlspecialchars($equipo['hostname']); ?>
            </h2>
            <div class="user-info">
                <?php
                    $roles = [1 => 'Administrador', 2 => 'Técnico', 3 => 'Lector'];
                    $nombreRol = $roles[$_SESSION['rol_id']] ?? 'Rol desconocido';
                ?>
                <span style="color: var(--text-muted); font-size: 0.85em; margin-right: 10px;">
                    <b style="color: white;"><?php echo htmlspecialchars($nombreRol); ?></b>
                </span>
                <a href="/documentador-red/logout" class="btn-logout" style="text-decoration: none; padding: 4px 8px; font-size: 0.85em;">Salir</a>
            </div>
        </header>

        <main class="workspace" style="padding: 15px; border-radius: 0 0 8px 8px; border: 1px solid var(--border-color); border-top: none;">
            
            <!-- TARJETA DE INFORMACIÓN BASE -->
            <div style="background: var(--panel-bg); padding: 20px; border-radius: 8px; border-left: 4px solid var(--primary); margin-bottom: 20px; font-size: 0.9em; line-height: 1.6;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="color: var(--text-muted); font-weight: bold;">Modelo:</span>
                    <span style="color: white; text-align: right;"><?php echo htmlspecialchars($equipo['marca'] . ' ' . $equipo['modelo']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="color: var(--text-muted); font-weight: bold;">IP Gestión:</span>
                    <span style="color: var(--primary); font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($equipo['ip_gestion']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="color: var(--text-muted); font-weight: bold;">Ubicación:</span>
                    <span style="color: white; text-align: right;"><?php echo htmlspecialchars($equipo['ubicacion']); ?></span>
                </div>
                <?php if (!empty($equipo['comentarios'])): ?>
                    <div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed var(--border-color);">
                        <span style="color: var(--text-muted); font-weight: bold; display: block; margin-bottom: 3px;">Notas:</span>
                        <span style="color: #cccccc; font-style: italic; font-size: 0.95em;"><?php echo htmlspecialchars($equipo['comentarios']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- CHASIS VISUAL -->
            <div class="switch-chassis" style="margin-bottom: 25px; padding: 15px; background: #000; border: 2px solid #333; border-radius: 6px;">
                <div class="switch-title" style="font-size: 0.75em; color: var(--text-muted); text-transform: uppercase; font-weight: bold; margin-bottom: 15px; letter-spacing: 1px;">Puertos Físicos</div>
                <div class="port-grid">
                    <?php if (empty($puertos)): ?>
                        <div style="width: 100%; text-align: center; color: var(--text-muted); font-style: italic; font-size: 0.85em;">Sin puertos registrados.</div>
                    <?php else: ?>
                        <?php foreach ($puertos as $puerto): ?>
                            <div class='port' style="width: 35px; height: 35px; background: #111; border: 1px solid #333; border-radius: 3px; display: flex; flex-direction: column; justify-content: center; align-items: center; font-size: 0.7em; color: #666; font-family: monospace;" title='<?php echo htmlspecialchars($puerto['destino']); ?>'>
                                <div class='led' style="width: 8px; height: 4px; border-radius: 2px; margin-bottom: 4px; background: <?php echo $puerto['estado'] === 'up' ? 'var(--port-up)' : 'var(--port-down)'; ?>; <?php echo $puerto['estado'] === 'up' ? 'box-shadow: 0 0 5px var(--port-up);' : 'opacity: 0.5;'; ?>"></div>
                                <?php preg_match('/\d+$/', $puerto['nombre_puerto'], $matches); echo isset($matches[0]) ? $matches[0] : 'P'; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TABLA DE CONEXIONES -->
            <div style="background: var(--panel-bg); padding: 15px; border-radius: 8px; overflow-x: auto;">
                <h3 style="margin-top: 0; margin-bottom: 15px; color: white; font-size: 1.05em; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">Conexiones y Cableado</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Interfaz</th>
                            <th style="width: 25%;">Red</th>
                            <th style="width: 45%;">Destino Físico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($puertos)): ?>
                            <tr><td colspan="3" style="text-align: center; color: var(--text-muted); font-style: italic;">No hay conexiones documentadas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($puertos as $p): ?>
                                <tr>
                                    <td style="color: var(--primary); font-family: monospace; font-weight: bold;">
                                        <?php echo htmlspecialchars($p['nombre_puerto']); ?>
                                    </td>
                                    <td style="color: var(--text-muted);">
                                        <?php echo htmlspecialchars($p['vlan'] ?? $p['direccion_ip'] ?? '--'); ?>
                                    </td>
                                    <td style="color: var(--text-main);">
                                        <?php echo htmlspecialchars($p['destino'] ?: '--'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>
</body>
</html>