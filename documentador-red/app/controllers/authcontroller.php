<?php
// C:\xampp\htdocs\documentador-red\app\controllers\authcontroller.php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../models/log.php';
class AuthController {
    
    // --- 1. PROCESADOR DE LOGIN ---
    public static function procesarLogin() {
        if (!isset($_POST['usuario']) || !isset($_POST['password'])) {
            die("Error: Faltan credenciales.");
        }

        $usuarioIngresado = trim($_POST['usuario']);
        $passwordIngresado = $_POST['password'];

        try {
            $db = Database::conectar();

            // Buscamos al usuario por nombre o correo
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE usuario = :usuario");
            $stmt->bindParam(':usuario', $usuarioIngresado);
            $stmt->execute();

            $usuarioDb = $stmt->fetch();

            // Validamos que el usuario exista y la contraseña coincida con el hash
            if ($usuarioDb && password_verify($passwordIngresado, $usuarioDb['password_hash'])) {
                
                session_start();
                $_SESSION['usuario_id'] = $usuarioDb['id'];
                $_SESSION['rol_id'] = $usuarioDb['id_rol'];
                $_SESSION['nombre_usuario'] = $usuarioDb['usuario'];

                require_once __DIR__ . '/../models/log.php';
                Log::registrar('Autenticación', 'Inicio de Sesión', "El usuario accedió al sistema desde la IP de gestión.");
                
                // --- REDIRECCIÓN INTELIGENTE ---
                // Si el sistema recuerda que querías ir a un QR específico, te manda ahí
                if (isset($_SESSION['redirect_to'])) {
                    $url_destino = $_SESSION['redirect_to'];
                    unset($_SESSION['redirect_to']); // Borramos la memoria para que no se cicle
                    header("Location: " . $url_destino);
                } else {
                    // Si iniciaste sesión normalmente desde tu computadora, te manda al Dashboard
                    header("Location: /documentador-red/dashboard");
                }
                exit();
                      
            } else {
                // --- NUEVO COMPORTAMIENTO DE ERROR ---
                // En lugar de mostrar la pantalla negra de error, lo regresamos al login
                // enviando una "señal" en la URL llamada "error=credenciales"
                header("Location: /documentador-red/?error=credenciales");
                exit();
            }
        } catch (PDOException $e) {
            echo "Error de base de datos: " . $e->getMessage();
        }
    }

    // --- 2. PROCESADOR DE REGISTRO ---
    public static function procesarRegistro() {
        if (!isset($_POST['usuario']) || !isset($_POST['password'])) {
            die("Error: Faltan datos para el registro.");
        }

        $usuario = trim($_POST['usuario']);
        $password = $_POST['password'];
        $id_rol = 3;

        // Encriptamos la contraseña
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $db = Database::conectar();
            $stmt = $db->prepare("INSERT INTO usuarios (usuario, password_hash, id_rol) VALUES (:usuario, :pass, :rol)");
            
            $stmt->bindParam(':usuario', $usuario);
            $stmt->bindParam(':pass', $passwordHash);
            $stmt->bindParam(':rol', $id_rol);
            $stmt->execute();

            echo "<div style='background-color: #1e1e2f; color: #00d2ff; padding: 40px; text-align: center; font-family: sans-serif; height: 100vh;'>
                    <h2>¡Usuario registrado con éxito!</h2>
                    <p>La cuenta de <b>" . htmlspecialchars($usuario) . "</b> ha sido creada.</p>
                    <a href='/documentador-red/' style='color: white;'>Ir al Login</a>
                  </div>";

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo "<div style='background-color: #1e1e2f; color: #ff4d4d; padding: 40px; text-align: center; font-family: sans-serif; height: 100vh;'>
                        <h3>Error de Registro</h3>
                        <p>El nombre de usuario ya está en uso.</p>
                        <a href='/documentador-red/registro' style='color: white;'>Intentar de nuevo</a>
                      </div>";
            } else {
                echo "Error de base de datos: " . $e->getMessage();
            }
        }
    }
    // --- 3. PROCESADOR DE LOGOUT (Cerrar Sesión) ---
    public static function logout() {
        // 1. Reanudamos la sesión actual para saber cuál destruir
        session_start();

        if(isset($_SESSION['usuario_id'])){
            require_once __DIR__ . '/../models/log.php';
            Log::registrar('Autenticación', 'Cierre de Sesión', "El usuario cerró sesión voluntariamente.");
        }
        
        // 2. Vaciamos todas las variables de sesión (usuario, rol, etc.)
        $_SESSION = array();

        // 3. Borramos la cookie de sesión en el navegador por seguridad
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // 4. Destruimos la sesión en el servidor
        session_destroy();

        // 5. Redirigimos de vuelta a la pantalla de Login
        header("Location: /documentador-red/");
        exit();
    }
}