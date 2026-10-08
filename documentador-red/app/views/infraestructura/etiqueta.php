<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta QR - <?php echo htmlspecialchars($equipo['hostname']); ?></title>
    <link rel="icon" type="image/png" href="/documentador-red/img/logo_netdocs.png">
    <style>
        /* Estilos de la pantalla previa (Modo Oscuro NetDocs) */
        body { 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
            background: #0b0f19; /* var(--bg-body) */
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            padding: 50px; 
            margin: 0;
        }
        
        .preview-title {
            color: white;
            margin-bottom: 30px;
            font-size: 1.2em;
            font-weight: 600;
        }

        /* Contenedor de la Etiqueta (Debe ser blanco para imprimir bien) */
        .label-card { 
            background: #ffffff; 
            width: 320px; 
            padding: 25px; 
            border: 2px solid #000000; 
            border-radius: 8px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); 
            text-align: center; 
            box-sizing: border-box;
        }
        
        .label-header { 
            font-size: 1.4em; 
            font-weight: bold; 
            border-bottom: 2px solid #000000; 
            padding-bottom: 15px; 
            margin-bottom: 15px; 
            color: #000000;
            word-break: break-word;
        }
        
        .qr-code { 
            margin: 15px 0; 
            width: 180px;
            height: 180px;
        }
        
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            font-size: 0.9em; 
            margin-bottom: 10px; 
            border-bottom: 1px dotted #999999; 
            padding-bottom: 6px; 
            color: #000000;
        }
        .info-row strong { 
            color: #555555; 
            text-transform: uppercase;
            font-size: 0.85em;
        }
        
        /* Controles de pantalla */
        .controls-wrapper {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .btn-print { 
            background: #3b82f6; /* var(--primary) */
            color: #ffffff; 
            border: none; 
            padding: 12px 25px; 
            font-weight: bold; 
            cursor: pointer; 
            border-radius: 4px; 
            font-size: 1.1em; 
            transition: opacity 0.2s;
        }
        .btn-print:hover { opacity: 0.8; }
        
        .btn-close {
            color: #8b949e; /* var(--text-muted) */
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9em;
            transition: color 0.2s;
        }
        .btn-close:hover { color: #ffffff; }

        /* ======================================================== */
        /* REGLAS ESTRICTAS DE IMPRESIÓN (Solo se envía la etiqueta) */
        /* ======================================================== */
        @media print {
            @page { margin: 0; }
            body { 
                background: #ffffff; 
                padding: 0; 
                margin: 0;
            }
            .label-card { 
                border: 1px dashed #cccccc; /* Línea guía de corte */
                box-shadow: none; 
                width: 100%; /* Adaptar al tamaño del papel de la impresora térmica */
                max-width: 300px; 
                margin: 0; 
                border-radius: 0;
            }
            .preview-title, .controls-wrapper { 
                display: none !important; 
            }
        }
    </style>
</head>
<body>

    <div class="preview-title">Vista Previa de Etiqueta de Activo</div>

    <div class="label-card">
        <div class="label-header">
            <?php echo htmlspecialchars($equipo['hostname']); ?>
        </div>

        <?php 
            // NUEVA RUTA: Apunta a /escaner en lugar de /detalles
            $url_destino = "http://192.168.1.72/documentador-red/escaner?id=" . $equipo['id']; 
            $url_api = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=0&color=000000&bgcolor=FFFFFF&data=" . urlencode($url_destino);
        ?>
        <img src="<?php echo $url_api; ?>" alt="Código QR" class="qr-code">

        <div class="info-row">
            <strong>Tipo:</strong> <span><?php echo htmlspecialchars($equipo['tipo']); ?></span>
        </div>
        <div class="info-row">
            <strong>Modelo:</strong> <span><?php echo htmlspecialchars($equipo['marca'] . ' ' . $equipo['modelo']); ?></span>
        </div>
        <div class="info-row" style="border-bottom: none;">
            <strong>Ubicación:</strong> <span><?php echo htmlspecialchars($equipo['ubicacion']); ?></span>
        </div>
    </div>

    <div class="controls-wrapper">
        <button class="btn-print" onclick="window.print()">[ Enviar a Impresora ]</button>
        <a href="/documentador-red/inventario" class="btn-close">Volver al Inventario</a>
    </div>

</body>
</html>