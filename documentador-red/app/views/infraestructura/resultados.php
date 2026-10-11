<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.2em; display: flex; align-items: center; gap: 10px;">
                Búsqueda Global: <span style="color: var(--primary);">"<?php echo htmlspecialchars($termino); ?>"</span>
            </h2>
            <?php 
                $total = count($resultados['equipos']) + count($resultados['ips']) + count($resultados['puertos']) + count($resultados['vlans']);
            ?>
            <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); padding: 6px 15px; border-radius: 4px; font-size: 0.85em; font-weight: bold; color: var(--text-muted);">
                <?php echo $total; ?> Coincidencias Encontradas
            </div>
        </header>
        
        <main class="workspace">
            
            <style>
                .result-card { background: var(--bg-body); padding: 20px; border-radius: 6px; border: 1px solid var(--border-color); transition: all 0.2s ease; display: flex; flex-direction: column; }
                .result-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.4); border-color: #555555; }
                
                .result-title { font-weight: bold; text-decoration: none; font-size: 1.1em; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color); }
                .result-title:hover { opacity: 0.8; }
                
                .result-info { font-size: 0.85em; color: var(--text-muted); display: flex; flex-direction: column; gap: 6px; }
                .result-info strong { color: var(--text-main); font-weight: 600; width: 85px; display: inline-block; }
                
                .section-title { color: white; margin-top: 0; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; margin-bottom: 20px; font-size: 1.1em; text-transform: uppercase; letter-spacing: 0.5px; }
            </style>

            <?php if ($total === 0): ?>
                <div style="background: var(--panel-bg); padding: 40px; text-align: center; border-radius: 8px; border: 1px dashed var(--border-color);">
                    <h3 style="color: var(--text-muted); margin: 0 0 10px 0;">No se encontraron resultados en el inventario</h3>
                    <p style="color: #666666; margin: 0; font-size: 0.9em;">Intente buscar por dirección IP, dirección MAC, Hostname, Ubicación o la descripción (Destino) de un puerto físico.</p>
                </div>
            <?php else: ?>

                <!-- SECCIÓN 1: EQUIPOS -->
                <?php if (!empty($resultados['equipos'])): ?>
                    <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid var(--primary);">
                        <h3 class="section-title" style="border-bottom-color: var(--primary);">
                            Equipos y Dispositivos de Red (<?php echo count($resultados['equipos']); ?>)
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                            <?php foreach ($resultados['equipos'] as $eq): ?>
                                <div class="result-card">
                                    <a href="/documentador-red/detalles?id=<?php echo $eq['id']; ?>" class="result-title" style="color: var(--primary);">
                                        <?php echo htmlspecialchars($eq['hostname']); ?>
                                    </a>
                                    <div class="result-info">
                                        <span><strong>IP Gestión:</strong> <span style="font-family: monospace; color: white;"><?php echo htmlspecialchars($eq['ip_gestion']); ?></span></span>
                                        <span><strong>Hardware:</strong> <?php echo htmlspecialchars($eq['marca'] . ' ' . $eq['modelo']); ?></span>
                                        <span><strong>Ubicación:</strong> <?php echo htmlspecialchars($eq['ubicacion'] ?: 'No especificada'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SECCIÓN 2: PUERTOS Y CONEXIONES -->
                <?php if (!empty($resultados['puertos'])): ?>
                    <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #f59e0b;">
                        <h3 class="section-title" style="border-bottom-color: #f59e0b;">
                            Interfaces y Conexiones Físicas (<?php echo count($resultados['puertos']); ?>)
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                            <?php foreach ($resultados['puertos'] as $p): ?>
                                <div class="result-card">
                                    <a href="/documentador-red/detalles?id=<?php echo $p['id_equipo']; ?>" class="result-title" style="color: #f59e0b;">
                                        <span style="font-family: monospace;"><?php echo htmlspecialchars($p['nombre_puerto']); ?></span> 
                                        <span style="font-size: 0.8em; color: var(--text-muted); font-weight: normal; margin-left: 5px;">en <?php echo htmlspecialchars($p['hostname']); ?></span>
                                    </a>
                                    <div class="result-info">
                                        <span><strong>Destino:</strong> <span style="color: white;"><?php echo htmlspecialchars($p['destino']); ?></span></span>
                                        <?php if ($p['direccion_ip']): ?>
                                            <span><strong>IP (Capa 3):</strong> <span style="font-family: monospace;"><?php echo htmlspecialchars($p['direccion_ip']); ?></span></span>
                                        <?php endif; ?>
                                        <span><strong>Estado L1:</strong> 
                                            <?php if ($p['estado'] == 'up'): ?>
                                                <span style="color: var(--port-up); font-weight: bold; background: rgba(16, 185, 129, 0.1); padding: 2px 6px; border-radius: 4px; font-size: 0.9em;">UP</span>
                                            <?php else: ?>
                                                <span style="color: var(--port-down); font-weight: bold; background: rgba(239, 68, 68, 0.1); padding: 2px 6px; border-radius: 4px; font-size: 0.9em;">DOWN</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SECCIÓN 3: VLANs -->
                <?php if (!empty($resultados['vlans'])): ?>
                    <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #8b5cf6;">
                        <h3 class="section-title" style="border-bottom-color: #8b5cf6;">
                            Segmentos Lógicos VLAN (<?php echo count($resultados['vlans']); ?>)
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                            <?php foreach ($resultados['vlans'] as $v): ?>
                                <div class="result-card">
                                    <a href="/documentador-red/mapa-ips?id=<?php echo $v['id']; ?>" class="result-title" style="color: #8b5cf6;">
                                        VLAN <?php echo htmlspecialchars($v['numero_vlan']); ?>: <?php echo htmlspecialchars($v['nombre_vlan']); ?>
                                    </a>
                                    <div class="result-info">
                                        <span><strong>Subred:</strong> <span style="font-family: monospace; color: white;"><?php echo htmlspecialchars($v['subred']); ?></span></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SECCIÓN 4: IPs Y MACs -->
                <?php if (!empty($resultados['ips'])): ?>
                    <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid var(--port-up);">
                        <h3 class="section-title" style="border-bottom-color: var(--port-up);">
                            Direcciones IP / Direcciones MAC (<?php echo count($resultados['ips']); ?>)
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                            <?php foreach ($resultados['ips'] as $ip): ?>
                                <div class="result-card">
                                    <div class="result-title" style="color: var(--port-up); font-family: monospace; cursor: default;">
                                        <?php echo htmlspecialchars($ip['direccion_ip']); ?>
                                    </div>
                                    <div class="result-info">
                                        <span><strong>Dir. MAC:</strong> <span style="color: white; font-family: monospace;"><?php echo htmlspecialchars($ip['mac_address'] ?: 'N/D'); ?></span></span>
                                        <span><strong>Asignación:</strong> <span style="color: white;"><?php echo htmlspecialchars($ip['dispositivo']); ?></span></span>
                                        <span><strong>Red Padre:</strong> <a href="/documentador-red/mapa-ips?id=<?php echo $ip['id_vlan']; ?>" style="color: var(--primary); text-decoration: none;">VLAN <?php echo htmlspecialchars($ip['numero_vlan']); ?></a></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>