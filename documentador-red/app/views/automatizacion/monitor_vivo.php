<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="main-content">
    <header class="topbar">
        <h2 style="margin: 0; font-size: 1.2em;">Monitor de Rendimiento en Vivo (SNMP)</h2>
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
        <style>
            /* Variables estandarizadas OLED */
            .custom-select { 
                background: var(--bg-body); 
                color: var(--text-main); 
                border: 1px solid var(--border-color); 
                padding: 12px; 
                border-radius: 4px; 
                outline: none; 
                min-width: 300px; 
                font-family: monospace;
                font-size: 1.05em;
                transition: border-color 0.3s;
            }
            .custom-select:focus { border-color: var(--primary); }
            
            @keyframes pulse-text {
                0% { opacity: 1; }
                50% { opacity: 0.4; }
                100% { opacity: 1; }
            }
            .status-waiting { animation: pulse-text 2s infinite; }
            
            .btn-start { background: var(--port-up); color: #000; border: none; padding: 12px 25px; border-radius: 4px; font-weight: bold; cursor: pointer; transition: 0.2s; font-size: 1em; }
            .btn-start:hover { opacity: 0.8; }
            .btn-stop { background: var(--port-down); color: #fff; border: none; padding: 12px 25px; border-radius: 4px; font-weight: bold; cursor: pointer; transition: 0.2s; display: none; font-size: 1em; }
            .btn-stop:hover { opacity: 0.8; }
            .btn-start:disabled { background: var(--border-color); cursor: not-allowed; color: var(--text-muted); }

            .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
            .kpi-card { background: var(--bg-body); border: 1px solid var(--border-color); padding: 25px; border-radius: 8px; text-align: center; border-bottom: 4px solid var(--primary); }
            .kpi-title { color: var(--text-muted); font-size: 0.85em; text-transform: uppercase; font-weight: bold; margin-bottom: 15px; }
            .kpi-val { color: white; font-size: 2.2em; font-family: Consolas, monospace; font-weight: bold; }
            
            .chart-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .chart-box { background: var(--bg-body); border: 1px solid var(--border-color); padding: 20px; border-radius: 8px; height: 300px; }
            @media (max-width: 900px) { .chart-row { grid-template-columns: 1fr; } }

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
            <summary>Documentación Técnica: Motor de Telemetría SNMP</summary>
            <div class="details-content">
                Este módulo proporciona monitorización activa del estado del hardware en tiempo real sin requerir agentes instalados en el equipo.<br><br>
                1. <b>Protocolo:</b> Utiliza el protocolo <b>SNMP</b> (Simple Network Management Protocol) vía puerto UDP 161.<br>
                2. <b>Frecuencia:</b> Al iniciar la captura, NetDocs ejecutará un ciclo de <i>Polling</i> enviando un script de Python en <i>background</i> cada <b>5 segundos</b> para extraer los OIDs de CPU, Memoria y Temperatura del dispositivo seleccionado.<br>
                3. <b>Requisito:</b> El dispositivo objetivo debe tener configurada una comunidad SNMP válida (Lectura) en la pestaña "Inventario" > "Editar Equipo".
            </div>
        </details>

        <!-- PANEL DE CONTROL -->  
        <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px; border-top: 3px solid var(--primary); margin-bottom: 25px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
            <div>
                <label style="display:block; color:var(--text-muted); font-size:0.85em; font-weight:bold; margin-bottom:8px;">Dispositivo Objetivo (SNMP Configurado):</label>
                <select id="equipo_select" class="custom-select">
                    <option value="" disabled selected>-- Seleccionar de la Base de Datos --</option>
                    <?php foreach ($equipos_snmp as $eq): ?>
                        <option value="<?= $eq['id'] ?>"><?= htmlspecialchars($eq['hostname']) ?> [<?= $eq['ip_gestion'] ?>]</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-top: 22px;">
                <button id="btn_start" class="btn-start" onclick="iniciarMonitoreo()">▶ Iniciar Ciclo (Polling)</button>
                <button id="btn_stop" class="btn-stop" onclick="detenerMonitoreo()">⏹ Detener Captura</button>
            </div>
            <div style="margin-top: 22px; margin-left: auto;">
                <span id="status_indicator" class="status-waiting" style="color: var(--text-muted); font-weight: bold; font-family: monospace;">[ Estado: En Espera ]</span>
            </div>
        </div>

        <div style="background: var(--panel-bg); padding: 25px; border-radius: 8px;">
            <!-- TARJETAS DE INDICADORES (KPIs) -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-title">Carga de CPU</div>
                    <div class="kpi-val" id="kpi_cpu">--%</div>
                </div>
                <div class="kpi-card" style="border-bottom-color: #8b5cf6;">
                    <div class="kpi-title">Memoria RAM Utilizada</div>
                    <div class="kpi-val" id="kpi_ram">--%</div>
                </div>
                <div class="kpi-card" style="border-bottom-color: #f59e0b;">
                    <div class="kpi-title">Temperatura de Chasis</div>
                    <div class="kpi-val" id="kpi_temp">--°C</div>
                </div>
            </div>

            <!-- GRÁFICAS EN TIEMPO REAL -->
            <div class="chart-row">
                <div class="chart-box">
                    <h3 style="color: white; margin-top: 0; font-size: 1.05em; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">Comportamiento de CPU (%)</h3>
                    <div style="position: relative; height: 230px; width: 100%;">
                        <canvas id="liveCpuChart"></canvas>
                    </div>
                </div>
                <div class="chart-box">
                    <h3 style="color: white; margin-top: 0; font-size: 1.05em; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">Consumo de Memoria (%)</h3>
                    <div style="position: relative; height: 230px; width: 100%;">
                        <canvas id="liveRamChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Variables Globales
    let intervaloMonitoreo;
    let cpuChart, ramChart;
    const maxDataPoints = 20; // Cuántos puntos mostrar en la gráfica antes de deslizar

    // Configuración Inicial de Chart.js
    Chart.defaults.color = '#888888'; // var(--text-muted)
    Chart.defaults.scale.grid.color = '#333333'; // var(--border-color)

    function initCharts() {
        const ctxCpu = document.getElementById('liveCpuChart').getContext('2d');
        const ctxRam = document.getElementById('liveRamChart').getContext('2d');

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { 
                y: { min: 0, max: 100 }, 
                x: { grid: { display: false } } 
            },
            animation: { duration: 0 } // Desactiva la animación inicial para que el "scroll" sea fluido
        };

        cpuChart = new Chart(ctxCpu, {
            type: 'line',
            data: { labels: [], datasets: [{ label: 'CPU %', data: [], borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', borderWidth: 2, fill: true, tension: 0.4 }] },
            options: commonOptions
        });

        ramChart = new Chart(ctxRam, {
            type: 'line',
            data: { labels: [], datasets: [{ label: 'RAM %', data: [], borderColor: '#8b5cf6', backgroundColor: 'rgba(139, 92, 246, 0.1)', borderWidth: 2, fill: true, tension: 0.4 }] },
            options: commonOptions
        });
    }

    function actualizarGraficasYDatos(datos) {
        // Obtenemos la hora actual HH:MM:SS
        const ahora = new Date();
        const etiquetaTiempo = ahora.getHours().toString().padStart(2, '0') + ':' + 
                               ahora.getMinutes().toString().padStart(2, '0') + ':' + 
                               ahora.getSeconds().toString().padStart(2, '0');

        // Actualizamos los Textos KPI
        document.getElementById('kpi_cpu').innerText = datos.cpu_load + '%';
        document.getElementById('kpi_ram').innerText = datos.memory_used_percent + '%';
        document.getElementById('kpi_temp').innerText = (datos.temperatura > 0) ? datos.temperatura + '°C' : 'N/D';
        
        // Cambiar color del KPI si hay alerta
        document.getElementById('kpi_cpu').style.color = (datos.cpu_load >= 80) ? '#ff1744' : '#ededed';

        // Actualizamos Gráficas
        cpuChart.data.labels.push(etiquetaTiempo);
        cpuChart.data.datasets[0].data.push(datos.cpu_load);

        ramChart.data.labels.push(etiquetaTiempo);
        ramChart.data.datasets[0].data.push(datos.memory_used_percent);

        // Deslizar gráfica si superamos los puntos máximos
        if (cpuChart.data.labels.length > maxDataPoints) {
            cpuChart.data.labels.shift();
            cpuChart.data.datasets[0].data.shift();
            
            ramChart.data.labels.shift();
            ramChart.data.datasets[0].data.shift();
        }

        cpuChart.update();
        ramChart.update();
    }

    // ==========================================
    // LÓGICA DE CONTROL (INICIAR / DETENER)
    // ==========================================
    async function hacerPolling() {
        const idEquipo = document.getElementById('equipo_select').value;
        const statusInd = document.getElementById('status_indicator');
        
        try {
            statusInd.innerText = "[ Estado: Consultando MIBs... ]";
            statusInd.style.color = "#f59e0b"; // Naranja

            // Llamada a la API que creamos en PHP
            const response = await fetch(`/documentador-red/api/snmp-live?id_equipo=${idEquipo}`);
            const data = await response.json();

            if (data.error) {
                statusInd.innerText = "[ Error: " + data.error + " ]";
                statusInd.style.color = "#ff1744"; // Rojo var(--port-down)
                detenerMonitoreo(); // Auto-detenemos si hay error grave
            } else {
                statusInd.innerText = "[ Estado: Recibiendo Datos OK ]";
                statusInd.style.color = "#00e676"; // Verde var(--port-up)
                actualizarGraficasYDatos(data);
            }
        } catch (error) {
            statusInd.innerText = "[ Error: Timeout / Fallo de Conexión ]";
            statusInd.style.color = "#ff1744";
            console.error("Error en Fetch:", error);
        }
    }

    function iniciarMonitoreo() {
        const select = document.getElementById('equipo_select');
        if (!select.value) {
            alert("Por favor, selecciona un equipo de la lista.");
            return;
        }

        // Bloquear UI para evitar dobles clics
        select.disabled = true;
        document.getElementById('btn_start').style.display = 'none';
        document.getElementById('btn_stop').style.display = 'inline-block';

        // Limpiar gráficas antes de empezar
        cpuChart.data.labels = []; cpuChart.data.datasets[0].data = [];
        ramChart.data.labels = []; ramChart.data.datasets[0].data = [];
        cpuChart.update(); ramChart.update();

        // Hacer la primera consulta inmediatamente
        hacerPolling();

        // Programar la consulta cada 5 segundos
        intervaloMonitoreo = setInterval(hacerPolling, 5000);
    }

    function detenerMonitoreo() {
        clearInterval(intervaloMonitoreo);
        document.getElementById('equipo_select').disabled = false;
        document.getElementById('btn_start').style.display = 'inline-block';
        document.getElementById('btn_stop').style.display = 'none';
        
        const statusInd = document.getElementById('status_indicator');
        statusInd.innerText = "[ Estado: Polling Pausado ]";
        statusInd.style.color = "#888888"; // var(--text-muted)
    }

    // Inicializar gráficas al cargar la página
    document.addEventListener('DOMContentLoaded', initCharts);
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>