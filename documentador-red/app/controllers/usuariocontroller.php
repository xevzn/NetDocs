<?php
// app/controllers/usuariocontroller.php
// El acceso (solo Administrador) lo impone la tabla de rutas; aquí se valida además cada dato.
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validar.php';
require_once __DIR__ . '/../models/usuario.php';
require_once __DIR__ . '/../models/log.php';

class UsuarioController {

    public static function index() {
        Auth::require([Auth::ROL_ADMIN]);

        $usuarios = Usuario::obtenerTodos();
        $roles = Usuario::obtenerRoles();

        require_once __DIR__ . '/../views/usuarios/gestion.php';
    }

    public static function guardar() {
        Auth::require([Auth::ROL_ADMIN]);

        $usuario         = Validar::post('usuario');
        $nombre_completo = Validar::texto(Validar::post('nombre_completo'), 150);
        $correo          = Validar::texto(Validar::post('correo'), 150);
        $password        = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $id_rol          = Validar::entero($_POST['id_rol'] ?? null);

        $rolesValidos = array_map('intval', array_column(Usuario::obtenerRoles(), 'id'));

        if (!Validar::usuario($usuario)
            || $nombre_completo === ''
            || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
            || Auth::validarPassword($password) !== null
            || $id_rol === null || !in_array($id_rol, $rolesValidos, true)) {
            header("Location: /documentador-red/usuarios?mensaje=datos_invalidos");
            exit();
        }

        $exito = Usuario::registrar($usuario, $nombre_completo, $correo, $password, $id_rol);

        if ($exito) {
            Log::registrar('Gestión de Usuarios', 'Creación de Cuenta', "Se creó el usuario '{$usuario}' ({$correo}) asignado al Rol ID: {$id_rol}.");
            header("Location: /documentador-red/usuarios?mensaje=creado");
        } else {
            header("Location: /documentador-red/usuarios?mensaje=error");
        }
        exit();
    }

    public static function eliminar() {
        Auth::require([Auth::ROL_ADMIN]);

        $id_eliminar = Validar::entero($_POST['id_usuario'] ?? null);
        if ($id_eliminar === null) {
            header("Location: /documentador-red/usuarios?mensaje=error");
            exit();
        }

        // No puedes suspender tu propia cuenta activa
        if ($id_eliminar === (int)$_SESSION['usuario_id']) {
            header("Location: /documentador-red/usuarios?mensaje=autoeliminacion");
            exit();
        }

        // Jamás suspender al ID 1 (superadministrador)
        if ($id_eliminar === 1) {
            Log::registrar('Seguridad', 'Violación de Seguridad', "Intento bloqueado: Un administrador intentó suspender la cuenta del Superadministrador maestro (ID 1).");
            header("Location: /documentador-red/usuarios?mensaje=proteccion_admin");
            exit();
        }

        // Suspensión lógica; la sesión del usuario deja de ser válida en su siguiente petición (Auth::require)
        Usuario::suspender($id_eliminar);

        Log::registrar('Gestión de Usuarios', 'Suspensión de Cuenta', "Se revocó el acceso al sistema para el usuario con ID interno: {$id_eliminar}.");

        header("Location: /documentador-red/usuarios?mensaje=suspendido");
        exit();
    }

    public static function actualizarPassword() {
        Auth::require([Auth::ROL_ADMIN]);

        $id = Validar::entero($_POST['id_usuario'] ?? null);
        $nueva_password = is_string($_POST['nueva_password'] ?? null) ? $_POST['nueva_password'] : '';

        if ($id === null || Auth::validarPassword($nueva_password) !== null) {
            header("Location: /documentador-red/usuarios?mensaje=datos_invalidos");
            exit();
        }

        Usuario::actualizarPassword($id, $nueva_password);

        Log::registrar('Seguridad', 'Cambio de Contraseña (Forzado)', "Un administrador cambió la contraseña de acceso del usuario con ID: {$id}.");

        header("Location: /documentador-red/usuarios?mensaje=pass_actualizada");
        exit();
    }
}
