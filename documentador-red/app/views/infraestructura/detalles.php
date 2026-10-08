<?php
require_once __DIR__ . '/../../core/vault.php';

$usuario_ssh = 'No configurado';
$password_ssh_descifrada = 'No configurada';

if ($_SESSION['rol_id'] <= 2 && !empty($equipo['ssh_password_encrypted'])) {
    $usuario_ssh = $equipo['ssh_user'] ?: 'No configurado';
    $password_ssh_descifrada = vault_decrypt_credential($equipo['ssh_password_encrypted']) ?? 'No disponible';
}
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.2em; display: flex; align-items: center; gap: 15px;">
                Detalles del Dispositivo: <span style="color: var(--primary);"><?php echo htmlspecialchars($equipo['hostname']); ?></span>
                
                <?php if ($_SESSION['rol_id'] <= 2): ?>
                    <button onclick="abrirModalEditarEquipo()" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.75em; font-weight: bold; transition: 0.2s;" title="Editar datos del dispositivo">
                        [ Editar Atributos ]
                    </button>
                <?php endif; ?>
            </h2>
            <div class="user-info">
                <?php
                    $roles = [1 => 'Administrador', 2 => 'Técnico', 3 => 'Lector'];
                    $nombreRol = $roles[$_SESSION['rol_id']] ?? 'Rol desconocido';
                ?>
                Rol de Sesión: <b style="color: var(--text-main);"><?php echo htmlspecialchars($nombreRol); ?></b>
                <a href="/documentador-red/inventario" style="margin-left: 15px; color: var(--text-muted); text-decoration: none; font-weight: bold; border-left: 1px solid var(--border-color); padding-left: 15px;">← Volver al Inventario</a>
            </div>
        </header>

        <main class="workspace">
            <?php if (isset($_SESSION['error_puerto'])): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--port-down); color: var(--port-down); padding: 15px; margin-bottom: 25px; border-radius: 4px; font-weight: bold;">
                    Error: <?php echo htmlspecialchars($_SESSION['error_puerto']); unset($_SESSION['error_puerto']); ?>
                </div>
            <?php endif; ?>

            <style>
                /* Estilos de Modales Corporativos */
                .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(2px); }
                .modal-box { background: var(--panel-bg); padding: 30px; border-radius: 8px; border-top: 3px solid var(--primary); width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); max-height: 90vh; overflow-y: auto;}
                
                .modal-box label { display: block; color: var(--text-muted); font-size: 0.85em; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; margin-top: 15px; }
                .modal-box input, .modal-box select, .modal-box textarea { width: 100%; padding: 10px; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 4px; box-sizing: border-box; font-family: sans-serif; transition: 0.3s; }
                .modal-box input:focus, .modal-box select:focus, .modal-box textarea:focus { border-color: var(--primary); outline: none; }
                .modal-box select option { background-color: var(--bg-body); color: white; padding: 10px; }
                
                .btn-modal-primary { background: var(--primary); color: #000; padding: 10px 20px; border: none; font-weight: bold; border-radius: 4px; cursor: pointer; transition: 0.2s; }
                .btn-modal-primary:hover { background: var(--primary-hover); }
                .btn-cancelar { background: transparent; color: var(--text-muted); padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; margin-right: 10px; font-weight: bold; transition: 0.2s; }
                .btn-cancelar:hover { background: var(--border-color); color: white; }

                /* Scrollbar Modales */
                .modal-box::-webkit-scrollbar { width: 6px; }
                .modal-box::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
                .modal-box::-webkit-scrollbar-thumb { background: #555; border-radius: 4px; }

                /* Tablas Estandarizadas */
                .data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9em; }
                .data-table th { background: var(--bg-body); color: var(--text-muted); padding: 12px 15px; border-bottom: 2px solid var(--border-color); font-weight: bold; }
                .data-table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); color: var(--text-main); }
                .data-table tr:last-child td { border-bottom: none; }
                .data-table tr:hover td { background: rgba(255,255,255,0.02); }

                /* Botones de Acción (Tabla) */
                .btn-secondary { background: transparent; color: var(--text-main); border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 0.85em; font-weight: bold; transition: 0.2s; }
                .btn-secondary:hover { background: var(--border-color); color: white; }

                /* Panel Frontal / Leds */
                .switch-chassis { background: #000; border: 2px solid #333; border-radius: 4px; padding: 15px; margin-bottom: 25px; box-shadow: inset 0 0 10px rgba(0,0,0,0.8); }
                .switch-title { color: var(--text-muted); font-size: 0.75em; text-transform: uppercase; font-weight: bold; margin-bottom: 15px; letter-spacing: 1px; }
                .port-grid { display: flex; flex-wrap: wrap; gap: 4px; }
                .port { width: 35px; height: 35px; background: #111; border: 1px solid #333; border-radius: 3px; display: flex; flex-direction: column; justify-content: center; align-items: center; font-size: 0.7em; color: #666; font-family: monospace; position: relative; cursor: pointer; }
                .port:hover { border-color: #555; color: #fff; }
                .led { width: 8px; height: 4px; border-radius: 2px; margin-bottom: 4px; background: #333; }
                .led.up { background: var(--port-up); box-shadow: 0 0 5px var(--port-up); }
                .led.down { background: var(--port-down); opacity: 0.5; }
            </style>
            
            <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-left: 4px solid var(--primary); margin-bottom: 25px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <span style="color: var(--text-muted); font-size: 0.85em; display: block; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Hardware Base</span>
                    <strong style="font-size: 1.1em;"><?php echo htmlspecialchars($equipo['marca'] . ' ' . $equipo['modelo']); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); font-size: 0.85em; display: block; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">IP de Gestión</span>
                    <strong style="font-size: 1.1em; color: var(--primary); font-family: monospace;"><?php echo htmlspecialchars($equipo['ip_gestion']); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); font-size: 0.85em; display: block; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Ubicación Física</span>
                    <strong style="font-size: 1.1em;"><?php echo htmlspecialchars($equipo['ubicacion']); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); font-size: 0.85em; display: block; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Plantilla Ansible (OS)</span>
                    <strong style="font-size: 1.1em;"><?php echo htmlspecialchars($equipo['plantilla_conexion'] ?? 'No definida'); ?></strong>
                </div>

                <?php if ($_SESSION['rol_id'] <= 2): ?>
                <div>
                    <span style="color: var(--text-muted); font-size: 0.85em; display: block; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Seguridad (Vault)</span>
                    <?php if (!empty($equipo['ssh_password_encrypted'])): ?>
                        <button onclick="abrirModalCredenciales()" style="background: rgba(59,130,246,0.1); color: #3b82f6; border: 1px solid #3b82f6; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.85em; font-weight: bold; transition: 0.2s;">Ver Bóveda</button>
                    <?php else: ?>
                        <span style="font-size: 0.9em; color: var(--border-color); font-style: italic; font-weight: bold;">Sin credenciales</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 15px; margin-top: 5px;">
                    <span style="color: var(--text-muted); font-size: 0.85em; display: block; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Notas / Comentarios</span>
                    <span style="font-size: 0.95em; color: #ccc;"><?php echo htmlspecialchars($equipo['comentarios'] ?: 'Sin comentarios adicionales.'); ?></span>
                </div>
            </div>

            <!-- CHASIS VISUAL -->
            <div class="switch-chassis">
                <div class="switch-title">Representación Lógica del Panel Frontal</div>
                <div class="port-grid">
                    <?php if (empty($puertos)): ?>
                        <p style="color: var(--text-muted); width: 100%; text-align: center; font-style: italic;">El chasis está vacío. Agregue puertos para visualizarlos aquí.</p>
                    <?php else: ?>
                        <?php foreach ($puertos as $puerto): ?>
                            <div class='port' title='<?php echo htmlspecialchars($puerto['nombre_puerto'] . " - " . $puerto['destino']); ?>'>
                                <div class='led <?php echo $puerto['estado'] === 'up' ? 'up' : 'down'; ?>'></div>
                                <?php 
                                    preg_match('/\d+$/', $puerto['nombre_puerto'], $matches);
                                    echo isset($matches[0]) ? $matches[0] : 'P'; 
                                ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TABLA DE PUERTOS -->
            <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; overflow-x: auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                    <h3 style="margin: 0; color: white;">Inventario de Interfaces y Cableado</h3>
                    <?php if ($_SESSION['rol_id'] <= 2): ?>
                        <button onclick="abrirModalAñadir()" style="background: var(--primary); color: #000; border: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; transition: 0.2s;">+ Añadir Interfaz</button>
                    <?php endif; ?>
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Nombre de Interfaz</th>
                            <th style="width: 10%;">Estado L1</th>
                            <th style="width: 10%;">Modo L2</th>
                            <th style="width: 15%;">VLAN / IP L3</th>
                            <th style="width: 20%;">Destino Físico (Patch)</th>
                            <th style="width: 25%; text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($puertos)): ?>
                            <tr><td colspan="6" style="padding: 20px; text-align: center; color: var(--text-muted); font-style: italic;">No hay puertos documentados para este equipo.</td></tr>
                        <?php else: ?>
                            <?php foreach ($puertos as $p): ?>
                                <tr>
                                    <td style="font-family: monospace; color: white; font-weight: bold;"><?php echo htmlspecialchars($p['nombre_puerto']); ?></td>
                                    
                                    <td>
                                        <?php if ($p['estado'] == 'up'): ?>
                                            <span style="background: rgba(16, 185, 129, 0.1); color: var(--port-up); border: 1px solid var(--port-up); padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold;">UP</span>
                                        <?php else: ?>
                                            <span style="background: rgba(239, 68, 68, 0.1); color: var(--port-down); border: 1px solid var(--port-down); padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold;">DOWN</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($equipo['tipo'] === 'Switch'): ?>
                                            <?php if (($p['modo_puerto'] ?? 'Acceso') === 'Troncal'): ?>
                                                <span style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid #f59e0b; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold;">TRUNK</span>
                                            <?php else: ?>
                                                <span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid #3b82f6; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold;">ACCESS</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 1px solid #8b5cf6; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold;">L3 PORT</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="font-family: monospace; color: var(--text-muted);">
                                        <?php echo $equipo['tipo'] === 'Switch' ? 'VLAN '.htmlspecialchars($p['vlan'] ?? '--') : htmlspecialchars($p['direccion_ip'] ?? '--'); ?>
                                    </td>
                                    
                                    <td style="color: var(--text-main);"><?php echo htmlspecialchars($p['destino'] ?: '--'); ?></td>
                                    
                                    <td style="text-align: right; display: flex; justify-content: flex-end; gap: 6px; align-items: center;">
                                        <!-- BOTÓN INTEGRADO DE ETIQUETA QR -->
                                        <a href="/documentador-red/cableado-etiqueta?id=<?php echo $p['id']; ?>" target="_blank" class="btn-secondary" title="Generar etiqueta física para este Patch Cord">Etiqueta</a>
                                        
                                        <?php if ($_SESSION['rol_id'] <= 2): ?>
                                            <a href="#" onclick="abrirModalEditar(<?php echo $p['id']; ?>, '<?php echo $p['nombre_puerto']; ?>', '<?php echo $p['estado']; ?>', '<?php echo $p['modo_puerto'] ?? 'Acceso'; ?>', '<?php echo htmlspecialchars($p['vlan'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['direccion_ip'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['destino'], ENT_QUOTES); ?>')" style="color: #3b82f6; background: rgba(59, 130, 246, 0.1); padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 0.85em; font-weight: bold;">Editar</a>
                                            <a href="#" onclick="confirmarEliminarPuerto(<?php echo $p['id']; ?>, <?php echo $equipo['id']; ?>)" style="color: var(--port-down); background: rgba(239, 68, 68, 0.1); padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 0.85em; font-weight: bold;">Borrar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
        
            <!-- ============================================== -->
            <!-- MODALES OCULTOS -->
            <!-- ============================================== -->

            <!-- MODAL AÑADIR PUERTO -->
            <?php if ($_SESSION['rol_id'] <= 2): ?>
            <div id="modalAñadir" class="modal-overlay">
                <div class="modal-box">
                    <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 5px;">Registrar Nueva Interfaz</h3>
                    <form action="/documentador-red/puerto-guardar" method="POST">
                        <input type="hidden" name="id_equipo" value="<?php echo $equipo['id']; ?>">
                        
                        <label>Identificador del Puerto</label>
                        <input type="text" name="nombre_puerto" required placeholder="Ej. GigabitEthernet0/1">
                        
                        <label>Estado L1</label>
                        <select name="estado">
                            <option value="up">UP (Conectado / Activo)</option>
                            <option value="down">DOWN (Desconectado)</option>
                        </select>

                        <?php if ($equipo['tipo'] === 'Switch'): ?>
                            <label>Modo de Operación L2</label>
                            <select name="modo_puerto">
                                <option value="Acceso">Modo Acceso (VLAN Única)</option>
                                <option value="Troncal">Modo Troncal (Múltiples VLANs)</option>
                            </select>

                            <label>ID de VLAN Asignada</label>
                            <input type="text" name="vlan" placeholder="Ej. 10">
                        <?php else: ?>
                            <input type="hidden" name="modo_puerto" value="Capa 3">
                            <label>Dirección IP / Máscara (L3)</label>
                            <input type="text" name="direccion_ip" placeholder="Ej. 192.168.1.1/24">
                        <?php endif; ?>
                        
                        <label>Destino Físico (Patch Cord)</label>
                        <input type="text" name="destino" placeholder="Ej. Patch Panel A, P 12">
                        
                        <div style="text-align: right; margin-top: 25px;">
                            <button type="button" class="btn-cancelar" onclick="cerrarModales()">Cancelar</button>
                            <button type="submit" class="btn-modal-primary">Guardar Interfaz</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- MODAL EDITAR PUERTO -->
            <?php if ($_SESSION['rol_id'] <= 2): ?>
            <div id="modalEditar" class="modal-overlay">
                <div class="modal-box">
                    <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 5px;">Modificar Interfaz</h3>
                    <form action="/documentador-red/puerto-modificar" method="POST">
                        <input type="hidden" name="id_equipo" value="<?php echo $equipo['id']; ?>">
                        <input type="hidden" name="id_puerto" id="edit_id_puerto">
                        
                        <label>Identificador del Puerto</label>
                        <input type="text" name="nombre_puerto" id="edit_nombre_puerto" required>
                        
                        <label>Estado L1</label>
                        <select name="estado" id="edit_estado">
                            <option value="up">UP (Conectado / Activo)</option>
                            <option value="down">DOWN (Desconectado)</option>
                        </select>

                        <?php if ($equipo['tipo'] === 'Switch'): ?>
                            <label>Modo de Operación L2</label>
                            <select name="modo_puerto" id="edit_modo_puerto">
                                <option value="Acceso">Modo Acceso</option>
                                <option value="Troncal">Modo Troncal</option>
                            </select>

                            <label>ID de VLAN</label>
                            <input type="text" name="vlan" id="edit_vlan">
                        <?php else: ?>
                            <input type="hidden" name="modo_puerto" value="Capa 3">
                            <label>Dirección IP / Máscara (L3)</label>
                            <input type="text" name="direccion_ip" id="edit_direccion_ip" placeholder="Ej. 192.168.1.1/24">
                        <?php endif; ?>
                        
                        <label>Destino Físico (Patch Cord)</label>
                        <input type="text" name="destino" id="edit_destino">
                        
                        <div style="text-align: right; margin-top: 25px;">
                            <button type="button" class="btn-cancelar" onclick="cerrarModales()">Cancelar</button>
                            <button type="submit" class="btn-modal-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- MODAL EDITAR EQUIPO COMPLETO -->
            <?php if ($_SESSION['rol_id'] <= 2): ?>
            <div id="modalEditarEquipo" class="modal-overlay">
                <div class="modal-box" style="width: 600px;">
                    <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 5px;">Modificar Hardware Base</h3>
                    <form action="/documentador-red/equipo-actualizar" method="POST">
                        <input type="hidden" name="id_equipo" value="<?php echo $equipo['id']; ?>">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div style="grid-column: span 2;">
                                <label>Hostname (Nombre en la Red)</label>
                                <input type="text" name="hostname" value="<?php echo htmlspecialchars($equipo['hostname']); ?>" required>
                            </div>
                            
                            <div>
                                <label>Marca / Fabricante</label>
                                <select name="marca">
                                    <option value="Cisco" <?php echo $equipo['marca']=='Cisco' ? 'selected' : ''; ?>>Cisco Systems</option>
                                    <option value="Generico" <?php echo $equipo['marca']=='Generico' ? 'selected' : ''; ?>>Otro / Genérico</option>
                                </select>
                            </div>
                            
                            <div>
                                <label>Modelo del Equipo</label>
                                <input type="text" name="modelo" value="<?php echo htmlspecialchars($equipo['modelo']); ?>" required>
                            </div>

                            <div>
                                <label>Rol en Topología</label>
                                <select name="tipo">
                                    <option value="Switch" <?php echo $equipo['tipo']=='Switch' ? 'selected' : ''; ?>>Switch (L2/L3)</option>
                                    <option value="Router" <?php echo $equipo['tipo']=='Router' ? 'selected' : ''; ?>>Router / Gateway</option>
                                    <option value="Otro" <?php echo $equipo['tipo']=='Otro' ? 'selected' : ''; ?>>Otro Dispositivo</option>
                                </select>
                            </div>

                            <div>
                                <label>Plantilla Ansible (OS)</label>
                                <select name="plantilla_conexion">
                                    <option value="ninguna" <?php echo ($equipo['plantilla_conexion'] ?? '')=='ninguna' ? 'selected' : ''; ?>>No aplica (Sin automatizar)</option>
                                    <option value="cisco_ios_legacy" <?php echo ($equipo['plantilla_conexion'] ?? '')=='cisco_ios_legacy' ? 'selected' : ''; ?>>Cisco IOS (Legacy)</option>
                                    <option value="cisco_ios_modern" <?php echo ($equipo['plantilla_conexion'] ?? '')=='cisco_ios_modern' ? 'selected' : ''; ?>>Cisco IOS-XE (Moderno)</option>
                                </select>
                            </div>
                            
                            <div>
                                <label>Dirección IP de Gestión</label>
                                <input type="text" name="ip_gestion" value="<?php echo htmlspecialchars($equipo['ip_gestion']); ?>" style="font-family: monospace;">
                            </div>
                            
                            <div>
                                <label>Ubicación Física</label>
                                <input type="text" name="ubicacion" value="<?php echo htmlspecialchars($equipo['ubicacion']); ?>">
                            </div>
                            
                            <div style="grid-column: span 2;">
                                <label>Notas Adicionales</label>
                                <textarea name="comentarios" rows="2" style="resize: vertical;"><?php echo htmlspecialchars($equipo['comentarios']); ?></textarea>
                            </div>

                            <!-- ZONA DE BÓVEDA -->
                            <div style="grid-column: span 2; border-top: 1px solid var(--border-color); padding-top: 15px; margin-top: 10px;">
                                <h4 style="margin: 0 0 5px 0; color: #3b82f6;">Bóveda: Actualizar Credenciales (Opcional)</h4>
                                <p style="font-size: 0.85em; color: var(--text-muted); margin-top: 0;">Deja la contraseña en blanco si no deseas alterar la credencial actualmente cifrada.</p>
                            </div>

                            <div>
                                <label>Usuario Admin (SSH)</label>
                                <input type="text" name="ssh_user" value="<?php echo htmlspecialchars($equipo['ssh_user'] ?? ''); ?>" placeholder="Ej. admin">
                            </div>
                            <div>
                                <label>Nueva Contraseña SSH</label>
                                <input type="password" name="ssh_pass" placeholder="••••••••">
                            </div>
                        </div>
                        
                        <div style="text-align: right; margin-top: 25px;">
                            <button type="button" class="btn-cancelar" onclick="cerrarModales()">Cancelar</button>
                            <button type="submit" class="btn-modal-primary">Guardar Cambios Hardware</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- MODAL CREDENCIALES (BÓVEDA) -->
            <?php if ($_SESSION['rol_id'] <= 2): ?>
            <div id="modalCredenciales" class="modal-overlay">
                <div class="modal-box" style="border-top-color: #3b82f6;">
                    <h3 style="margin-top: 0; color: #3b82f6; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 5px;">Extracción de Bóveda (AES-256)</h3>
                    <p style="font-size: 0.85em; color: var(--text-muted); line-height: 1.5; margin-bottom: 20px;">
                        Estos datos son confidenciales. Se muestran en texto plano únicamente porque usted tiene permisos de Administración.
                    </p>

                    <label>Usuario Autorizado</label>
                    <input type="text" value="<?php echo htmlspecialchars($usuario_ssh); ?>" readonly style="background: var(--bg-body); color: #3b82f6; font-weight: bold; cursor: text;">
                    
                    <label>Contraseña Descifrada</label>
                    <input type="text" value="<?php echo htmlspecialchars($password_ssh_descifrada); ?>" readonly style="background: var(--bg-body); color: var(--port-up); font-weight: bold; cursor: text;">
                    
                    <div style="text-align: right; margin-top: 25px;">
                        <button type="button" onclick="cerrarModales()" style="background: var(--bg-body); color: white; padding: 12px 20px; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold; transition: 0.2s;" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='var(--bg-body)'">Cerrar Visor Seguro</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <script>
                function abrirModalAñadir() { document.getElementById('modalAñadir').style.display = 'flex'; }
                function abrirModalEditarEquipo() { document.getElementById('modalEditarEquipo').style.display = 'flex'; }
                function abrirModalCredenciales() { document.getElementById('modalCredenciales').style.display = 'flex'; }

                function abrirModalEditar(id, nombre, estado, modo, vlan, ip, destino) {
                    document.getElementById('edit_id_puerto').value = id;
                    document.getElementById('edit_nombre_puerto').value = nombre;
                    document.getElementById('edit_estado').value = estado;
                    
                    if(document.getElementById('edit_modo_puerto')) document.getElementById('edit_modo_puerto').value = modo;
                    if(document.getElementById('edit_vlan')) document.getElementById('edit_vlan').value = vlan;
                    if(document.getElementById('edit_direccion_ip')) document.getElementById('edit_direccion_ip').value = ip;
                    
                    document.getElementById('edit_destino').value = destino;
                    document.getElementById('modalEditar').style.display = 'flex';
                }

                function cerrarModales() {
                    document.getElementById('modalAñadir').style.display = 'none';
                    document.getElementById('modalEditar').style.display = 'none';
                    if(document.getElementById('modalEditarEquipo')) document.getElementById('modalEditarEquipo').style.display = 'none';
                    if(document.getElementById('modalCredenciales')) document.getElementById('modalCredenciales').style.display = 'none';
                }

                function confirmarEliminarPuerto(idPuerto, idEquipo) {
                    if (confirm('¿Estás seguro de que deseas eliminar la documentación de este puerto? Esta acción no se puede deshacer.')) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '/documentador-red/puerto-eliminar';
                        
                        var inputId = document.createElement('input');
                        inputId.type = 'hidden';
                        inputId.name = 'id_puerto';
                        inputId.value = idPuerto;
                        form.appendChild(inputId);

                        var inputEquipo = document.createElement('input');
                        inputEquipo.type = 'hidden';
                        inputEquipo.name = 'id_equipo';
                        inputEquipo.value = idEquipo;
                        form.appendChild(inputEquipo);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            </script>
    </div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>