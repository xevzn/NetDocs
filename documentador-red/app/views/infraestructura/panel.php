<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <style>
        /* Estilos limpios para las tarjetas de métricas */
        .metric-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 35px; }
        .card { 
            background: var(--bg-body); 
            border: 1px solid var(--border-color); 
            border-radius: 6px; 
            padding: 24px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            display: flex;
            flex-direction: column;
            border-bottom: 3px solid var(--primary);
        }
        .card h4 { 
            margin: 0 0 10px 0; 
            color: var(--text-muted); 
            font-size: 0.85em; 
            text-transform: uppercase; 
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .card .value { 
            font-size: 2.2em; 
            font-weight: bold; 
            font-family: Consolas, monospace;
            color: var(--text-main); 
            line-height: 1;
        }
        
        /* Títulos de los Racks modernizados */
        .rack-title { 
            color: white; 
            font-size: 1.1em; 
            font-weight: bold; 
            margin-top: 40px; 
            margin-bottom: 20px; 
            padding-bottom: 8px; 
            border-bottom: 1px solid var(--border-color); 
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Ajustes específicos del chasis para el Dashboard */
        .switch-chassis { margin-bottom: 25px; }
        .hostname-link { text-decoration: none; color: white; transition: color 0.2s; font-family: monospace; font-size: 1.1em; }
        .hostname-link:hover { color: var(--primary); }
    </style>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.25em; font-weight: 600; color: white;">Dashboard de Infraestructura</h2>
            <div class="user-info">
                <?php
                    $roles = [
                        1 => 'Administrador',
                        2 => 'Técnico',
                        3 => 'Lector'
                    ];

                    $nombreRol = $roles[$_SESSION['rol_id']] ?? 'Rol desconocido';
                ?>
                Rol de Sesión: <b style="color: var(--text-main); margin-right: 15px;">
                    <?php echo htmlspecialchars($nombreRol); ?>
                </b>
                <a href="/documentador-red/logout" class="btn-logout" style="text-decoration: none;">Cerrar Sesión</a>
            </div>
        </header>

        <main class="workspace">
            
            <div class="metric-cards">
                <div class="card" style="border-bottom-color: #3b82f6;">
                    <h4>Total de Equipos</h4>
                    <div class="value"><?php echo $total_equipos; ?></div>
                </div>
                <div class="card" style="border-bottom-color: var(--port-up);">
                    <h4>Puertos Activos (UP)</h4>
                    <div class="value">
                        <?php echo $puertos_up; ?>
                        <span style="color: var(--port-up); font-size: 0.4em; vertical-align: middle; margin-left: 5px; font-family: sans-serif;">[OK]</span>
                    </div>
                </div>
                <div class="card" style="border-bottom-color: var(--port-down);">
                    <h4>Puertos Libres (DOWN)</h4>
                    <div class="value">
                        <?php echo $puertos_down; ?>
                        <span style="color: var(--port-down); font-size: 0.4em; vertical-align: middle; margin-left: 5px; font-family: sans-serif;">[OFF]</span>
                    </div>
                </div>
            </div>

            <?php if (empty($infraestructura)): ?>
                <div style="background: var(--panel-bg); padding: 40px; text-align: center; border-radius: 8px; border: 1px solid var(--border-color); color: var(--text-muted);">
                    <span style="display: block; font-size: 1.1em; margin-bottom: 15px;">El inventario de hardware está vacío.</span>
                    <a href="/documentador-red/alta-equipo" style="color: var(--primary); text-decoration: none; font-weight: bold; border: 1px solid var(--primary); padding: 10px 20px; border-radius: 4px; display: inline-block;">+ Provisionar Primer Dispositivo</a>
                </div>
            <?php else: ?>
                
                <div style="background: var(--panel-bg); padding: 30px; border-radius: 8px; border-top: 3px solid var(--primary);">
                    <h3 style="margin-top: 0; color: white; margin-bottom: 5px;">Estado Físico Global</h3>
                    <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 10px;">Vista en tiempo real de los paneles frontales de la infraestructura registrada.</p>

                    <?php foreach ($infraestructura as $rack => $equipos): ?>
                        <h3 class="rack-title">[ SITE ] <?php echo htmlspecialchars($rack); ?></h3>
                        
                        <?php foreach ($equipos as $eq): ?>
                            <div class="switch-chassis">
                                <div class="switch-title" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <span style="color: var(--text-muted); font-weight: normal; margin-right: 10px;">
                                            <?php echo htmlspecialchars($eq['marca'] . ' ' . $eq['modelo']); ?> 
                                        </span>
                                        <a href="/documentador-red/detalles?id=<?php echo $eq['id']; ?>" class="hostname-link" title="Ver configuración y conexiones">
                                            <?php echo htmlspecialchars($eq['hostname']); ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 0.9em; font-weight: normal; font-family: monospace; color: var(--primary);">
                                        <?php echo htmlspecialchars($eq['ip_gestion']); ?>
                                    </div>
                                </div>
                                
                                <div class="port-grid">
                                    <?php if (empty($eq['puertos'])): ?>
                                        <div style="width: 100%; text-align: center; color: var(--text-muted); padding: 15px; font-size: 0.85em; font-style: italic;">Sin interfaces documentadas.</div>
                                    <?php else: ?>
                                        <?php foreach ($eq['puertos'] as $puerto): ?>
                                            <a href="/documentador-red/detalles?id=<?php echo $eq['id']; ?>" style="text-decoration: none;">
                                                <div class='port' title='<?php echo htmlspecialchars($puerto['nombre_puerto'] . ($puerto['destino'] ? " -> " . $puerto['destino'] : "")); ?>'>
                                                    <div class='led <?php echo $puerto['estado'] === 'up' ? 'up' : 'down'; ?>'></div>
                                                    <?php 
                                                        preg_match('/\d+$/', $puerto['nombre_puerto'], $matches);
                                                        echo isset($matches[0]) ? $matches[0] : 'P'; 
                                                    ?>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>