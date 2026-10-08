<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.2em;">Identificación de Cableado Físico (Patch Cords)</h2>
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
                <a href="/documentador-red/inventario" style="margin-left: 15px; color: var(--text-muted); text-decoration: none; font-weight: bold; border-left: 1px solid var(--border-color); padding-left: 15px;">← Volver al Inventario</a>
            </div>
        </header>

        <main class="workspace">
            <style>
                /* Estilos Corporativos OLED para Acordeones */
                details { background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 15px; overflow: hidden; transition: border-color 0.3s; }
                details[open] { border-color: var(--primary); }
                
                summary { padding: 15px 20px; font-weight: bold; cursor: pointer; list-style: none; display: flex; align-items: center; background: var(--panel-bg); transition: background 0.3s; color: var(--text-main); }
                summary:hover { background: rgba(255, 255, 255, 0.05); }
                summary::-webkit-details-marker { display: none; } /* Ocultar flecha nativa */
                
                /* Flecha animada personalizada */
                summary::before { content: '▶'; display: inline-block; width: 25px; font-size: 0.8em; color: var(--text-muted); transition: transform 0.2s; }
                details[open] > summary::before { transform: rotate(90deg); color: var(--primary); }
                
                /* Acordeón interno (Nivel Equipo) */
                .equipo-details { margin: 15px 20px 15px 45px; border-color: var(--border-color); }
                .equipo-details summary { background: var(--bg-body); font-size: 0.95em; padding: 12px 15px; border-bottom: 1px solid transparent; }
                .equipo-details[open] summary { border-bottom-color: var(--border-color); }
                .equipo-details summary::before { color: var(--text-muted); }
                
                /* Badges (Contadores) */
                .badge-rack { background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-muted); padding: 4px 10px; border-radius: 12px; font-size: 0.85em; margin-left: auto; font-weight: normal; }
                .badge-equipo { background: rgba(59, 130, 246, 0.1); border: 1px solid #3b82f6; color: #3b82f6; padding: 3px 8px; border-radius: 12px; font-size: 0.8em; margin-left: auto; }
                
                /* Tablas Estandarizadas */
                .data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9em; }
                .data-table th { background: var(--bg-body); color: var(--text-muted); padding: 10px 15px; border-bottom: 2px solid var(--border-color); font-weight: bold; }
                .data-table td { padding: 10px 15px; border-bottom: 1px solid var(--border-color); color: var(--text-main); }
                .data-table tr:last-child td { border-bottom: none; }
                .data-table tr:hover td { background: rgba(255,255,255,0.02); }

                /* Botón Acción Secundario */
                .btn-qr { background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); text-decoration: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85em; transition: all 0.2s; display: inline-block; }
                .btn-qr:hover { background: var(--primary); color: #000; border-color: var(--primary); }
            </style>

            <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary);">
                <h3 style="margin-top: 0; color: white;">Visor Global de Conexiones por Ubicación</h3>
                <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 25px;">Despliega un Site/Rack y selecciona el dispositivo de red para generar etiquetas QR individuales por puerto.</p>
                
                <?php 
                // LÓGICA PHP: Agrupar los datos por Rack y luego por Equipo
                $jerarquia = [];
                foreach ($conexiones as $c) {
                    $rack = !empty($c['ubicacion']) ? $c['ubicacion'] : 'Ubicación No Especificada';
                    $equipo = $c['hostname'];
                    $jerarquia[$rack][$equipo][] = $c;
                }
                ?>

                <?php if (empty($jerarquia)): ?>
                    <div style="padding: 30px; text-align: center; color: var(--text-muted); background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 6px;">
                        Aún no se han documentado puertos ni conexiones físicas en el inventario.
                    </div>
                <?php else: ?>
                    
                    <?php foreach ($jerarquia as $nombre_rack => $equipos): ?>
                        
                        <details>
                            <summary>
                                <span style="font-size: 1.05em;">[ SITE ] <?php echo htmlspecialchars($nombre_rack); ?></span>
                                <span class="badge-rack"><?php echo count($equipos); ?> Dispositivo(s)</span>
                            </summary>
                            
                            <?php foreach ($equipos as $nombre_equipo => $puertos): ?>
                                
                                <details class="equipo-details">
                                    <summary>
                                        <span style="color: #3b82f6; font-family: monospace; font-size: 1.1em;"><?php echo htmlspecialchars($nombre_equipo); ?></span>
                                        <span class="badge-equipo"><?php echo count($puertos); ?> Interfaz(ces)</span>
                                    </summary>
                                    
                                    <div style="padding: 15px; overflow-x: auto; background: var(--bg-body);">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 25%;">Interfaz Local</th>
                                                    <th style="width: 50%;">Destino Documentado</th>
                                                    <th style="width: 25%; text-align: right;">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($puertos as $p): ?>
                                                    <tr>
                                                        <td style="font-family: monospace; color: white; font-weight: bold;"><?php echo htmlspecialchars($p['nombre_puerto']); ?></td>
                                                        <td style="color: var(--text-muted);"><?php echo htmlspecialchars($p['destino']); ?></td>
                                                        <td style="text-align: right;">
                                                            <a href="/documentador-red/cableado-etiqueta?id=<?php echo $p['id']; ?>" target="_blank" class="btn-qr">Generar Código QR</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </details>

                            <?php endforeach; ?>
                        </details>

                    <?php endforeach; ?>
                <?php endif; ?>
                
            </div>
        </main>
    </div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>