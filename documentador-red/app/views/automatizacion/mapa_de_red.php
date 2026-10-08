<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="main-content">
    <header class="topbar">
        <h2 style="margin:0; font-size:1.2em;">Mapa de Topología Dinámica</h2>
        <div class="user-info">
            <?php
                $roles = [1 => 'Administrador', 2 => 'Técnico', 3 => 'Lector'];
                $nombreRol = $roles[$_SESSION['rol_id']] ?? 'Rol desconocido';
            ?>
            Rol de Sesión: <b style="color: var(--text-main);"><?php echo htmlspecialchars($nombreRol); ?></b>
            <a href="/documentador-red/logout" class="btn-logout" style="margin-left: 15px; text-decoration: none;">Cerrar Sesión</a>
        </div>
    </header>

    <main class="workspace" style="padding: 0; display: flex; flex-direction: column; height: calc(100vh - 65px);">
        <?php if (empty($nodos_unicos)): ?>
            <div style="margin: 25px; background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; color: #f59e0b; padding: 20px; border-radius: 8px; font-weight: bold;">
                El mapa está vacío. Por favor, ejecuta el descubrimiento de topología (NAPALM) primero.
            </div>
        <?php else: ?>
            
            <style>
                .btn-map-control { background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.2s; }
                .btn-map-control:hover { background: var(--border-color); color: white; }
                .custom-input { background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); padding: 8px 12px; border-radius: 4px; width: 220px; outline: none; font-family: monospace; }
                .custom-input:focus { border-color: var(--primary); }
                .filter-label { cursor: pointer; color: var(--text-muted); font-size: 0.9em; font-weight: bold; display: flex; align-items: center; gap: 5px; }
                .filter-label:hover { color: var(--text-main); }
            </style>

            <!-- BARRA SUPERIOR DE CONTROLES OLED -->
            <div style="background: var(--panel-bg); padding: 15px 25px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap;">
                <div style="display: flex; gap: 20px; align-items: center;">
                    <label class="filter-label"><input type="checkbox" class="layer" data-class="core" checked style="accent-color: #ef4444;"> Core / Gateway</label>
                    <label class="filter-label"><input type="checkbox" class="layer" data-class="router" checked style="accent-color: #f59e0b;"> Enrutadores</label>
                    <label class="filter-label"><input type="checkbox" class="layer" data-class="firewall" checked style="accent-color: var(--port-up);"> Firewalls</label>
                    <label class="filter-label"><input type="checkbox" class="layer" data-class="switch" checked style="accent-color: #3b82f6;"> Switches</label>
                </div>
                
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input id="searchNode" type="text" class="custom-input" placeholder="Buscar por hostname...">
                    <button class="btn-map-control" onclick="zoomMap(1.2)" title="Acercar (Zoom In)">+</button>
                    <button class="btn-map-control" onclick="zoomMap(.8)" title="Alejar (Zoom Out)">-</button>
                    <button class="btn-map-control" onclick="cy.fit()" title="Restablecer Vista">[ ]</button>
                    <button class="btn-map-control" onclick="cy.center(cy.nodes(':selected'))" title="Centrar en Selección">⌖</button>
                </div>
            </div>

            <!-- ÁREA DEL MAPA E INSPECTOR -->
            <div style="display: flex; flex: 1; width: 100%; overflow: hidden;">
                
                <!-- LIENZO CYTOSCAPE -->
                <div id="cy" style="flex: 1; background: var(--bg-body);"></div>
                
                <!-- INSPECTOR LATERAL -->
                <aside style="width: 380px; background: var(--panel-bg); border-left: 1px solid var(--border-color); display: flex; flex-direction: column;">
                    <div style="padding: 20px; background: var(--bg-body); border-bottom: 1px solid var(--border-color);">
                        <h3 style="margin: 0; color: white; font-size: 1.1em;">Inspector de Nodo</h3>
                        <p style="margin: 5px 0 0; color: var(--text-muted); font-size: 0.85em;">Selecciona un dispositivo o enlace en el lienzo.</p>
                    </div>
                    <div id="panel-content" style="padding: 20px; color: var(--text-muted); flex: 1; overflow-y: auto;">
                        <div style="text-align: center; margin-top: 50px; font-style: italic;">
                            Esperando selección...
                        </div>
                    </div>
                </aside>
            </div>

            <script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.27.0/cytoscape.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded',function(){
                    const cy = cytoscape({
                        container: document.getElementById('cy'),
                        elements: <?= $json_elements ?>,
                        layout: {
                            name: 'breadthfirst',
                            directed: true,
                            padding: 55,
                            spacingFactor: 1.25,
                            roots: cy => cy.nodes('.core')
                        },
                        style: [
                            {
                                selector: 'node',
                                style: {
                                    label: 'data(label)',
                                    color: '#ededed', /* var(--text-main) */
                                    'text-valign': 'bottom',
                                    'text-margin-y': 9,
                                    'background-color': '#111111', /* var(--panel-bg) */
                                    'border-width': 3,
                                    width: 48,
                                    height: 48,
                                    'font-size': 11,
                                    'font-family': 'Consolas, monospace',
                                    'text-background-color': '#000000', /* var(--bg-body) */
                                    'text-background-opacity': 0.85,
                                    'text-background-padding': 3
                                }
                            },
                            {selector:'.core',style:{shape:'ellipse','border-color':'#ef4444'}},
                            {selector:'.router',style:{shape:'diamond','border-color':'#f59e0b',width:52,height:52}},
                            {selector:'.firewall',style:{shape:'hexagon','border-color':'#00e676',width:52,height:52}}, /* var(--port-up) */
                            {selector:'.switch',style:{shape:'round-rectangle','border-color':'#3b82f6',width:58,height:42}},
                            
                            {selector:'.ok',style:{'border-style':'solid'}},
                            {selector:'.warning',style:{'border-color':'#f59e0b'}},
                            {selector:'.critical',style:{'border-color':'#ef4444','border-width':5}},
                            {selector:'.unknown',style:{'border-color':'#333333','border-style':'solid'}}, /* var(--border-color) */
                            
                            {selector:'edge',style:{width:'data(width)','curve-style':'bezier','line-color':'#333333'}}, /* var(--border-color) */
                            {selector:'.cdp',style:{'line-color':'#3b82f6'}},
                            {selector:'.lldp',style:{'line-color':'#00e676','line-style':'dashed'}}, /* var(--port-up) */
                            {selector:':selected',style:{'border-color':'#ffffff','border-width':5}}
                        ]
                    });
                    window.cy = cy;

                    function esc(v){
                        return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
                    }
                    function estadoColor(s){
                        return {ok:'#00e676', warning:'#f59e0b', critical:'#ef4444', unknown:'#888888'}[s]||'#888888';
                    }
                    function valor(v,unidad=''){
                        return v===null||v===''?'N/D':esc(v)+unidad;
                    }
                    
                    function mostrarEquipo(d){
                        const color = estadoColor(d.status);
                        const vecinos = d.neighbors || [];
                        let conexiones = vecinos.length ? '<table style="width:100%; border-collapse:collapse; font-size:0.9em;"><tr style="color:#888; background:#000;"><th style="padding:10px; text-align:left; border-bottom:1px solid #333;">Interfaz Local</th><th style="padding:10px; text-align:left; border-bottom:1px solid #333;">Vecino Conectado</th></tr>' : '<div style="color:#888; font-style:italic;">Sin conexiones registradas.</div>';
                        
                        vecinos.forEach(v => {
                            conexiones += `<tr><td style="padding:10px; border-bottom:1px solid #333; color:white; font-family:monospace;">${esc(v.puerto)}</td><td style="padding:10px; border-bottom:1px solid #333;">${esc(v.vecino)}</td></tr>`;
                        });
                        if(vecinos.length) conexiones += '</table>';
                        
                        document.getElementById('panel-content').innerHTML = `
                            <div style="background:#000; border:1px solid #333; border-radius:6px; padding:15px; margin-bottom:20px;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong style="font-size:1.1em; color:white; font-family:monospace;">${esc(d.label)}</strong>
                                    <span style="border:1px solid ${color}; color:${color}; background:rgba(255,255,255,0.05); padding:4px 8px; border-radius:4px; font-size:0.75em; font-weight:bold; letter-spacing:1px;">● ${esc(d.statusText)}</span>
                                </div>
                            </div>
                            
                            <h4 style="color:white; margin-bottom:10px; font-size:0.9em; border-bottom:1px solid #333; padding-bottom:5px;">INFORMACIÓN DEL NODO</h4>
                            <div style="line-height:1.8; color:#888; font-size:0.9em;">
                                <b>Rol de Red:</b> ${esc(d.role)}<br>
                                <b>Dirección IP:</b> <span style="color:#3b82f6; font-family:monospace; font-weight:bold;">${esc(d.ip)}</span><br>
                                <b>Hardware:</b> ${esc(d.marca)} ${esc(d.modelo)}<br>
                                <b>Plataforma (OS):</b> ${esc(d.os)}<br>
                                <b>Ubicación:</b> ${esc(d.ubicacion)}
                            </div>
                            
                            <h4 style="color:white; margin:25px 0 10px; font-size:0.9em; border-bottom:1px solid #333; padding-bottom:5px;">MÉTRICAS DE RENDIMIENTO</h4>
                            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
                                <div style="background:#000; border:1px solid #333; padding:10px; text-align:center; border-radius:4px;"><small style="color:#888; display:block; margin-bottom:5px;">CPU Load</small><b style="color:white; font-family:monospace;">${valor(d.cpu,'%')}</b></div>
                                <div style="background:#000; border:1px solid #333; padding:10px; text-align:center; border-radius:4px;"><small style="color:#888; display:block; margin-bottom:5px;">Memory</small><b style="color:white; font-family:monospace;">${valor(d.memory,'%')}</b></div>
                                <div style="background:#000; border:1px solid #333; padding:10px; text-align:center; border-radius:4px;"><small style="color:#888; display:block; margin-bottom:5px;">Temp</small><b style="color:white; font-family:monospace;">${valor(d.temperature,'°C')}</b></div>
                            </div>
                            <div style="font-size:0.75em; color:#666; margin-top:10px; text-align:right;">Última lectura: ${valor(d.lastRead)}</div>
                            
                            <h4 style="color:white; margin:25px 0 10px; font-size:0.9em; border-bottom:1px solid #333; padding-bottom:5px;">ENLACES DESCUBIERTOS</h4>
                            ${conexiones}
                            
                            ${d.comentarios ? `<h4 style="color:white; margin:25px 0 10px; font-size:0.9em; border-bottom:1px solid #333; padding-bottom:5px;">COMENTARIOS</h4><div style="color:#888; font-size:0.85em; background:#000; padding:12px; border-radius:4px; border:1px solid #333; font-style:italic;">${esc(d.comentarios)}</div>` : ''}
                        `;
                    }

                    cy.on('tap','node', e => mostrarEquipo(e.target.data()));

                    cy.on('tap','edge', e => {
                        const d = e.target.data();
                        const color = d.proto === 'LLDP' ? '#00e676' : '#3b82f6';
                        document.getElementById('panel-content').innerHTML = `
                            <div style="background:#000; border:1px solid #333; border-radius:6px; padding:20px;">
                                <h3 style="margin-top:0; color:white; font-size:1.1em; border-bottom:1px solid #333; padding-bottom:10px;">Detalle del Enlace Físico</h3>
                                <p style="color:#888; margin-bottom:5px;"><b>Protocolo Base:</b> <span style="color:${color}; font-weight:bold; background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px;">${esc(d.proto)}</span></p>
                                <p style="color:#888; margin-bottom:5px;"><b>Equipo Origen:</b> <span style="color:white;">${esc(d.source)}</span></p>
                                <p style="color:#888; margin-bottom:15px;"><b>Puerto Local:</b> <span style="font-family:monospace; color:#3b82f6;">${esc(d.psrc)}</span></p>
                                
                                <div style="text-align:center; color:#555; margin-bottom:15px;">⬇⬇⬇</div>
                                
                                <p style="color:#888; margin-bottom:5px;"><b>Equipo Destino:</b> <span style="color:white;">${esc(d.target)}</span></p>
                                <p style="color:#888; margin-bottom:15px;"><b>Puerto Remoto:</b> <span style="font-family:monospace; color:#00e676;">${esc(d.pdst)}</span></p>
                                
                                <p style="font-size:0.8em; color:#555; margin-top:20px; text-align:right;">Descubierto el: ${esc(d.date)}</p>
                            </div>
                        `;
                    });

                    cy.on('tap', e => {
                        if(e.target === cy){
                            document.getElementById('panel-content').innerHTML = '<div style="text-align:center; color:#888; margin-top:50px; font-style:italic;">Selecciona un dispositivo o enlace en el lienzo...</div>';
                        }
                    });

                    document.querySelectorAll('.layer').forEach(cb => {
                        cb.addEventListener('change', function(){
                            cy.nodes('.'+this.dataset.class).style('display', this.checked ? 'element' : 'none');
                            cy.edges().forEach(e => {
                                const visible = e.source().style('display') !== 'none' && e.target().style('display') !== 'none';
                                e.style('display', visible ? 'element' : 'none');
                            });
                        });
                    });

                    document.getElementById('searchNode').addEventListener('input', function(){
                        const q = this.value.trim().toUpperCase();
                        cy.nodes().forEach(n => n.style('opacity', !q || n.data('label').includes(q) ? 1 : 0.15));
                    });
                });

                function zoomMap(factor) {
                    cy.zoom({
                        level: cy.zoom() * factor,
                        renderedPosition: { x: cy.width() / 2, y: cy.height() / 2 }
                    });
                }
            </script>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>