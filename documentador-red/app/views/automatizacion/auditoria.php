<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

    <style>
        /* Estilos base estandarizados OLED */
        .radio-input { display: none; }
        
        .radio-label {
            background: var(--bg-body); border: 1px solid var(--border-color); padding: 12px 20px; 
            border-radius: 6px; cursor: pointer; transition: all 0.2s ease; 
            color: var(--text-muted); display: inline-flex; align-items: center; 
            gap: 10px; font-weight: 500; user-select: none; flex-grow: 1; justify-content: center;
        }
        .radio-label:hover { border-color: #555555; color: var(--text-main); }

        /* Color Semántico: Aprovisionamiento (Azul) */
        .radio-input.orq:checked + .radio-label { background: rgba(59, 130, 246, 0.1); border-color: #3b82f6; color: #3b82f6; }
        .radio-input.orq:checked + .radio-label .radio-circle { border-color: #3b82f6; }
        .radio-input.orq:checked + .radio-label .radio-circle::after { background: #3b82f6; }

        /* Color Semántico: Auditoría (Verde) */
        .radio-input.aud:checked + .radio-label { background: rgba(16, 185, 129, 0.1); border-color: var(--port-up); color: var(--port-up); }
        .radio-input.aud:checked + .radio-label .radio-circle { border-color: var(--port-up); }
        .radio-input.aud:checked + .radio-label .radio-circle::after { background: var(--port-up); }

        .radio-circle {
            width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--border-color); 
            display: inline-block; position: relative; transition: all 0.2s ease;
        }
        .radio-circle::after {
            content: ''; position: absolute; top: 50%; left: 50%; 
            transform: translate(-50%, -50%); width: 6px; height: 6px; 
            border-radius: 50%; background: transparent; transition: all 0.2s ease;
        }

        .custom-select {
            width: 100%; padding: 10px; background-color: var(--bg-body); color: var(--text-main); 
            border: 1px solid var(--border-color); border-radius: 4px; outline: none; 
        }
        .custom-select option { background-color: var(--panel-bg); color: var(--text-main); padding: 10px; }
        optgroup { background-color: var(--bg-body); color: var(--text-muted); font-weight: bold; }

        .grid-2-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .grid-2-col { grid-template-columns: 1fr; } }
    </style>

    <div class="main-content">
        <header class="topbar">
            <h2 style="margin: 0; font-size: 1.2em;">Centro de Operaciones Ansible</h2>
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
            
            <div class="grid-2-col">
                <!-- ========================================================
                     PANEL 1: APROVISIONAMIENTO Y RESPALDOS (Azul)
                     ======================================================== -->
                <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid #3b82f6;">
                    <h3 style="margin-top: 0; color: white;">Aprovisionamiento y Respaldos</h3>
                    <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 25px; line-height: 1.5;">
                        Ejecuta el playbook <code style="background: var(--bg-body); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--border-color); color: #3b82f6;">netdocs_orquestador.yml</code> para configurar SNMP y crear respaldos físicos (Backups).
                    </p>

                    <form action="/documentador-red/ejecutar-orquestador" method="POST" onsubmit="document.getElementById('loading-orq').style.display='flex'; document.getElementById('btn-orq-container').style.display='none';">
                        
                        <div style="background: var(--bg-body); padding: 20px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 25px;">
                            <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--text-main); font-size: 0.95em;">Alcance de la Ejecución</h4>
                            
                            <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                                <input type="radio" id="modo_todos_orq" name="modo_ejecucion_orq" value="todos" class="radio-input orq" checked onchange="toggleFiltros('orq')">
                                <label for="modo_todos_orq" class="radio-label">
                                    <span class="radio-circle"></span> Toda la Red
                                </label>
                                
                                <input type="radio" id="modo_grupo_orq" name="modo_ejecucion_orq" value="grupo" class="radio-input orq" onchange="toggleFiltros('orq')">
                                <label for="modo_grupo_orq" class="radio-label">
                                    <span class="radio-circle"></span> Por Plantilla
                                </label>
                                
                                <input type="radio" id="modo_especifico_orq" name="modo_ejecucion_orq" value="especifico" class="radio-input orq" onchange="toggleFiltros('orq')">
                                <label for="modo_especifico_orq" class="radio-label">
                                    <span class="radio-circle"></span> Específico
                                </label>
                            </div>

                            <div id="div-grupo-orq" style="display: none;">
                                <label style="display: block; color: var(--text-muted); font-size: 0.85em; margin-bottom: 6px;">Selecciona Plantilla Operativa:</label>
                                <select name="grupo_objetivo_orq" class="custom-select">
                                    <optgroup label="Cisco Systems">
                                        <option value="cisco_ios_legacy">Cisco IOS (Legacy - Catalyst 2960/3750)</option>
                                        <option value="cisco_ios_modern">Cisco IOS-XE (Moderno - Catalyst 3650/9000)</option>
                                    </optgroup>
                                </select>
                            </div>

                            <div id="div-especifico-orq" style="display: none;">
                                <label style="display: block; color: var(--text-muted); font-size: 0.85em; margin-bottom: 6px;">Selecciona Equipos (Ctrl + Clic para múltiples):</label>
                                <select name="equipos_especificos_orq[]" multiple size="5" class="custom-select">
                                    <?php if(isset($equipos_lista) && is_array($equipos_lista)): ?>
                                        <?php foreach ($equipos_lista as $eq): ?>
                                            <option value="<?php echo htmlspecialchars($eq['hostname']); ?>">
                                                 <?php echo htmlspecialchars($eq['hostname']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay equipos registrados</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div id="btn-orq-container">
                            <button type="submit" style="background: #3b82f6; color: #ffffff; border: none; font-weight: bold; padding: 12px; border-radius: 4px; cursor: pointer; width: 100%; transition: background 0.2s;">
                                Ejecutar Aprovisionamiento
                            </button>
                        </div>
                        
                        <div id="loading-orq" style="display: none; background: rgba(59, 130, 246, 0.1); border: 1px dashed #3b82f6; color: #3b82f6; padding: 12px; border-radius: 4px; font-weight: bold; font-size: 0.9em; text-align: center; justify-content: center;">
                            Conectando vía SSH... No cierre esta ventana.
                        </div>
                    </form>
                </div>

                <!-- ========================================================
                     PANEL 2: AUDITORÍA DE INFRAESTRUCTURA (Verde)
                     ======================================================== -->
                <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--port-up);">
                    <h3 style="margin-top: 0; color: white;">Auditoría Estructural</h3>
                    <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 25px; line-height: 1.5;">
                        Ejecuta el playbook <code style="background: var(--bg-body); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--border-color); color: var(--port-up);">network_info.yml</code> para extraer Puertos, Rutas, VLANs y Seguridad a la BD.
                    </p>

                    <form action="/documentador-red/ejecutar-auditoria" method="POST" onsubmit="document.getElementById('loading-aud').style.display='flex'; document.getElementById('btn-aud-container').style.display='none';">
                        
                        <div style="background: var(--bg-body); padding: 20px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 25px;">
                            <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--text-main); font-size: 0.95em;">Alcance de la Ejecución</h4>
                            
                            <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                                <input type="radio" id="modo_todos_aud" name="modo_ejecucion_aud" value="todos" class="radio-input aud" checked onchange="toggleFiltros('aud')">
                                <label for="modo_todos_aud" class="radio-label">
                                    <span class="radio-circle"></span> Toda la Red
                                </label>
                                
                                <input type="radio" id="modo_grupo_aud" name="modo_ejecucion_aud" value="grupo" class="radio-input aud" onchange="toggleFiltros('aud')">
                                <label for="modo_grupo_aud" class="radio-label">
                                    <span class="radio-circle"></span> Por Plantilla
                                </label>
                                
                                <input type="radio" id="modo_especifico_aud" name="modo_ejecucion_aud" value="especifico" class="radio-input aud" onchange="toggleFiltros('aud')">
                                <label for="modo_especifico_aud" class="radio-label">
                                    <span class="radio-circle"></span> Específico
                                </label>
                            </div>

                            <div id="div-grupo-aud" style="display: none;">
                                <label style="display: block; color: var(--text-muted); font-size: 0.85em; margin-bottom: 6px;">Selecciona Plantilla Operativa:</label>
                                <select name="grupo_objetivo_aud" class="custom-select">
                                    <optgroup label="Cisco Systems">
                                        <option value="cisco_ios_legacy">Cisco IOS (Legacy - Catalyst 2960/3750)</option>
                                        <option value="cisco_ios_modern">Cisco IOS-XE (Moderno - Catalyst 3650/9000)</option>
                                    </optgroup>
                                </select>
                            </div>

                            <div id="div-especifico-aud" style="display: none;">
                                <label style="display: block; color: var(--text-muted); font-size: 0.85em; margin-bottom: 6px;">Selecciona Equipos (Ctrl + Clic para múltiples):</label>
                                <select name="equipos_especificos_aud[]" multiple size="5" class="custom-select">
                                    <?php if(isset($equipos_lista) && is_array($equipos_lista)): ?>
                                        <?php foreach ($equipos_lista as $eq): ?>
                                            <option value="<?php echo htmlspecialchars($eq['hostname']); ?>">
                                                 <?php echo htmlspecialchars($eq['hostname']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay equipos registrados</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div id="btn-aud-container">
                            <button type="submit" style="background: var(--port-up); color: #000; border: none; font-weight: bold; padding: 12px; border-radius: 4px; cursor: pointer; width: 100%; transition: background 0.2s;">
                                Ejecutar Auditoría Profunda
                            </button>
                        </div>
                        
                        <div id="loading-aud" style="display: none; background: rgba(16, 185, 129, 0.1); border: 1px dashed var(--port-up); color: var(--port-up); padding: 12px; border-radius: 4px; font-weight: bold; font-size: 0.9em; text-align: center; justify-content: center;">
                            Auditando y poblando MySQL... No cierre esta ventana.
                        </div>
                    </form>
                </div>
            </div>

            <script>
                // Función unificada que recibe el sufijo del panel ('orq' o 'aud')
                function toggleFiltros(panel) {
                    let modo = document.querySelector('input[name="modo_ejecucion_' + panel + '"]:checked').value;
                    document.getElementById('div-grupo-' + panel).style.display = (modo === 'grupo') ? 'block' : 'none';
                    document.getElementById('div-especifico-' + panel).style.display = (modo === 'especifico') ? 'block' : 'none';
                }
            </script>

            <!-- ZONA DE RESULTADOS (Consola) -->
            <?php if (isset($_SESSION['orquestador_output'])): ?>
                <div style="margin-top: 25px; background: var(--bg-body); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); border-left: 4px solid #3b82f6;">
                    <h4 style="margin-top: 0; color: #3b82f6; font-size: 1.05em; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">Salida del Orquestador (Ansible)</h4>
                    <pre style="color: var(--text-main); font-family: monospace; font-size: 0.85em; white-space: pre-wrap; overflow-x: auto; max-height: 400px; margin-bottom: 0;"><?php echo htmlspecialchars($_SESSION['orquestador_output']); unset($_SESSION['orquestador_output']); ?></pre>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['auditoria_output'])): ?>
                <div style="margin-top: 25px; background: var(--bg-body); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); border-left: 4px solid var(--port-up);">
                    <h4 style="margin-top: 0; color: var(--port-up); font-size: 1.05em; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">Salida de la Auditoría (Ansible)</h4>
                    <pre style="color: var(--text-main); font-family: monospace; font-size: 0.85em; white-space: pre-wrap; overflow-x: auto; max-height: 400px; margin-bottom: 0;"><?php echo htmlspecialchars($_SESSION['auditoria_output']); unset($_SESSION['auditoria_output']); ?></pre>
                </div>
            <?php endif; ?>

        </main>
    </div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>