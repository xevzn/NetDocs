<style>
    /* --- 1. CORRECCIÓN DEL TRASLAPE --- */
    .main-content { 
        margin-left: 250px !important; 
        width: calc(100% - 250px) !important;
        min-height: 100vh;
        box-sizing: border-box;
    }

    /* --- 2. ESTILOS BASE DEL SIDEBAR --- */
    .sidebar { width: 250px; background: var(--panel-bg); height: 100vh; position: fixed; top: 0; left: 0; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; z-index: 1000; }
    .sidebar .logo { padding: 24px 20px; color: var(--primary); font-size: 1.4em; font-weight: 700; text-align: center; border-bottom: 1px solid var(--border-color); margin: 0; margin-bottom: 15px; letter-spacing: 0.5px; }
    .sidebar-menu { list-style: none; padding: 0; margin: 0; overflow-y: auto; flex-grow: 1; }
    
    /* Buscador modernizado */
    .sidebar form input { width: 100%; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-main); box-sizing: border-box; font-family: 'Inter', sans-serif; font-size: 0.85em; transition: all 0.2s ease; }
    .sidebar form input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
    .sidebar form input::placeholder { color: var(--text-muted); }

    /* --- 3. ESTILOS DE LOS TÍTULOS DE SECCIÓN --- */
    .menu-toggle {
        display: block; color: var(--text-muted); font-size: 0.75em; text-transform: uppercase;
        letter-spacing: 1px; font-weight: 600; background: rgba(0, 0, 0, 0.2); 
        border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);
        padding: 16px 20px; user-select: none;
    }
    
    /* --- 4. ESTILOS DE LOS ENLACES INTERNOS --- */
    .sub-menu { list-style: none; padding: 0; margin: 0; display: block; } 
    .sub-menu li a { display: block; padding: 12px 20px 12px 35px; color: var(--text-muted); text-decoration: none; transition: all 0.2s ease; border-left: 3px solid transparent; font-size: 0.9em; font-weight: 500; }
    .sub-menu li a:hover { background: rgba(255, 255, 255, 0.03); color: var(--text-main); border-left: 3px solid var(--primary); }
    
    /* --- 5. BOTÓN DE AYUDA (FOOTER DEL SIDEBAR) --- */
    .sidebar-footer {
        margin-top: auto;
        padding: 15px 20px;
        border-top: 1px solid var(--border-color);
        background: rgba(59, 130, 246, 0.05);
    }
    .help-btn {
        display: flex; align-items: center; gap: 10px; color: var(--primary); 
        text-decoration: none; font-weight: 600; font-size: 0.9em; 
        transition: all 0.2s ease; padding: 10px; border-radius: 6px; cursor: pointer;
    }
    .help-btn:hover { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
    .help-btn-icon { background: var(--primary); color: #000; border-radius: 50%; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8em; font-weight: bold; }

    /* --- 6. SCROLLBAR MODERNO (Sidebar) --- */
    .sidebar-menu::-webkit-scrollbar { width: 6px; }
    .sidebar-menu::-webkit-scrollbar-track { background: transparent; }
    .sidebar-menu::-webkit-scrollbar-thumb { background: #3f3f5a; border-radius: 10px; }
    .sidebar-menu::-webkit-scrollbar-thumb:hover { background: var(--primary); }

    /* =======================================================
       ESTILOS DEL MODAL DE AYUDA GLOBAL (MANUAL OLED)
       ======================================================= */
    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(4px);
        z-index: 9999; justify-content: center; align-items: center;
        animation: fadeInModal 0.2s ease-in-out;
    }
    
    .modal-box {
        background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 12px;
        width: 95%; max-width: 900px; max-height: 85vh; display: flex; flex-direction: column;
        box-shadow: 0 20px 50px rgba(0,0,0,0.8); color: var(--text-main); font-family: 'Inter', sans-serif;
    }

    .modal-header {
        background: var(--bg-body); padding: 20px 25px; border-bottom: 1px solid var(--border-color);
        display: flex; justify-content: space-between; align-items: center;
        border-radius: 12px 12px 0 0;
    }
    .modal-header h3 { margin: 0; color: var(--primary); font-size: 1.3em; font-weight: 600; letter-spacing: 0.5px; }
    .close-modal { background: none; border: none; color: var(--text-muted); font-size: 2em; cursor: pointer; transition: color 0.2s; line-height: 1; padding: 0; }
    .close-modal:hover { color: var(--port-down); }

    .modal-body { padding: 30px; overflow-y: auto; line-height: 1.6; }
    
    .manual-intro { font-size: 1.05em; color: var(--text-muted); margin-bottom: 25px; }
    .manual-section {
        background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 8px;
        padding: 20px; margin-bottom: 20px;
    }
    .manual-section h4 { 
        color: white; margin-top: 0; font-size: 1.1em;
        border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 15px;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    .manual-step { margin-bottom: 15px; font-size: 0.95em; color: #cccccc; }
    .manual-step strong { color: var(--primary); }
    
    .note-box { background: rgba(59, 130, 246, 0.1); border-left: 4px solid var(--primary); padding: 12px 15px; border-radius: 4px; margin-top: 15px; font-size: 0.9em; color: #a5c6ff; }
    .warn-box { background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; padding: 12px 15px; border-radius: 4px; margin-top: 15px; font-size: 0.9em; color: #fcd34d; }
    .success-box { background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--port-up); padding: 12px 15px; border-radius: 4px; margin-top: 15px; font-size: 0.9em; color: #6ee7b7; }

    .modal-body::-webkit-scrollbar { width: 6px; }
    .modal-body::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
    .modal-body::-webkit-scrollbar-thumb { background: #555555; border-radius: 4px; }
    .modal-body::-webkit-scrollbar-thumb:hover { background: var(--primary); }
    
    @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
</style>

<div class="sidebar">
    <div style="display: flex; align-items: center; justify-content: center; padding: 20px 0; gap: 10px;">
        <img src="/documentador-red/img/logo_netdocs.png" alt="NetDocs Logo" style="width: 60px; height: 60px; object-fit: contain;">
        <h1 class="logo" style="margin: 0; padding: 0; border: none;">NetDocs</h1>
    </div>
    
    <form action="/documentador-red/buscar" method="GET" style="padding: 0 20px; margin-bottom: 20px;">
        <input type="text" name="q" placeholder="Buscar IP, Hostname, MAC..." required>
    </form>

    <ul class="sidebar-menu">
        <!-- SECCIÓN 1: DOCUMENTACIÓN SIMPLIFICADA -->
        <!-- SECCIÓN 1: DOCUMENTACIÓN SIMPLIFICADA -->
        <li>
            <div class="menu-toggle">Documentación Base</div>
            <ul class="sub-menu">
                <li><a href="/documentador-red/dashboard">Dashboard Principal</a></li>
                <li><a href="/documentador-red/inventario">Inventario General (Equipos)</a></li>
                
                <!-- AGREGAMOS EL ENLACE AQUÍ -->
                <li><a href="/documentador-red/cableado">Mapeo Físico (Cableado)</a></li>
                
                <li><a href="/documentador-red/vlans">Mapeo Lógico (VLANs / IPs)</a></li>
                <?php if ($_SESSION['rol_id'] <= 2): ?>
                    <li><a href="/documentador-red/alta-equipo">Provisionar Hardware</a></li>
                <?php endif; ?>
                <?php if ($_SESSION['rol_id'] == 1): ?>
                    <li><a href="/documentador-red/usuarios">Control de Accesos (Usuarios)</a></li>
                <?php endif; ?>
            </ul>
        </li>
        <!-- SECCIÓN 2: AUTOMATIZACIÓN -->
        <li>
            <div class="menu-toggle">Automatización</div>
            <ul class="sub-menu">
                <li><a href="/documentador-red/monitor_vivo">Monitor Telemetría (SNMP)</a></li>
                <li><a href="/documentador-red/estadisticas">Análisis de Infraestructura</a></li>
                <li><a href="/documentador-red/mapa-de-red">Topología de Red</a></li>
                <?php if ($_SESSION['rol_id'] <= 2): ?>
                <li><a href="/documentador-red/auditoria-ansible">Orquestador (Ansible)</a></li>
                <li><a href="/documentador-red/descubrimiento-napalm">Escáner NAPALM</a></li>
                <li><a href="/documentador-red/buscador-arp">Tabla MAC / ARP Global</a></li>
                <li><a href="/documentador-red/gestion-vlans">Aprovisionamiento VLANs</a></li>
                <li><a href="/documentador-red/respaldos">Auditoría de Configuraciones</a></li>
                <?php else: ?>
                <li><a href="#" style="opacity: 0.5; cursor: not-allowed;" title="Permiso denegado por Rol">Auditoría (Solo Técnicos)</a></li>
                <?php endif; ?>
            </ul>
        </li>
        
        <!-- SECCIÓN 3: LOGS -->
        <li>
            <div class="menu-toggle">Seguridad</div>
            <ul class="sub-menu">
                <?php if ($_SESSION['rol_id'] == 1): ?>
                    <li><a href="/documentador-red/logs" class="menu-item">Bitácora de Auditoría</a></li>
                <?php else: ?>
                    <li><a href="#" style="opacity: 0.5; cursor: not-allowed;" title="Permiso denegado por Rol">Bitácora (Solo Admin)</a></li>
                <?php endif; ?>
            </ul>
        </li>
    </ul>

    <!-- BOTÓN DE AYUDA (Fijo al fondo del sidebar) -->
    <div class="sidebar-footer">
        <div class="help-btn" onclick="abrirModalAyuda()">
            <span class="help-btn-icon">?</span>
            Manual de Arquitectura
        </div>
    </div>
</div>

<!-- =======================================================
     MODAL DE AYUDA GLOBAL (MANUAL OLED)
     ======================================================= -->
<div id="ayudaGlobalModal" class="modal-overlay" onclick="cerrarModalAyuda(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        
        <div class="modal-header">
            <h3>Arquitectura y Operación NetDocs</h3>
            <button class="close-modal" title="Cerrar Manual" onclick="cerrarModalAyuda(event)">&times;</button>
        </div>
        
        <div class="modal-body">
            <p class="manual-intro">
                Bienvenido al Centro de Operaciones de Red. Esta plataforma unifica el modelado estático del inventario físico con motores de automatización en tiempo real.
            </p>

            <!-- 1. EL FLUJO DE TRABAJO -->
            <div class="manual-section">
                <h4>1. Paradigma: Diseño vs. Operatividad</h4>
                <p style="color: #cccccc; font-size: 0.95em;">El sistema segmenta estrictamente la información para garantizar la trazabilidad:</p>
                <ul>
                    <li style="color: #cccccc; font-size: 0.95em; margin-bottom: 5px;"><strong>Documentación Base:</strong> Representa el <em>Estado Deseado</em> (Inventario).</li>
                    <li style="color: #cccccc; font-size: 0.95em;"><strong>Automatización:</strong> Extrae el <em>Estado Operativo Real</em> directamente de los equipos.</li>
                </ul>
                <div class="warn-box">
                    <strong>[ Configuration Drift ]</strong> Si la información de Inventario no coincide con la arrojada por Automatización, un ingeniero ha alterado la red sin actualizar los registros oficiales.
                </div>
            </div>

            <!-- 2. DOCUMENTACIÓN -->
            <div class="manual-section">
                <h4>2. Módulo de Documentación Base</h4>
                <p style="color: #cccccc; font-size: 0.95em;">Punto de partida obligatorio. Si la Bóveda carece de credenciales o plantillas OS válidas, la automatización será rechazada por el hardware.</p>
                <div class="manual-step">
                    <strong>Provisionar Hardware:</strong> Registre la IP de gestión, la plantilla de conexión Ansible y almacene las credenciales seguras (AES-256).
                </div>
                <div class="manual-step">
                    <strong>Mapeo Lógico:</strong> Gestión administrativa para reservar direcciones estáticas (IPAM) antes de ser configuradas en producción.
                </div>
                <div class="note-box">
                    <strong>[ Tip Operativo ]</strong> Las conexiones físicas (Patch Cords) ahora se gestionan directamente desde el botón "Inspeccionar" dentro de los detalles de cada equipo en el Inventario.
                </div>
            </div>

            <!-- 3. AUTOMATIZACIÓN - ANSIBLE -->
            <div class="manual-section">
                <h4>3. Motor de Orquestación (Ansible Core)</h4>
                <p style="color: #cccccc; font-size: 0.95em;">Utiliza SSH y TextFSM para escaneos profundos de la topología sin intervención humana.</p>
                <div class="manual-step">
                    <strong>Consola de Orquestador:</strong> Lanza playbooks centralizados para aprovisionar, generar Backups (.txt) y extraer telemetría masiva de puertos.
                </div>
                <div class="manual-step">
                    <strong>Análisis de Infraestructura:</strong> Posterior a la ejecución del Playbook, este panel grafica las discrepancias (Riesgos de seguridad, Tablas de Enrutamiento, etc.).
                </div>
            </div>

            <!-- 4. AUTOMATIZACIÓN - SNMP -->
            <div class="manual-section" style="border-bottom: none; margin-bottom: 0;">
                <h4>4. Telemetría Aislada (SNMPv2c/v3)</h4>
                <div class="manual-step">
                    <strong>Monitor (Polling Vivo):</strong> A diferencia de las cargas pesadas de Ansible, el protocolo SNMP se usa para consultas ultraligeras cada 5 segundos de CPU, RAM y Chasis.
                </div>
                <div class="success-box">
                    <strong>[ Ventaja Arquitectónica ]</strong> Separar SSH (Lógica) de UDP 161 (Salud) impide la saturación de los procesadores en los Cisco Catalyst e ISR del entorno.
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function abrirModalAyuda() {
        document.getElementById('ayudaGlobalModal').style.display = 'flex';
        document.body.style.overflow = 'hidden'; 
    }
    function cerrarModalAyuda(event) {
        if(event) event.preventDefault();
        document.getElementById('ayudaGlobalModal').style.display = 'none';
        document.body.style.overflow = 'auto'; 
    }
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") cerrarModalAyuda();
    });
</script>