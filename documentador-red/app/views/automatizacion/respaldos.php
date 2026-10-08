<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
// ==============================================================================
// RESCATE AUTOMÁTICO DE DIRECTORIO (Si el controlador apuntaba a la ruta vieja)
// ==============================================================================
$backup_real_dir = "/var/www/html/documentador-red/automation/backups";

if (empty($equipos) && is_dir($backup_real_dir)) {
    $equipos = [];
    $carpetas = array_diff(scandir($backup_real_dir), ['..', '.']);
    foreach ($carpetas as $carpeta) {
        $ruta_eq = "$backup_real_dir/$carpeta";
        if (is_dir($ruta_eq)) {
            $archivos_raw = array_diff(scandir($ruta_eq), ['..', '.']);
            $archivos = [];
            foreach ($archivos_raw as $arc) {
                if (is_file("$ruta_eq/$arc") && preg_match('/\.(txt|cfg|conf)$/i', $arc)) {
                    $archivos[] = $arc;
                }
            }
            rsort($archivos);
            if (!empty($archivos)) {
                $equipos[$carpeta] = $archivos;
            }
        }
    }
    ksort($equipos);

    $equipo_sel = $_GET['equipo'] ?? (empty($equipos) ? null : array_key_first($equipos));
    $archivos_equipo = ($equipo_sel && isset($equipos[$equipo_sel])) ? $equipos[$equipo_sel] : [];
    $archivo_actual = $_GET['ver_archivo'] ?? ($archivos_equipo[0] ?? null);

    if ($equipo_sel && $archivo_actual && empty($contenido_archivo)) {
        $ruta_lectura = "$backup_real_dir/" . basename($equipo_sel) . "/" . basename($archivo_actual);
        if (is_file($ruta_lectura)) {
            $contenido_archivo = file_get_contents($ruta_lectura);
        }
    }
}

$sel_file_a = $_POST['file_a'] ?? '';
$sel_file_b = $_POST['file_b'] ?? '';
?>

