<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - NetDocs</title>
    <link rel="icon" type="image/png" href="/documentador-red/img/logo_netdocs.png">
    <!-- Importamos la tipografía moderna Inter -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        :root { 
            /* Paleta OLED Minimalist */
            --bg-body: #000000;       /* Negro puro absoluto */
            --panel-bg: #111111;      /* Gris casi negro */
            --primary: #ffffff;       /* Blanco puro para botones y enlaces */
            --primary-hover: #cccccc; /* Gris claro para hover */
            --text-main: #ededed;     /* Texto blanco */
            --text-muted: #888888;    /* Texto gris neutral */
            --border-color: #333333;  /* Borde gris oscuro */
            
            --port-up: #00e676;       /* Verde puro (Destaca muchísimo) */
            --port-down: #ff1744;     /* Rojo puro */
        }
        
        body { 
            margin: 0; 
            font-family: 'Inter', system-ui, -apple-system, sans-serif; 
            background: var(--bg-body); 
            color: var(--text-main); 
            display: flex; 
            height: 100vh; 
            overflow: hidden; 
            -webkit-font-smoothing: antialiased; 
        }
        
        /* --- CONTENIDO PRINCIPAL --- */
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        /* Cabecera superior */
        .topbar { background: var(--panel-bg); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
        .user-info { font-size: 0.9em; color: var(--text-muted); font-weight: 500; }
        .btn-logout { background: var(--port-down); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500; font-family: 'Inter', sans-serif; transition: 0.2s ease; }
        .btn-logout:hover { background: #dc2626; }

        /* Área de trabajo y Chasis del Switch rediseñado */
        .workspace { padding: 30px; }
        .switch-chassis { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 900px; }
        .switch-title { color: var(--text-muted); font-size: 0.75em; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; }
        
        /* Puertos más limpios */
        .port-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 6px; }
        .port { background: var(--bg-body); border: 1px solid var(--border-color); height: 44px; display: flex; align-items: flex-end; justify-content: center; font-size: 0.75em; color: var(--text-muted); padding-bottom: 6px; border-radius: 4px; cursor: pointer; position: relative; transition: all 0.2s ease; font-weight: 500; }
        .port:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); z-index: 10; color: var(--text-main); }
        
        /* Leds sutiles */
        .led { width: 8px; height: 8px; border-radius: 50%; position: absolute; top: 6px; right: 6px; }
        .led.up { background-color: var(--port-up); box-shadow: 0 0 6px rgba(16, 185, 129, 0.4); }
        .led.down { background-color: var(--port-down); opacity: 0.5; }
    </style>
</head>
<body>