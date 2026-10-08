<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.2em;">Alta de Nuevo Hardware (Aprovisionamiento)</h2>
            <div class="user-info">
                <?php
                    $roles = [1 => 'Administrador', 2 => 'Técnico', 3 => 'Lector'];
                    $nombreRol = $roles[$_SESSION['rol_id']] ?? 'Rol desconocido';
                ?>
                Rol de Sesión: <b style="color: var(--text-main);"><?php echo htmlspecialchars($nombreRol); ?></b>
                <a href="/documentador-red/logout" class="btn-logout" style="margin-left: 15px; text-decoration: none;">Cerrar Sesión</a>
            </div>
        </header>

        <main class="workspace">
            
            <style>
                .custom-input { width: 100%; padding: 12px; background-color: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; outline: none; font-family: sans-serif; font-size: 1em; transition: 0.3s; box-sizing: border-box; }
                .custom-input:focus { border-color: var(--primary); }
                .custom-select { width: 100%; padding: 12px; background-color: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; outline: none; cursor: pointer; transition: 0.3s; box-sizing: border-box; }
                .custom-select:focus { border-color: var(--primary); }
                .input-label { display: block; color: var(--text-muted); font-size: 0.85em; margin-bottom: 6px; font-weight: bold; text-transform: uppercase; }
                
                .btn-submit { background: var(--primary); color: #000; font-weight: bold; font-size: 1.05em; padding: 14px; border: none; border-radius: 4px; cursor: pointer; width: 100%; transition: opacity 0.2s; }
                .btn-submit:hover { background: var(--primary-hover); }
            </style>

            <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] == 'exito'): ?>
                <div style="background: rgba(16, 185, 129, 0.1); color: var(--port-up); padding: 15px; border: 1px solid var(--port-up); border-radius: 4px; margin-bottom: 25px; font-weight: bold; text-align: center;">
                    [OK] El equipo y sus credenciales se han registrado correctamente en la bóveda de inventario.
                </div>
            <?php elseif (isset($_GET['mensaje']) && $_GET['mensaje'] == 'error'): ?>
                <div style="background: rgba(239, 68, 68, 0.1); color: var(--port-down); padding: 15px; border: 1px solid var(--port-down); border-radius: 4px; margin-bottom: 25px; font-weight: bold; text-align: center;">
                    [ERROR] El Hostname ingresado ya existe en la base de datos o hubo un fallo en el registro.
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: center; padding-top: 10px;">
                <div style="background: var(--panel-bg); padding: 35px; border-radius: 8px; width: 100%; max-width: 800px; border-top: 3px solid var(--primary);">
                    
                    <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 25px; font-size: 1.1em;">
                        Especificaciones del Hardware
                    </h3>

                    <form action="/documentador-red/alta-equipo-procesar" method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        
                        <div style="grid-column: span 2;">
                            <label class="input-label">Hostname (Nombre en la Red)</label>
                            <input type="text" name="hostname" class="custom-input" required placeholder="Ej. SW-CORE-01">
                        </div>

                        <div>
                            <label class="input-label">Marca / Fabricante</label>
                            <select name="marca" class="custom-select" required>
                                <option value="Cisco">Cisco Systems</option>
                                <option value="Generico">Otro / Genérico</option>
                            </select>
                        </div>

                        <div>
                            <label class="input-label">Modelo del Equipo</label>
                            <input type="text" name="modelo" class="custom-input" required placeholder="Ej. ISR 4221, Catalyst 2960">
                        </div>

                        <div>
                            <label class="input-label">Rol en Topología (Tipo)</label>
                            <select name="tipo" class="custom-select" required>
                                <option value="Switch">Switch (L2 / L3)</option>
                                <option value="Router">Router / Gateway</option>
                                <option value="Otro">Otro Dispositivo (Ej. Cámara IP)</option>
                            </select>
                        </div>

                        <!-- NUEVO: Selector de Integración -->
                        <div>
                            <label class="input-label">¿Automatizar con Ansible?</label>
                            <select name="usa_ansible" id="usa_ansible" class="custom-select" onchange="toggleAnsible()" required style="border-color: #3b82f6;">
                                <option value="si">Sí (Requiere Plantilla OS)</option>
                                <option value="no">No (Solo documentar / Cámara IP)</option>
                            </select>
                        </div>
       
                        <div id="div_plantilla">
                            <label class="input-label">Plantilla Ansible (OS)</label>
                            <select name="plantilla_conexion" id="plantilla_conexion" class="custom-select">
                                <option value="cisco_ios_legacy">Cisco IOS (Legacy - Paramiko)</option>
                                <option value="cisco_ios_modern">Cisco IOS-XE (Moderno)</option>
                                <option value="ninguna" id="opt_ninguna" style="display:none;">No aplica</option>
                            </select>
                        </div>

                        <div id="div_ip">
                            <label class="input-label">Dirección IP de Gestión</label>
                            <input type="text" name="ip_gestion" class="custom-input" placeholder="Ej. 192.168.10.1" style="font-family: monospace;">
                        </div>

                        <div style="grid-column: span 2;">
                            <label class="input-label">Ubicación Física (Site / Rack)</label>
                            <input type="text" name="ubicacion" class="custom-input" placeholder="Ej. Rack Principal - Site MDF-1">
                        </div>

                        <!-- ========================================== -->
                        <!-- SECCIÓN: CREDENCIALES VAULT (DINÁMICA)     -->
                        <!-- ========================================== -->
                        <div style="grid-column: span 2; margin-top: 15px;">
                            <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px; font-size: 1.1em;">
                                Bóveda de Accesos (Cifrado AES-256)
                            </h3>
                            <p id="vault_desc" style="font-size: 0.85em; color: var(--text-muted); margin-top: 0; margin-bottom: 20px; line-height: 1.5;">
                                Ingrese las credenciales de administración (SSH). Estos datos son cifrados en la base de datos y utilizados por el orquestador de Ansible para tareas de aprovisionamiento.
                            </p>
                        </div>

                        <div>
                            <label class="input-label">Usuario (Login Admin)</label>
                            <input type="text" name="ssh_user" class="custom-input" placeholder="Ej. admin">
                        </div>

                        <div>
                            <label class="input-label">Contraseña de Bóveda</label>
                            <input type="password" name="ssh_pass" class="custom-input" placeholder="••••••••">
                        </div>
                        <!-- ========================================== -->

                        <div style="grid-column: span 2; margin-top: 5px;">
                            <label class="input-label">Notas Adicionales (Comentarios)</label>
                            <textarea name="comentarios" class="custom-input" rows="3" placeholder="Ej. Equipo provisionado temporalmente. El puerto 24 está dañado." style="resize: vertical;"></textarea>
                        </div>

                        <div style="grid-column: span 2; margin-top: 15px;">
                            <button type="submit" class="btn-submit">
                                Guardar Equipo en Inventario Seguro
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleAnsible() {
            var usa = document.getElementById('usa_ansible').value;
            var divPlantilla = document.getElementById('div_plantilla');
            var selPlantilla = document.getElementById('plantilla_conexion');
            var optNinguna = document.getElementById('opt_ninguna');
            var vaultDesc = document.getElementById('vault_desc');

            if (usa === 'no') {
                // Ocultar plantilla y seleccionar "No aplica" por debajo
                divPlantilla.style.display = 'none';
                optNinguna.selected = true;
                
                // Cambiar el texto de la bóveda para reflejar que es solo un respaldo seguro
                vaultDesc.innerHTML = "Ingrese las credenciales de administración (Web/GUI). Al no usar Ansible, estos datos simplemente se almacenarán de forma segura en la bóveda como respaldo consultable.";
            } else {
                // Mostrar plantilla y resetear a Cisco
                divPlantilla.style.display = 'block';
                selPlantilla.options[0].selected = true;

                // Restaurar el texto de Ansible
                vaultDesc.innerHTML = "Ingrese las credenciales de administración (SSH). Estos datos son cifrados en la base de datos y utilizados por el orquestador de Ansible para tareas de aprovisionamiento.";
            }
        }
        
        // Ejecutar al cargar por si el navegador guarda el estado del select
        document.addEventListener('DOMContentLoaded', toggleAnsible);
    </script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>