<div class="main-content">
    <header class="topbar">
        <h2 style="margin: 0; font-size: 1.2em;">Gestión de Respaldos de Configuración</h2>
        <div class="user-info">
            <?php
                $roles = [1 => 'Administrador', 2 => 'Técnico', 3 => 'Lector'];
                $nombreRol = $roles[$_SESSION['rol_id'] ?? 0] ?? 'Rol desconocido';
            ?>
            Rol de Sesión: <b style="color: var(--text-main);"><?php echo htmlspecialchars($nombreRol); ?></b>
            <a href="/documentador-red/logout" class="btn-logout" style="margin-left: 15px; text-decoration: none;">Cerrar Sesión</a>
        </div>
    </header>

    <main class="workspace">
        <style>
            /* Estilos base estandarizados OLED */
            .radio-input { display: none; }
            .radio-label { background: var(--bg-body); border: 1px solid var(--border-color); padding: 12px 20px; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; color: var(--text-muted); display: inline-flex; align-items: center; gap: 10px; font-weight: 500; user-select: none; flex-grow: 1; justify-content: center; }
            .radio-label:hover { border-color: #555555; color: var(--text-main); }
            
            .radio-input:checked + .radio-label { background: rgba(59, 130, 246, 0.1); border-color: #3b82f6; color: #3b82f6; }
            .radio-input:checked + .radio-label .radio-circle { border-color: #3b82f6; }
            .radio-input:checked + .radio-label .radio-circle::after { content: ''; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 6px; height: 6px; background: #3b82f6; border-radius: 50%; }
            .radio-circle { width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--border-color); display: inline-block; position: relative; }

            .custom-select { width: 100%; padding: 10px; background-color: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; outline: none; }
            optgroup { background: var(--bg-body); color: var(--text-muted); font-weight: bold; }

            .btn-run { background: var(--primary); color: #000; border: none; font-weight: bold; padding: 12px 25px; border-radius: 4px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 10px; font-size: 1em; width: 100%; }
            .btn-run:hover { background: var(--primary-hover); }
            .btn-action { background: var(--panel-bg); color: var(--text-main); border: 1px solid var(--border-color); padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.2s; text-decoration: none; display: inline-block; }
            .btn-action:hover { background: var(--border-color); color: white; }

            /* Pestañas */
            .tab-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
            .tab-btn { background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); padding: 10px 20px; cursor: pointer; font-weight: bold; border-radius: 4px; transition: 0.3s; }
            .tab-btn.active { background: var(--primary); color: #000; border-color: var(--primary); }
            .tab-content { display: none; }
            .tab-content.active { display: block; animation: fadeIn 0.3s; }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

            /* Acordeón Documentación */
            details { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 25px; border-left: 4px solid #3b82f6; }
            summary { padding: 15px 20px; font-weight: bold; color: white; cursor: pointer; outline: none; user-select: none; list-style: none; display: flex; justify-content: space-between; align-items: center; }
            summary::-webkit-details-marker { display: none; }
            summary::after { content: '+'; color: #3b82f6; font-size: 1.2em; font-weight: bold; }
            details[open] summary::after { content: '-'; }
            details[open] summary { border-bottom: 1px solid var(--border-color); }
            .details-content { padding: 20px; color: var(--text-muted); font-size: 0.9em; line-height: 1.6; background: var(--bg-body); border-radius: 0 0 6px 6px; }
            .details-content b { color: var(--text-main); }
        </style>

        <!-- DOCUMENTACIÓN EXPANDIBLE -->
        <details>
            <summary>Documentación Técnica: Auditoría de Configuraciones y Diff</summary>
            <div class="details-content">
                Módulo diseñado para la prevención de desastres y auditoría de configuraciones de los equipos en producción.<br><br>
                1. <b>Aprovisionamiento:</b> Al generar un respaldo, el sistema conectará vía SSH y descargará el archivo de texto plano con la configuración "Running-Config" al servidor de NetDocs.<br>
                2. <b>Time Machine (Diff):</b> Si sospechas que alguien hizo un cambio no autorizado en la red que causó una falla, utiliza la herramienta de "Comparar". Ésta cruza dos respaldos (del mismo equipo o entre equipos similares) utilizando el comando nativo <code>diff -u</code> de Linux.<br>
                3. <b>Lectura Segura:</b> Las líneas marcadas en verde (<span style="color: #10b981;">+</span>) son adiciones nuevas a la red, y las rojas (<span style="color: #ef4444;">-</span>) son comandos o configuraciones que fueron eliminadas.<br>
                4. <b>Trazabilidad:</b> Cada vez que descargues un archivo físico (<code>.txt</code>) a tu computadora, la acción quedará registrada en el log del sistema bajo tu nombre de usuario.
            </div>
        </details>

        <!-- SECCIÓN 1: FORMULARIO DE GENERACIÓN DE BACKUPS (ACCIONES RÁPIDAS) -->
        <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary); margin-bottom: 25px;">
            <h3 style="margin-top: 0; color: white;">Generar Nuevo Respaldo (Running-Config)</h3>
            <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 25px;">Esta herramienta ejecutará un playbook para conectar a los equipos y extraer su configuración en tiempo real.</p>
            
            <form action="/documentador-red/ejecutar-backup" method="POST" onsubmit="document.getElementById('loading-backup').style.display='flex'; document.getElementById('btn-run-container').style.display='none';">
                
                <div style="background: var(--bg-body); padding: 20px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 25px;">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--text-main); font-size: 0.95em;">Alcance de la Extracción</h4>
                    
                    <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                        <input type="radio" id="modo_todos" name="modo_ejecucion" value="todos" class="radio-input" checked onchange="toggleFiltrosBackup()">
                        <label for="modo_todos" class="radio-label"><span class="radio-circle"></span> Toda la Red</label>
                        
                        <input type="radio" id="modo_grupo" name="modo_ejecucion" value="grupo" class="radio-input" onchange="toggleFiltrosBackup()">
                        <label for="modo_grupo" class="radio-label"><span class="radio-circle"></span> Por Grupo/Plantilla</label>
                        
                        <input type="radio" id="modo_especifico" name="modo_ejecucion" value="especifico" class="radio-input" onchange="toggleFiltrosBackup()">
                        <label for="modo_especifico" class="radio-label"><span class="radio-circle"></span> Equipos Específicos</label>
                    </div>

                    <div id="div-grupo" style="display: none;">
                        <label style="display: block; color: var(--text-muted); font-size: 0.85em; margin-bottom: 6px;">Selecciona el Grupo Objetivo:</label>
                        <select name="grupo_objetivo" class="custom-select">
                            <optgroup label="Por Rol de Dispositivo">
                                <option value="switches">Todos los Switches</option>
                                <option value="routers">Todos los Routers</option>
                                <option value="firewalls">Todos los Firewalls</option>
                            </optgroup>
                            <optgroup label="Por Sistema Operativo">
                                <option value="cisco_ios_legacy">Cisco IOS Legacy (Paramiko)</option>
                                <option value="cisco_ios_modern">Cisco IOS-XE Moderno</option>
                                <option value="huawei_vrp">Huawei VRP</option>
                            </optgroup>
                        </select>
                    </div>

                    <div id="div-especifico" style="display: none;">
                        <label style="display: block; color: var(--text-muted); font-size: 0.85em; margin-bottom: 6px;">Selecciona los Equipos (Ctrl + Clic para selección múltiple):</label>
                        <select name="equipos_especificos[]" multiple size="5" class="custom-select">
                            <?php if (!empty($equipos_lista)): ?>
                                <?php foreach ($equipos_lista as $eq): ?>
                                    <option value="<?php echo htmlspecialchars($eq['hostname']); ?>"> <?php echo htmlspecialchars($eq['hostname']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div id="btn-run-container">
                    <button type="submit" class="btn-run">Iniciar Tarea de Respaldo</button>
                </div>
                
                <div id="loading-backup" style="display: none; background: rgba(59, 130, 246, 0.1); border: 1px dashed #3b82f6; color: #3b82f6; padding: 12px; border-radius: 4px; font-weight: bold; font-size: 0.9em; text-align: center; justify-content: center;">
                    Conectando a dispositivos y descargando configuraciones... Por favor no cierre la ventana.
                </div>
            </form>
        </div>

        <!-- CONSOLA DE SALIDA DEL BACKUP -->
        <?php if (isset($_SESSION['backup_output'])): ?>
            <div style="margin-bottom: 25px; background: var(--bg-body); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); border-left: 4px solid var(--primary);">
                <h4 style="margin-top: 0; color: var(--primary); border-bottom: 1px solid var(--border-color); padding-bottom: 10px; font-size: 1.05em;">Resultado de la Tarea</h4>
                <pre style="color: var(--text-main); font-family: monospace; font-size: 0.85em; white-space: pre-wrap; max-height: 300px; overflow-y: auto; margin-bottom: 0;"><?php echo htmlspecialchars($_SESSION['backup_output']); unset($_SESSION['backup_output'], $_SESSION['backup_status']); ?></pre>
            </div>
        <?php endif; ?>

        <!-- SECCIÓN 2: LECTURA Y COMPARACIÓN DE ARCHIVOS EXISTENTES -->
        <?php if (empty($equipos)): ?>
            <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; color: #f59e0b; padding: 20px; border-radius: 6px; font-weight: bold;">
                No se encontraron respaldos en el sistema de archivos del servidor (<code>/automation/backups</code>). Utiliza la herramienta superior para generar los primeros respaldos.
            </div>
        <?php else: ?>
            
            <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid #8b5cf6;">
                <form method="GET" action="/documentador-red/respaldos" id="deviceForm" style="margin-bottom: 25px;">
                    <label style="color: var(--text-main); font-weight: bold; display: block; margin-bottom: 10px; font-size: 1.1em;">Bóveda: Seleccione el Dispositivo a Auditar</label>
                    <select name="equipo" class="custom-select" style="max-width: 400px; font-weight: bold;" onchange="document.getElementById('deviceForm').submit();">
                        <?php foreach ($equipos as $eq => $archivos): ?>
                            <option value="<?php echo htmlspecialchars($eq); ?>" <?php echo $eq === $equipo_sel ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($eq); ?> [<?php echo count($archivos); ?> archivo(s)]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <div class="tab-buttons">
                    <button class="tab-btn <?php echo empty($diff_html) ? 'active' : ''; ?>" onclick="openTab(event, 'tab-ver')">Ver y Descargar Archivo</button>
                    <button class="tab-btn <?php echo !empty($diff_html) ? 'active' : ''; ?>" onclick="openTab(event, 'tab-comparar')">Analizador de Diferencias (Diff)</button>
                </div>

                <!-- PESTAÑA: VER ARCHIVO -->
                <div id="tab-ver" class="tab-content <?php echo empty($diff_html) ? 'active' : ''; ?>">
                    <form method="GET" action="/documentador-red/respaldos">
                        <input type="hidden" name="equipo" value="<?php echo htmlspecialchars($equipo_sel ?? ''); ?>">
                        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <select name="ver_archivo" class="custom-select" style="max-width: 350px;" onchange="this.form.submit()">
                                <?php foreach ($archivos_equipo as $archivo): ?>
                                    <option value="<?php echo htmlspecialchars($archivo); ?>" <?php echo $archivo === $archivo_actual ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($archivo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($archivo_actual)): ?>
                                <a href="/documentador-red/respaldos?equipo=<?php echo urlencode($equipo_sel); ?>&descargar=<?php echo urlencode($archivo_actual); ?>" class="btn-action">
                                    Descargar Archivo (.txt)
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div style="margin-top: 25px; background: #000; padding: 20px; border-radius: 6px; border: 1px solid #333;">
                        <pre style="color: #cccccc; max-height: 500px; overflow-y: auto; font-size: 0.85em; font-family: monospace; margin: 0;"><?php echo htmlspecialchars($contenido_archivo ?: 'Seleccione un archivo válido para visualizar su configuración.'); ?></pre>
                    </div>
                </div>

                <!-- PESTAÑA: COMPARADOR -->
                <div id="tab-comparar" class="tab-content <?php echo !empty($diff_html) ? 'active' : ''; ?>">
                    <?php if (count($archivos_equipo) < 2): ?>
                        <div style="color: #3b82f6; background: rgba(59, 130, 246, 0.1); padding: 12px 15px; border-radius: 4px; border: 1px solid #3b82f6; margin-bottom: 20px; font-size: 0.9em;">
                            ℹ️ <b>Nota:</b> <b><?php echo htmlspecialchars($equipo_sel); ?></b> tiene actualmente 1 respaldo en su carpeta. Puedes compararlo contra otros respaldos disponibles en la bóveda o generar un segundo respaldo tras aplicar cambios.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/documentador-red/respaldos?equipo=<?php echo urlencode($equipo_sel); ?>">
                        <div style="display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 250px;">
                                <label style="color: var(--text-muted); font-size: 0.85em; font-weight: bold; margin-bottom: 6px; display: block;">Configuración A (Archivo Base / Original):</label>
                                <select name="file_a" class="custom-select">
                                    <optgroup label="Respaldos de <?php echo htmlspecialchars($equipo_sel); ?>">
                                        <?php foreach ($archivos_equipo as $index => $archivo): 
                                            $val_a = $equipo_sel . '/' . $archivo;
                                            $selected = ($sel_file_a === $val_a) || ($sel_file_a === '' && $index == (count($archivos_equipo) > 1 ? 1 : 0));
                                        ?>
                                            <option value="<?php echo htmlspecialchars($val_a); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($archivo); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php foreach ($equipos as $otro_eq => $lista_otros): ?>
                                        <?php if ($otro_eq !== $equipo_sel): ?>
                                            <optgroup label="Comparar con <?php echo htmlspecialchars($otro_eq); ?>">
                                                <?php foreach ($lista_otros as $arc_otro): 
                                                    $val_otro = $otro_eq . '/' . $arc_otro;
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($val_otro); ?>" <?php echo $sel_file_a === $val_otro ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars("$otro_eq / $arc_otro"); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="flex: 1; min-width: 250px;">
                                <label style="color: var(--text-muted); font-size: 0.85em; font-weight: bold; margin-bottom: 6px; display: block;">Configuración B (Archivo Modificado / A Comparar):</label>
                                <select name="file_b" class="custom-select">
                                    <optgroup label="Respaldos de <?php echo htmlspecialchars($equipo_sel); ?>">
                                        <?php foreach ($archivos_equipo as $index => $archivo): 
                                            $val_b = $equipo_sel . '/' . $archivo;
                                            $selected = ($sel_file_b === $val_b) || ($sel_file_b === '' && $index == 0);
                                        ?>
                                            <option value="<?php echo htmlspecialchars($val_b); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($archivo); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php foreach ($equipos as $otro_eq => $lista_otros): ?>
                                        <?php if ($otro_eq !== $equipo_sel): ?>
                                            <optgroup label="Comparar con <?php echo htmlspecialchars($otro_eq); ?>">
                                                <?php foreach ($lista_otros as $arc_otro): 
                                                    $val_otro = $otro_eq . '/' . $arc_otro;
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($val_otro); ?>" <?php echo $sel_file_b === $val_otro ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars("$otro_eq / $arc_otro"); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="comparar" class="btn-action">Ejecutar Análisis Diff</button>
                    </form>

                    <?php if (!empty($diff_html)): ?>
                        <div style="margin-top: 25px;">
                            <h4 style="color: white; margin-bottom: 15px; margin-top: 0; font-size: 1.05em; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">Resultados de la Inspección</h4>
                            <?php echo $diff_html; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    function toggleFiltrosBackup() {
        let modo = document.querySelector('input[name="modo_ejecucion"]:checked').value;
        document.getElementById('div-grupo').style.display = (modo === 'grupo') ? 'block' : 'none';
        document.getElementById('div-especifico').style.display = (modo === 'especifico') ? 'block' : 'none';
    }
    
    function openTab(evt, tabName) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        evt.currentTarget.classList.add('active');
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
