<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta de Cable - <?php echo htmlspecialchars($cable['hostname'] . ' ' . $cable['nombre_puerto']); ?></title>
    <link rel="icon" type="image/png" href="/documentador-red/img/logo_netdocs.png">
    <style>
        /* Estilos de la pantalla previa (Modo Oscuro NetDocs) */
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background: #0b0f19; /* var(--bg-body) */
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            padding: 40px; 
            margin: 0;
        }
        
        .preview-title {
            color: white;
            margin-bottom: 30px;
            font-size: 1.2em;
            font-weight: 600;
        }
        
        /* Diseño "Wrap-around" (Envolvente). Mide aprox 18cm de largo x 4cm de alto */
        .cable-label-container {
            background: #ffffff; /* La etiqueta real SIEMPRE DEBE SER BLANCA para la impresora */
            border: 1px solid #000000;
            width: 700px; 
            height: 120px;
            display: flex;
            position: relative;
            box-sizing: border-box;
            overflow: hidden; /* Evita que el texto largo se salga del papel */
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); /* Sombra solo visible en pantalla */
        }

        /* Línea punteada en el centro para indicar por dónde doblar */
        .fold-line {
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            border-left: 2px dashed #999999;
        }

        /* Cada mitad de la etiqueta */
        .label-half {
            width: 50%;
            padding: 5px 15px; /* REDUCIDO: Gana espacio vertical */
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-sizing: border-box;
        }

        /* Invertir la mitad derecha para que al abrazar el cable quede al derecho */
        .right-half {
            transform: scale(-1, -1);
        }

        /* QR ligeramente más pequeño para evitar compresión vertical */
        .qr-code { 
            width: 85px; 
            height: 85px; 
            flex-shrink: 0; 
        } 
        
        .info-text {
            flex-grow: 1;
            padding: 0 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .info-text .tag { 
            font-size: 0.55em; /* Reducido */
            color: #555555; 
            text-transform: uppercase; 
            margin-top: 3px; 
            margin-bottom: 1px; 
            display: block;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .info-text .tag:first-child { margin-top: 0; }
        
        .info-text .data { 
            font-weight: bold; 
            font-size: 0.75em; /* Reducido para hostnames largos */
            margin-bottom: 2px; 
            display: block; 
            line-height: 1.1; 
            color: #000000;
            word-break: break-word; 
        }
        
        /* Botones de acción en pantalla */
        .btn-print { 
            background: #3b82f6; /* var(--primary) */
            color: #ffffff; 
            border: none; 
            padding: 12px 25px; 
            font-weight: bold; 
            cursor: pointer; 
            border-radius: 4px; 
            margin-top: 40px; 
            font-size: 1.1em;
            transition: opacity 0.2s;
        }
        .btn-print:hover { opacity: 0.8; }

        .btn-close {
            color: #8b949e; /* var(--text-muted) */
            text-decoration: none;
            margin-top: 15px;
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
                align-items: flex-start; 
                margin: 0;
            }
            .cable-label-container { 
                border: 1px dashed #cccccc; /* Línea de corte guía para tijeras si se imprime en A4 */
                box-shadow: none; 
            }
            .preview-title, .btn-print, .btn-close { 
                display: none !important; 
            }
        }
    </style>
</head>
<body>

    <?php 
        // Lógica para saber si mostramos IP o VLAN en el TEXTO VISIBLE de la etiqueta
        $info_red_tag = "";
        $info_red_data = "";
        
        if (!empty($cable['direccion_ip'])) {
            $info_red_tag = "IP (L3)";
            $info_red_data = $cable['direccion_ip'];
        } elseif (!empty($cable['vlan'])) {
            $info_red_tag = "VLAN";
            $info_red_data = $cable['vlan'];
        }

        // --- SOLUCIÓN DEL CÓDIGO QR ---
        // Extraemos el id_equipo. Si no viene como 'id_equipo', buscamos 'id'.
        $id_equipo_qr = $cable['id_equipo'] ?? $cable['id'] ?? 1;

        // Generamos la misma URL web que funciona en la etiqueta de los equipos
        // TODO: Asegúrate de que esta IP sea accesible desde la red Wifi al escanear
        $url_destino = "http://192.168.100.51/documentador-red/escaner?id=" . $id_equipo_qr; 
        $url_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&color=000000&bgcolor=FFFFFF&data=" . urlencode($url_destino);
    ?>

    <div class="preview-title">Vista Previa de Impresión</div>

    <div class="cable-label-container">
        <!-- LADO IZQUIERDO (Perspectiva desde el Switch) -->
        <div class="label-half">
            <img src="<?php echo $url_api; ?>" class="qr-code" alt="QR Code">
            <div class="info-text">
                <span class="tag">Origen Físico</span>
                <span class="data"><?php echo htmlspecialchars($cable['hostname'] . ' (' . $cable['nombre_puerto'] . ')'); ?></span>
                
                <?php if ($info_red_data !== ""): ?>
                    <span class="tag"><?php echo $info_red_tag; ?></span>
                    <span class="data"><?php echo htmlspecialchars($info_red_data); ?></span>
                <?php endif; ?>

                <span class="tag">Destino</span>
                <span class="data"><?php echo htmlspecialchars($cable['destino']); ?></span>
            </div>
        </div>

        <div class="fold-line"></div>

        <!-- LADO DERECHO INVERTIDO (Perspectiva desde el Servidor/Patch Panel) -->
        <div class="label-half right-half">
            <img src="<?php echo $url_api; ?>" class="qr-code" alt="QR Code">
            <div class="info-text">
                <span class="tag">Origen Físico</span>
                <!-- Aquí invertimos: El origen ahora es el destino original -->
                <span class="data"><?php echo htmlspecialchars($cable['destino']); ?></span>
                
                <span class="tag">Destino Switch</span>
                <!-- El destino ahora es el Switch -->
                <span class="data"><?php echo htmlspecialchars($cable['hostname'] . ' (' . $cable['nombre_puerto'] . ')'); ?></span>

                <!-- Ponemos la VLAN/IP al final porque pertenece al Switch -->
                <?php if ($info_red_data !== ""): ?>
                    <span class="tag"><?php echo $info_red_tag; ?></span>
                    <span class="data"><?php echo htmlspecialchars($info_red_data); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <button class="btn-print" onclick="window.print()">[ Enviar a Impresora ]</button>
    <a href="javascript:window.close();" class="btn-close">Cerrar Pestaña</a>

</body>
</html>