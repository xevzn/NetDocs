<?php
// app/controllers/usuariocontroller.php
require_once __DIR__ . '/../models/usuario.php';
require_once __DIR__ . '/../models/log.php';

class UsuarioController {
    
    public static function index() {
        session_start();
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) { 
            header("Location: /documentador-red/dashboard"); 
            exit(); 
        }

        $usuarios = Usuario::obtenerTodos();
        $roles = Usuario::obtenerRoles();
        
        require_once __DIR__ . '/../views/usuarios/gestion.php';
    }

    public static function guardar() {
        session_start();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['rol_id'] == 1) {
            $usuario = trim($_POST['usuario']);
            $nombre_completo = trim($_POST['nombre_completo']); 
            $correo = trim($_POST['correo']); 
            $password = $_POST['password'];
            $id_rol = intval($_POST['id_rol']);

            // Pasamos los nuevos argumentos al modelo
            $exito = Usuario::registrar($usuario, $nombre_completo, $correo, $password, $id_rol);

            if ($exito) {
                // LOG: Registro de nueva identidad
                Log::registrar('Gestión de Usuarios', 'Creación de Cuenta', "Se creó el usuario '{$usuario}' ({$correo}) asignado al Rol ID: {$id_rol}.");
                header("Location: /documentador-red/usuarios?mensaje=creado");
            } else {
                header("Location: /documentador-red/usuarios?mensaje=error");
            }
            exit();
        }
    }

    public static function eliminar() {
        session_start();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['rol_id'] == 1) {
            $id_eliminar = $_POST['id_usuario'];
            
            // Seguridad: No puedes suspender tu propia cuenta activa
            if ($id_eliminar == $_SESSION['usuario_id']) {
                header("Location: /documentador-red/usuarios?mensaje=autoeliminacion");
                exit();
            }
            
            // Seguridad Crítica: Jamás suspender al ID 1
            if ($id_eliminar == 1) {
                // LOG DE ALERTA: Alguien intentó un sabotaje o cometió un error grave
                Log::registrar('Seguridad', 'Violación de Seguridad', "Intento bloqueado: Un administrador intentó suspender la cuenta del Superadministrador maestro (ID 1).");
                header("Location: /documentador-red/usuarios?mensaje=proteccion_admin");
                exit();
            }

            // Cambiamos el eliminar físico por la suspensión lógica
            Usuario::suspender($id_eliminar);
            
            // LOG: Baja lógica de usuario
            Log::registrar('Gestión de Usuarios', 'Suspensión de Cuenta', "Se revocó el acceso al sistema para el usuario con ID interno: {$id_eliminar}.");
            
            header("Location: /documentador-red/usuarios?mensaje=suspendido");
            exit();
        }
    }

    public static function actualizarPassword() {
        session_start();
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
            header("Location: /documentador-red/dashboard");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id_usuario']) && !empty($_POST['nueva_password'])) {
            $id = $_POST['id_usuario'];
            $nueva_password = $_POST['nueva_password'];

            require_once __DIR__ . '/../models/usuario.php';
            Usuario::actualizarPassword($id, $nueva_password);
            
            // LOG: Modificación de credenciales (Crítico)
            Log::registrar('Seguridad', 'Cambio de Contraseña (Forzado)', "Un administrador cambió la contraseña de acceso del usuario con ID: {$id}.");
            
            header("Location: /documentador-red/usuarios?mensaje=pass_actualizada");
            exit();
        } else {
            header("Location: /documentador-red/usuarios");
            exit();
        }
    }
}