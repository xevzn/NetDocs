<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.2em;">Segmentación de Red (VLANs e IPAM)</h2>
            <div class="user-info">
                <?php
                    $roles = [
                        1 => 'Administrador',
                        2 => 'Técnico',
                        3 => 'Lector'
                    ];
                    $nombreRol = $roles[$_SESSION['rol_id']] ?? 'Rol desconocido';
                ?>
                Rol de Sesión: <b style="color: var(--text-main);"><?php echo htmlspecialchars($nombreRol); ?></b>
                <a href="/documentador-red/logout" class="btn-logout" style="margin-left: 15px; text-decoration: none;">Cerrar Sesión</a>
            </div>
        </header>

        <style>
            /* Variables estandarizadas OLED */
            .custom-input { width: 100%; padding: 10px; background-color: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; outline: none; font-family: sans-serif; font-size: 0.95em; transition: 0.3s; box-sizing: border-box; }
            .custom-input:focus { border-color: var(--primary); }
            .input-label { display: block; color: var(--text-muted); font-size: 0.85em; margin-bottom: 6px; font-weight: bold; text-transform: uppercase; }
            
            .btn-submit { background: var(--primary); color: #000; font-weight: bold; font-size: 1em; padding: 12px; border: none; border-radius: 4px; cursor: pointer; width: 100%; transition: opacity 0.2s; }
            .btn-submit:hover { background: var(--primary-hover); }

            /* Tablas Estandarizadas */
            .data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9em; }
            .data-table th { background: var(--bg-body); color: var(--text-muted); padding: 12px 15px; border-bottom: 2px solid var(--border-color); font-weight: bold; }
            .data-table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); color: var(--text-main); }
            .data-table tr:last-child td { border-bottom: none; }
            .data-table tr:hover td { background: rgba(255,255,255,0.02); }

            /* Botones de Tabla */
            .btn-action { text-decoration: none; padding: 8px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85em; transition: opacity 0.2s; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; border: none; white-space: nowrap; height: 32px; box-sizing: border-box;}
            .btn-action:hover { opacity: 0.8; }
            .btn-primary { background: var(--primary); color: #000; }
            .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--port-down); border: 1px solid var(--port-down); }
        </style>

        <!-- GRID AJUSTADO: 350px para el formulario, el resto para la tabla -->
        <main class="workspace" style="display: grid; grid-template-columns: 350px 1fr; gap: 25px; align-items: start;">
            
        <?php if ($_SESSION['rol_id'] <= 2): ?>
            <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary);">
                <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px;">Registrar Segmento</h3>
                
                <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] == 'exito'): ?>
                    <div style="background: rgba(16, 185, 129, 0.1); color: var(--port-up); border: 1px solid var(--port-up); padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 0.9em; font-weight: bold; text-align: center;">
                        [OK] Segmento registrado exitosamente.
                    </div>
                <?php elseif (isset($_GET['mensaje']) && $_GET['mensaje'] == 'error'): ?>
                    <div style="background: rgba(239, 68, 68, 0.1); color: var(--port-down); border: 1px solid var(--port-down); padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 0.9em; font-weight: bold; text-align: center;">
                        [ERROR] El ID de VLAN ya existe en la base.
                    </div>
                <?php endif; ?>

                <form action="/documentador-red/vlans-procesar" method="POST">
                    <div style="margin-bottom: 15px;">
                        <label class="input-label">ID de VLAN (802.1Q)</label>
                        <input type="number" name="numero_vlan" class="custom-input" required min="1" max="4094" title="El ID de VLAN debe ser un número entre 1 y 4094" placeholder="Ej. 10">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label class="input-label">Nombre Lógico (Alias)</label>
                        <input type="text" name="nombre_vlan" class="custom-input" required pattern="[a-zA-Z0-9_ -]+" maxlength="50" title="Solo se permiten letras, números, guiones y espacios" placeholder="Ej. Datos_Ventas">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label class="input-label">Dirección de Subred (CIDR)</label>
                        <input type="text" name="subred" class="custom-input" style="font-family: monospace;" required pattern="^([0-9]{1,3}\.){3}[0-9]{1,3}\/([0-9]|[1-2][0-9]|3[0-2])$" title="Debe ser una red con formato CIDR, ej: 192.168.10.0/24" placeholder="Ej. 192.168.10.0/24">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label class="input-label">Puerta de Enlace (Gateway)</label>
                        <input type="text" name="gateway" class="custom-input" style="font-family: monospace;" required pattern="^([0-9]{1,3}\.){3}[0-9]{1,3}$" title="Debe ser una dirección IPv4 válida, ej: 192.168.10.1" placeholder="Ej. 192.168.10.1">
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <label class="input-label">Descripción / Uso</label>
                        <textarea name="descripcion" class="custom-input" rows="2" maxlength="150" placeholder="Ej. Segmento aislado para equipos de IoT" style="resize: vertical;"></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">Guardar Segmento</button>
                </form>
            </div>
        <?php else: ?>
            <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--border-color);">
                <h3 style="margin-top: 0; color: white;">Permisos Restringidos</h3>
                <p style="color: var(--text-muted); font-size: 0.9em; line-height: 1.5;">Tu cuenta tiene un rol de <b>Lector</b>. No tienes privilegios operativos para registrar nuevas VLANs ni modificar los segmentos de red de la infraestructura.</p>
            </div>
        <?php endif; ?>

            <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary); overflow-x: auto;">
                <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px;">Segmentos Lógicos Activos</h3>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Identificador VLAN</th>
                            <th>Esquema de Red (L3)</th>
                            <th>Rango DHCP / Útil</th>
                            <th>Capacidad</th>
                            <th style="text-align: center;">Acciones Operativas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vlans)): ?>
                            <tr><td colspan="5" style="padding: 30px; text-align: center; color: var(--text-muted); font-style: italic;">No hay VLANs registradas en la base de datos.</td></tr>
                        <?php else: ?>
                            <?php foreach ($vlans as $v): ?>
                                <?php 
                                    $calculo = VlanController::calcularCIDR($v['subred']); 
                                ?>
                                <tr>
                                    
                                    <td style="font-weight: bold; color: var(--primary);">
                                        <span style="font-size: 1.1em; display: block; margin-bottom: 3px;">VLAN <?php echo htmlspecialchars($v['numero_vlan']); ?></span>
                                        <span style="font-size: 0.85em; color: var(--text-muted); font-weight: normal;"><?php echo htmlspecialchars($v['nombre_vlan']); ?></span>
                                    </td>
                                    
                                    <td style="font-family: monospace;">
                                        <span style="color: white; font-size: 1.1em; display: block; margin-bottom: 3px;"><?php echo htmlspecialchars($v['subred']); ?></span>
                                        <span style="font-size: 0.9em; color: var(--text-muted);">GW: <?php echo htmlspecialchars($v['gateway']); ?></span>
                                    </td>
                                    
                                    <td style="font-family: monospace; font-size: 0.95em; color: var(--text-main);">
                                        <?php if($calculo): ?>
                                            <span style="display: block; margin-bottom: 2px;"><?php echo $calculo['rango_inicio']; ?></span>
                                            <span><?php echo $calculo['rango_fin']; ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--port-down);">Error de Formato</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if($calculo): ?>
                                            <span style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 4px; color: var(--port-up); font-weight: bold; white-space: nowrap; font-size: 0.9em;">
                                                <?php echo $calculo['total_usables']; ?> Host(s)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                            
                                            <a href="/documentador-red/mapa-ips?id=<?php echo $v['id']; ?>" class="btn-action btn-primary">
                                                Mapa IPAM
                                            </a>
                                            
                                            <?php if ($_SESSION['rol_id'] == 1): ?>
                                                <form action="/documentador-red/vlans-eliminar" method="POST" onsubmit="return confirm('ATENCIÓN: ¿Estás seguro de que deseas eliminar este segmento? Se borrarán todos los registros IP asociados.');" style="margin: 0; display: inline-flex;">
                                                    <input type="hidden" name="id_vlan" value="<?php echo $v['id']; ?>">
                                                    <button type="submit" class="btn-action btn-danger">
                                                        Eliminar
                                                    </button>
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