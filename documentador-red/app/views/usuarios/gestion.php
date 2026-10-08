<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.2em;">Gestión de Accesos y Cuentas de Usuario</h2>
            <div class="user-info">
                <?php
                    $roles_nombres = [1 => 'Administrador', 2 => 'Técnico', 3 => 'Lector'];
                    $nombreRol = $roles_nombres[$_SESSION['rol_id']] ?? 'Administrador';
                ?>
                Rol de Sesión: <b style="color: var(--text-main); margin-right: 15px;"><?php echo htmlspecialchars($nombreRol); ?></b>
                <a href="/documentador-red/logout" class="btn-logout" style="text-decoration: none;">Cerrar Sesión</a>
            </div>
        </header>

        <style>
            /* Variables y Clases Estandarizadas OLED */
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

            /* Botones de Acción (Tabla) */
            .btn-action { text-decoration: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85em; transition: opacity 0.2s; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; border: none; white-space: nowrap; height: 32px; box-sizing: border-box;}
            .btn-action:hover { opacity: 0.8; }
            .btn-secondary { background: transparent; color: var(--primary); border: 1px solid var(--primary); }
            .btn-secondary:hover { background: var(--primary); color: #000; }
            .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--port-down); border: 1px solid var(--port-down); }
            .btn-danger:hover { background: var(--port-down); color: #fff; }

            /* Modales Corporativos */
            .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(2px); }
            .modal-box { background: var(--panel-bg); padding: 30px; border-radius: 8px; border-top: 3px solid var(--primary); width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
            .btn-cancelar { background: transparent; color: var(--text-muted); padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; margin-right: 10px; font-weight: bold; transition: 0.2s; }
            .btn-cancelar:hover { background: var(--border-color); color: white; }
            .btn-modal-primary { background: var(--primary); color: #000; padding: 10px 20px; border: none; font-weight: bold; border-radius: 4px; cursor: pointer; transition: 0.2s; }
            .btn-modal-primary:hover { background: var(--primary-hover); }
        </style>

        <main class="workspace" style="display: grid; grid-template-columns: 350px 1fr; gap: 25px; align-items: start;">
            
            <!-- PANEL: CREAR NUEVA CUENTA -->
            <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary);">
                <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px;">Provisionar Nueva Cuenta</h3>
                
                <?php if (isset($_GET['mensaje'])): ?>
                    <?php if ($_GET['mensaje'] == 'creado'): ?>
                        <div style="background: rgba(16, 185, 129, 0.1); color: var(--port-up); border: 1px solid var(--port-up); padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; font-size: 0.9em; text-align: center;">[OK] Usuario creado con éxito.</div>
                    <?php elseif ($_GET['mensaje'] == 'error'): ?>
                        <div style="background: rgba(239, 68, 68, 0.1); color: var(--port-down); border: 1px solid var(--port-down); padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; font-size: 0.9em; text-align: center;">[ERROR] El usuario o correo ya existe.</div>
                    <?php elseif ($_GET['mensaje'] == 'suspendido'): ?>
                        <div style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-muted); padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; font-size: 0.9em; text-align: center;">[INFO] Cuenta suspendida correctamente.</div>
                    <?php elseif ($_GET['mensaje'] == 'autoeliminacion'): ?>
                        <div style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid #f59e0b; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; font-size: 0.9em; text-align: center;">[WARN] No puedes suspender tu propia cuenta.</div>
                    <?php elseif ($_GET['mensaje'] == 'proteccion_admin'): ?>
                        <div style="background: rgba(239, 68, 68, 0.1); color: var(--port-down); border: 1px solid var(--port-down); padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; font-size: 0.9em; text-align: center;">[ERROR] El Superadministrador no puede ser suspendido.</div>
                    <?php elseif ($_GET['mensaje'] == 'pass_actualizada'): ?>
                        <div style="background: rgba(16, 185, 129, 0.1); color: var(--port-up); border: 1px solid var(--port-up); padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; font-size: 0.9em; text-align: center;">[OK] Contraseña actualizada.</div>
                    <?php endif; ?>
                <?php endif; ?>

                <form action="/documentador-red/usuario-guardar" method="POST">
                    
                    <div style="margin-bottom: 15px;">
                        <label class="input-label">Nombre Completo</label>
                        <input type="text" name="nombre_completo" class="custom-input" required placeholder="Ej. Juan Pérez López">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label class="input-label">Correo Corporativo</label>
                        <input type="email" name="correo" class="custom-input" required placeholder="Ej. jperez@empresa.com">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label class="input-label">Nombre de Usuario (Login)</label>
                        <input type="text" name="usuario" class="custom-input" required placeholder="Ej. jperez">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label class="input-label">Contraseña de Acceso</label>
                        <input type="password" name="password" class="custom-input" required placeholder="Asigne una contraseña segura">
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <label class="input-label">Rol y Privilegios</label>
                        <select name="id_rol" class="custom-input" required style="cursor: pointer;">
                            <option value="" disabled selected>-- Seleccione un nivel de acceso --</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id']; ?>">
                                    <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-submit">Registrar Usuario</button>
                </form>
            </div>

            <!-- PANEL: TABLA DE USUARIOS -->
            <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary); overflow-x: auto;">
                <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px;">Directorio de Cuentas Activas</h3>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 5%; text-align: center;">ID</th>
                            <th style="width: 40%;">Identidad Corporativa</th>
                            <th style="width: 15%;">Nivel de Acceso</th>
                            <th style="width: 20%;">Último Inicio de Sesión</th>
                            <th style="width: 20%; text-align: center;">Acciones Operativas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted); font-family: monospace;"><?php echo htmlspecialchars($u['id']); ?></td>
                                
                                <td>
                                    <strong style="color: white; font-size: 1.05em; display: block; margin-bottom: 3px;"><?php echo htmlspecialchars($u['nombre_completo']); ?></strong>
                                    <span style="color: var(--primary); font-family: monospace; font-weight: bold; font-size: 0.9em;">@<?php echo htmlspecialchars($u['usuario']); ?></span>
                                    <span style="color: var(--text-muted); font-size: 0.85em;"> • <?php echo htmlspecialchars($u['correo']); ?></span>
                                </td>

                                <td>
                                    <?php if ($u['nombre_rol'] == 'Administrador'): ?>
                                        <span style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--port-up); color: var(--port-up); padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; text-transform: uppercase;">Admin</span>
                                    <?php elseif ($u['nombre_rol'] == 'Técnico'): ?>
                                        <span style="background: rgba(59, 130, 246, 0.1); border: 1px solid var(--primary); color: var(--primary); padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; text-transform: uppercase;">Técnico</span>
                                    <?php else: ?>
                                        <span style="background: var(--bg-body); border: 1px solid var(--text-muted); color: var(--text-muted); padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; text-transform: uppercase;">Lector</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="font-size: 0.85em; color: var(--text-muted); font-family: monospace;">
                                    <?php echo $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca ha entrado'; ?>
                                </td>
                                
                                <td style="text-align: center;">
                                    <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                                        <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                            <button type="button" onclick="abrirModalPass(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['usuario'], ENT_QUOTES); ?>')" class="btn-action btn-secondary">
                                                Reset Pass
                                            </button>
                                            
                                            <?php if ($u['id'] != 1): // Botón de suspender solo si NO es el Admin maestro ?>
                                            <form action="/documentador-red/usuario-eliminar" method="POST" onsubmit="return confirm('¿Suspender la cuenta de <?php echo htmlspecialchars($u['nombre_completo']); ?>? Perderá acceso inmediato al sistema.');" style="margin: 0; display: inline-flex;">
                                                <input type="hidden" name="id_usuario" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn-action btn-danger">Suspender</button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.85em; font-style: italic;">[ Sesión Actual ]</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </main>
        
        <!-- MODAL CAMBIAR CONTRASEÑA -->
        <div id="modalPass" class="modal-overlay">
            <div class="modal-box">
                <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px;">Forzar Restablecimiento de Contraseña</h3>
                <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 20px; line-height: 1.5;">Usuario seleccionado: <strong id="lbl_usuario" style="color: var(--primary); font-family: monospace; font-size: 1.1em;"></strong></p>
                
                <form action="/documentador-red/usuario-actualizar-pass" method="POST">
                    <input type="hidden" name="id_usuario" id="input_id_usuario">
                    
                    <label class="input-label">Asignar Nueva Contraseña</label>
                    <input type="password" name="nueva_password" class="custom-input" required placeholder="Escriba la nueva credencial...">
                    
                    <div style="text-align: right; margin-top: 25px;">
                        <button type="button" onclick="cerrarModalPass()" class="btn-cancelar">Cancelar</button>
                        <button type="submit" class="btn-modal-primary">Guardar Nueva Pass</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
        function abrirModalPass(id, usuario) {
            document.getElementById('input_id_usuario').value = id;
            document.getElementById('lbl_usuario').innerText = '@' + usuario;
            document.getElementById('modalPass').style.display = 'flex';
        }

        function cerrarModalPass() {
            document.getElementById('modalPass').style.display = 'none';
        }
    </script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>