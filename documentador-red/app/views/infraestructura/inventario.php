<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.2em;">Inventario General de Infraestructura</h2>
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
                /* Tablas Estandarizadas OLED */
                .data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9em; }
                .data-table th { background: var(--bg-body); color: var(--text-muted); padding: 12px 15px; border-bottom: 2px solid var(--border-color); font-weight: bold; }
                .data-table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); color: var(--text-main); }
                .data-table tr:last-child td { border-bottom: none; }
                .data-table tr:hover td { background: rgba(255,255,255,0.02); }

                /* Botones de Acción */
                .btn-action { text-decoration: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85em; transition: opacity 0.2s; display: inline-block; cursor: pointer; border: none; }
                .btn-action:hover { opacity: 0.8; }
                .btn-primary { background: var(--primary); color: #000; }
                .btn-secondary { background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); }
                .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--port-down); border: 1px solid var(--port-down); }
            </style>

            <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary); overflow-x: auto;">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                    <h3 style="margin: 0; color: white;">Equipos Registrados en la Base de Datos</h3>
                        <?php if ($_SESSION['rol_id'] <= 2): ?>
                        <a href="/documentador-red/alta-equipo" class="btn-action btn-primary" style="font-size: 0.95em; padding: 10px 15px;">+ Registrar Nuevo Hardware</a>
                        <?php endif; ?>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Hostname</th>
                            <th>Marca / Modelo</th>
                            <th>Tipo (Rol)</th>
                            <th>IP Gestión</th>
                            <th>Ubicación</th>
                            <th>Comentarios</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($equipos)): ?>
                            <tr>
                                <td colspan="7" style="padding: 40px; text-align: center; color: var(--text-muted);">
                                    <span style="display: block; font-size: 1.1em; margin-bottom: 10px;">La bóveda de inventario está vacía.</span>
                                    <?php if ($_SESSION['rol_id'] <= 2): ?>
                                        <a href="/documentador-red/alta-equipo" style="color: var(--primary); text-decoration: none; font-weight: bold;">Haz clic aquí para provisionar tu primer dispositivo.</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($equipos as $eq): ?>
                                <tr>
                                    <td style="font-weight: bold; color: var(--primary); font-family: monospace; font-size: 1.1em;">
                                        <?php echo htmlspecialchars($eq['hostname']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($eq['marca']) . ' ' . htmlspecialchars($eq['modelo']); ?>
                                    </td>
                                    <td>
                                        <span style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); padding: 3px 8px; border-radius: 4px; font-size: 0.85em; color: var(--text-muted);">
                                            <?php echo htmlspecialchars($eq['tipo']); ?>
                                        </span>
                                    </td>
                                    <td style="font-family: monospace; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($eq['ip_gestion'] ?: '--'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($eq['ubicacion'] ?: '--'); ?>
                                    </td>
                                    <td style="font-size: 0.85em; color: var(--text-muted); max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($eq['comentarios'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($eq['comentarios'] ?: '--'); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; justify-content: center; align-items: center;">
                                            <a href="/documentador-red/detalles?id=<?php echo $eq['id']; ?>" class="btn-action btn-primary">Inspeccionar</a>
                                            <a href="/documentador-red/etiqueta?id=<?php echo $eq['id']; ?>" target="_blank" class="btn-action btn-secondary" title="Generar Etiqueta QR">QR</a>
                                            
                                            <?php if ($_SESSION['rol_id'] == 1): // Solo Administradores pueden borrar equipos ?>
                                                <form action="/documentador-red/equipo-eliminar" method="POST" onsubmit="return confirm('ATENCIÓN: ¿Estás seguro de que deseas eliminar el equipo <?php echo $eq['hostname']; ?> de la bóveda? Se borrarán permanentemente todos sus puertos, credenciales cifradas y enlaces documentados.');" style="margin: 0;">
                                                    <input type="hidden" name="id_equipo" value="<?php echo $eq['id']; ?>">
                                                    <button type="submit" class="btn-action btn-danger">Depurar</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>