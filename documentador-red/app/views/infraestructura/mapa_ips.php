<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.2em;">Gestión de Direccionamiento (IPAM)</h2>
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
                        <?php echo htmlspecialchars($nombreRol); ?></b>
                <a href="/documentador-red/vlans" style="margin-left: 15px; color: var(--text-muted); text-decoration: none; font-weight: bold; border-left: 1px solid var(--border-color); padding-left: 15px;">← Volver a Segmentos VLAN</a>
            </div>
        </header>

        <main class="workspace">
            <style>
                .ip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-top: 25px; }
                .ip-box { background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 6px; padding: 15px 10px; text-align: center; font-size: 0.85em; cursor: pointer; transition: all 0.2s ease; position: relative; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .ip-box:hover { transform: translateY(-3px); border-color: var(--text-muted); box-shadow: 0 6px 12px rgba(0,0,0,0.3); z-index: 10; }
                .ip-box .host { font-family: monospace; font-weight: bold; font-size: 1.15em; display: block; margin-bottom: 6px; letter-spacing: 0.5px; }
                .ip-box .label { font-size: 0.9em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; width: 100%; }
                
                /* Colores de estado (OLED) */
                .estado-libre { color: var(--text-muted); }
                .estado-libre .host { color: var(--text-main); }
                
                .estado-estatica { border-color: var(--port-down); background: rgba(239, 68, 68, 0.05); } 
                .estado-estatica .host { color: var(--port-down); }
                .estado-estatica .label { color: var(--text-main); }
                
                .estado-dhcp { border-color: var(--primary); background: rgba(59, 130, 246, 0.05); } 
                .estado-dhcp .host { color: var(--primary); }
                .estado-dhcp .label { color: var(--text-main); }
                
                .estado-gateway { border-color: var(--port-up); background: rgba(16, 185, 129, 0.05); cursor: not-allowed; } 
                .estado-gateway .host { color: var(--port-up); }
                .estado-gateway .label { color: var(--text-main); font-weight: bold; }

                /* Leyenda */
                .leyenda { display: flex; gap: 20px; margin-top: 25px; margin-bottom: 10px; background: var(--bg-body); padding: 15px 20px; border-radius: 6px; font-size: 0.9em; border: 1px solid var(--border-color); font-weight: bold; }
                .leyenda-item { display: flex; align-items: center; gap: 8px; color: var(--text-main); }
                .dot { width: 12px; height: 12px; border-radius: 50%; border: 1px solid var(--border-color); }

                /* Estilos del Modal */
                .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(2px); }
                .modal-box { background: var(--panel-bg); padding: 30px; border-radius: 8px; border-top: 3px solid var(--primary); width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
                .modal-box label { display: block; color: var(--text-muted); font-size: 0.85em; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; margin-top: 15px; }
                .modal-box input, .modal-box select { width: 100%; padding: 10px; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 4px; box-sizing: border-box; font-family: sans-serif; transition: 0.3s; }
                .modal-box input:focus, .modal-box select:focus { border-color: var(--primary); outline: none; }
                .modal-box select option { background-color: var(--bg-body); color: white; padding: 10px; }
                
                .btn-cancelar { background: transparent; color: var(--text-muted); padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; margin-right: 10px; font-weight: bold; transition: 0.2s; }
                .btn-cancelar:hover { background: var(--border-color); color: white; }
                .btn-primary { background: var(--primary); color: #000; padding: 10px 20px; border: none; font-weight: bold; border-radius: 4px; cursor: pointer; transition: 0.2s; }
                .btn-primary:hover { background: var(--primary-hover); }
                .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--port-down); border: 1px solid var(--port-down); padding: 10px 15px; font-weight: bold; border-radius: 4px; cursor: pointer; transition: 0.2s; }
                .btn-danger:hover { background: var(--port-down); color: #fff; }
            </style>

            <div style="background: var(--panel-bg); padding: 30px; border-radius: 8px; border-top: 3px solid var(--primary);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--border-color); padding-bottom: 20px;">
                    <div>
                        <h3 style="margin: 0 0 5px 0; color: white; font-size: 1.3em;">
                            Segmento VLAN <?php echo htmlspecialchars($vlan['numero_vlan']); ?> - <span style="color: var(--primary);"><?php echo htmlspecialchars($vlan['nombre_vlan']); ?></span>
                        </h3>
                        <p style="color: var(--text-muted); margin: 0; font-size: 0.95em;">
                            Rango Asignado: <b style="color: var(--text-main); font-family: monospace;"><?php echo htmlspecialchars($vlan['subred']); ?></b>
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-muted); padding: 8px 15px; border-radius: 4px; font-weight: bold; font-size: 0.9em; display: inline-block;">
                            Capacidad: <span style="color: white;"><?php echo $calculo['total_usables']; ?></span> IPs Utilizables
                        </span>
                    </div>
                </div>

                <div class="leyenda">
                    <div class="leyenda-item"><div class="dot" style="background: var(--bg-body);"></div> Libre (Disponible)</div>
                    <div class="leyenda-item"><div class="dot" style="background: var(--port-down); border-color: var(--port-down);"></div> Uso Fijo / Estático</div>
                    <div class="leyenda-item"><div class="dot" style="background: var(--primary); border-color: var(--primary);"></div> Rango Dinámico (DHCP)</div>
                    <div class="leyenda-item"><div class="dot" style="background: var(--port-up); border-color: var(--port-up);"></div> Enrutador (Gateway L3)</div>
                </div>

                <div class="ip-grid">
                    <?php 
                    // Convertimos las IPs de inicio y fin a números enteros para poder hacer un ciclo FOR
                    $ip_inicio_long = ip2long($calculo['rango_inicio']);
                    $ip_fin_long = ip2long($calculo['rango_fin']);
                    
                    for ($i = $ip_inicio_long; $i <= $ip_fin_long; $i++) {
                        $ip_actual = long2ip($i);
                        $es_gateway = ($ip_actual == $vlan['gateway']);
                        
                        $estado_clase = 'estado-libre';
                        $etiqueta = 'Disponible';
                        $estado_db = 'estatica'; // Valor por defecto para el modal
                        $dispositivo_db = '';
                        $mac_db = '';
                        
                        if ($es_gateway) {
                            $estado_clase = 'estado-gateway';
                            $etiqueta = 'Gateway L3';
                            // El gateway no debe ser clickeable
                            echo "<div class='ip-box {$estado_clase}' title='Gateway Reservado - No Modificable'>";
                        } else {
                            if (isset($ips_ocupadas[$ip_actual])) {
                                $info_ip = $ips_ocupadas[$ip_actual];
                                $estado_clase = 'estado-' . $info_ip['estado'];
                                $etiqueta = htmlspecialchars($info_ip['dispositivo']);
                                $estado_db = $info_ip['estado'];
                                $dispositivo_db = htmlspecialchars($info_ip['dispositivo'], ENT_QUOTES);
                                $mac_db = isset($info_ip['mac_address']) ? htmlspecialchars($info_ip['mac_address'], ENT_QUOTES) : '';
                            }

                            // Añadimos el evento onclick pasándole la IP, el estado y el nombre (Solo roles <= 2 pueden editar)
                            if ($_SESSION['rol_id'] <= 2) {
                                echo "<div class='ip-box {$estado_clase}' title='{$etiqueta}' onclick='abrirModalIp(\"{$ip_actual}\", \"{$estado_db}\", \"{$dispositivo_db}\", \"{$mac_db}\")'>";
                            } else {
                                echo "<div class='ip-box {$estado_clase}' title='{$etiqueta}'>";
                            }
                        }

                        echo "<span class='host'>{$ip_actual}</span>";
                        $etiqueta_corta = mb_strimwidth($etiqueta, 0, 16, "...");
                        echo "<span class='label'>{$etiqueta_corta}</span>";
                        echo "</div>";
                    }
                    ?>
                </div>

            </div>
        </main>
    </div>

    <!-- MODAL DE GESTIÓN IPAM -->
    <?php if ($_SESSION['rol_id'] <= 2): ?>
    <div id="modalIp" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; font-size: 1.1em;">Documentar Asignación IP</h3>
            <form action="/documentador-red/ip-guardar" method="POST">
                <input type="hidden" name="id_vlan" value="<?php echo $vlan['id']; ?>">
                
                <label>Dirección IP Seleccionada</label>
                <input type="text" name="direccion_ip" id="modal_ip_input" readonly style="color: var(--primary); font-weight: bold; background: #000; border-color: #333;">
                
                <label>Modo de Asignación</label>
                <select name="estado" id="modal_estado_input">
                    <option value="estatica">IP Fija / Estática (Servidor, Impresora)</option>
                    <option value="dhcp">Reservada para Rango DHCP</option>
                    <option value="reservada">Bloqueada / Reservada Administrativamente</option>
                </select>
                
                <label>Dispositivo o Uso (Hostname)</label>
                <input type="text" name="dispositivo" id="modal_dispositivo_input" placeholder="Ej. Servidor Base de Datos" required>

                <label>Dirección MAC (Opcional - Para filtrado)</label>
                <input type="text" name="mac_address" id="modal_mac_input" placeholder="Ej. AA:BB:CC:DD:EE:FF" style="font-family: monospace;">

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px;">
                    <button type="submit" name="accion" value="liberar" class="btn-danger" onclick="return confirm('¿Estás seguro de liberar esta IP? Se borrarán sus datos y volverá a estar disponible.');">Liberar IP</button>
                    
                    <div>
                        <button type="button" class="btn-cancelar" onclick="cerrarModalIp()">Cancelar</button>
                        <button type="submit" name="accion" value="guardar" class="btn-primary">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function abrirModalIp(ip, estado, dispositivo, mac='') {
            document.getElementById('modal_ip_input').value = ip;
            document.getElementById('modal_estado_input').value = estado;
            document.getElementById('modal_dispositivo_input').value = dispositivo;
            document.getElementById('modal_mac_input').value = mac;
            document.getElementById('modalIp').style.display = 'flex';
            
            // Poner el foco en el campo de texto automáticamente
            setTimeout(() => document.getElementById('modal_dispositivo_input').focus(), 100);
        }

        function cerrarModalIp() {
            document.getElementById('modalIp').style.display = 'none';
        }
    </script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>