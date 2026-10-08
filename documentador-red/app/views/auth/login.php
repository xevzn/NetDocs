<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NetDocs</title>
    <link rel="icon" type="image/png" href="/documentador-red/img/logo_netdocs.png">
    <style>
        :root {
            /* Colores extraídos exactamente de la imagen del Dashboard */
            --bg-body: #000000;      /* Fondo general ultra oscuro/negro */
            --panel-bg: #111111;     /* Gris muy oscuro para el contenedor */
            --input-bg: #000000;     /* Fondo de los campos de texto como el buscador */
            --border-color: #222222; /* Bordes sutiles y delgados */
            --primary: #ff3355;      /* Rojo/Rosa vibrante del botón 'Cerrar Sesión' */
            --text-main: #ffffff;    /* Texto blanco puro */
            --text-muted: #888888;   /* Gris para etiquetas secundarias */
            --port-down: #ff3355;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: var(--panel-bg);
            padding: 40px;
            border-radius: 6px;
            border: 1px solid var(--border-color); /* Borde sutil como las tarjetas de equipos */
            width: 100%;
            max-width: 360px;
        }

        .login-container h2 {
            margin-top: 0;
            text-align: center;
            font-weight: 600;
            font-size: 1.8em;
            letter-spacing: 0.5px;
        }

        .login-container p {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9em;
            margin-bottom: 30px;
            line-height: 1.4;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.75em;
            color: var(--text-muted);
            font-weight: bold;
            text-transform: uppercase; /* Estilo de etiquetas como 'TOTAL DE EQUIPOS' */
            letter-spacing: 1px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            background-color: var(--input-bg);
            color: var(--text-main);
            border-radius: 4px;
            box-sizing: border-box; 
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #555555; /* Resalte sutil al escribir */
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 1em;
            cursor: pointer;
            transition: opacity 0.2s ease-in-out;
            margin-top: 10px;
        }

        .btn-login:hover {
            opacity: 0.85;
        }

        /* Estilo de la alerta de error adaptada al nuevo fondo */
        .alert-error {
            background: rgba(255, 51, 85, 0.1); 
            border: 1px solid var(--port-down); 
            color: var(--port-down); 
            padding: 12px; 
            border-radius: 4px; 
            margin-bottom: 20px; 
            text-align: center; 
            font-weight: bold; 
            font-size: 0.85em; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <h2>NetDocs</h2>
        <img src="/documentador-red/img/logo_netdocs.png"" alt="NetDocs Logo" style="width: 120px; height: 120px; display: block; margin: 0 auto 5px auto;">
        <p>Plataforma de Documentación y Automatización de Infraestructura</p>

        <form action="/documentador-red/login-procesar" method="POST">
            <?php if (isset($_GET['error']) && $_GET['error'] == 'credenciales'): ?>
                <div class="alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    Usuario o contraseña incorrectos
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" required autocomplete="off" placeholder="Ej. Admin">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn-login">Ingresar al Sistema</button>
        </form>
    </div>

</body>
</html>