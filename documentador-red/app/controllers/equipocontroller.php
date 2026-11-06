<?php
// app/controllers/equipocontroller.php
// El rol requerido por cada ruta lo declara app/core/routes.php; los métodos de escritura
// vuelven a exigirlo aquí (defensa en profundidad) y validan cada dato en el servidor.

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validar.php';
require_once __DIR__ . '/../models/equipo.php';
require_once __DIR__ . '/../models/log.php';
require_once __DIR__ . '/../core/vault.php';

class EquipoController {

    private const TIPOS       = ['Switch', 'Router', 'Firewall', 'Servidor', 'Otro'];
    private const PLANTILLAS  = ['ninguna', 'cisco_ios_legacy', 'cisco_ios_modern', 'huawei_vrp', 'mikrotik_routeros'];
    private const MODOS_PUERTO = ['Acceso', 'Troncal'];

    /** Lee y valida los campos comunes de alta/edición. Devuelve [datos, error]. */
    private static function leerDatosEquipo(): array {
        $d = [
            'hostname'           => Validar::post('hostname'),
            'marca'              => Validar::texto(Validar::post('marca'), 50),
            'modelo'             => Validar::texto(Validar::post('modelo'), 100),
            'tipo'               => Validar::post('tipo'),
            'plantilla_conexion' => Validar::post('plantilla_conexion', 'cisco_ios_legacy'),
            'ip_gestion'         => Validar::post('ip_gestion'),
            'ubicacion'          => Validar::texto(Validar::post('ubicacion'), 100),
            'comentarios'        => Validar::texto(Validar::post('comentarios'), 2000),
            'ssh_user'           => Validar::texto(Validar::post('ssh_user'), 100),
            'ssh_pass'           => Validar::post('ssh_pass'),
        ];

        if (!Validar::hostname($d['hostname'])) {
            return [$d, 'hostname'];
        }
        if (!Validar::enLista($d['tipo'], self::TIPOS)) {
            return [$d, 'tipo'];
        }
        if (!Validar::enLista($d['plantilla_conexion'], self::PLANTILLAS)) {
            return [$d, 'plantilla'];
        }
        if ($d['ip_gestion'] !== '' && !Validar::ipv4($d['ip_gestion'])) {
            return [$d, 'ip'];
        }
        if (strlen($d['ssh_pass']) > 255) {
            return [$d, 'password'];
        }
        $d['ssh_user'] = $d['ssh_user'] !== '' ? $d['ssh_user'] : null;
        $d['ssh_pass'] = $d['ssh_pass'] !== '' ? $d['ssh_pass'] : null;
        return [$d, null];
    }

    // Muestra la tabla principal con todos los equipos
    public static function listar() {
        Auth::start();
        $equipos = Equipo::obtenerTodos();
        require_once __DIR__ . '/../views/infraestructura/inventario.php';
    }

    // Muestra el formulario HTML de alta
    public static function crear() {
        Auth::require(Auth::STAFF);
        require_once __DIR__ . '/../views/infraestructura/alta.php';
    }

    // Procesa el alta de un equipo
    public static function guardar() {
        Auth::require(Auth::STAFF);

        [$d, $error] = self::leerDatosEquipo();
        if ($error !== null) {
            header("Location: /documentador-red/alta-equipo?mensaje=error");
            exit();
        }

        $ssh_password_encrypted = null;
        if ($d['ssh_pass'] !== null) {
            $ssh_password_encrypted = vault_encrypt_credential($d['ssh_pass']);
            if ($ssh_password_encrypted === null) {
                header("Location: /documentador-red/alta-equipo?mensaje=error");
                exit();
            }
        }

        $exito = Equipo::registrar($d['hostname'], $d['marca'], $d['modelo'], $d['tipo'], $d['plantilla_conexion'],
                                   $d['ip_gestion'], $d['ubicacion'], $d['comentarios'], $d['ssh_user'], $ssh_password_encrypted);

        if ($exito) {
            Log::registrar('Inventario', 'Alta de Dispositivo', "Se registró el equipo {$d['hostname']} ({$d['tipo']}). IP Gestión: {$d['ip_gestion']}.");
            header("Location: /documentador-red/alta-equipo?mensaje=exito");
        } else {
            header("Location: /documentador-red/alta-equipo?mensaje=error");
        }
        exit();
    }

    // Etiqueta QR lista para imprimir
    public static function etiqueta() {
        Auth::start();
        $id = Validar::entero($_GET['id'] ?? null);
        if ($id === null) {
            http_response_code(400);
            exit("Error: No se especificó el equipo a imprimir.");
        }
        $equipo = Equipo::obtenerPorId($id);
        if (!$equipo) {
            http_response_code(404);
            exit("Error: El equipo no existe en la base de datos.");
        }
        require_once __DIR__ . '/../views/infraestructura/etiqueta.php';
    }

    // Detalles completos de un equipo y sus puertos
    public static function detalles() {
        Auth::start();
        $id = Validar::entero($_GET['id'] ?? null);
        if ($id === null) {
            http_response_code(400);
            exit("Error: No se especificó el equipo a consultar.");
        }

        $equipo = Equipo::obtenerPorId($id);
        if (!$equipo) {
            http_response_code(404);
            exit("Error: El equipo no existe.");
        }

        // La bóveda solo se descifra para Administradores; cada consulta queda en la bitácora.
        $puedeVerBoveda = ((int)($_SESSION['rol_id'] ?? 0) === Auth::ROL_ADMIN);
        if ($puedeVerBoveda && !empty($equipo['ssh_password_encrypted'])) {
            Log::registrar('Seguridad', 'Consulta de Bóveda', "Se abrió el detalle del equipo {$equipo['hostname']} (ID {$id}) con acceso a sus credenciales.");
        }

        $puertos = Equipo::obtenerPuertos($id);

        require_once __DIR__ . '/../views/infraestructura/detalles.php';
    }

    // Información al escanear el QR (sin menús)
    public static function escaner() {
        Auth::start();
        $id = Validar::entero($_GET['id'] ?? null);
        if ($id === null) {
            http_response_code(400);
            exit("Error: Equipo no especificado.");
        }

        $equipo = Equipo::obtenerPorId($id);
        if (!$equipo) {
            http_response_code(404);
            exit("Error: El equipo no existe.");
        }
        $puertos = Equipo::obtenerPuertos($id);

        require_once __DIR__ . '/../views/infraestructura/vista_qr.php';
    }

    /** Valida los campos de un puerto. Devuelve [datos, error]. */
    private static function leerDatosPuerto(): array {
        $d = [
            'nombre_puerto' => Validar::texto(Validar::post('nombre_puerto'), 50),
            'estado'        => Validar::post('estado'),
            'modo_puerto'   => Validar::post('modo_puerto', 'Acceso'),
            'vlan'          => Validar::texto(Validar::post('vlan'), 50),
            'direccion_ip'  => Validar::texto(Validar::post('direccion_ip'), 50),
            'destino'       => Validar::texto(Validar::post('destino'), 255),
        ];
        if ($d['nombre_puerto'] === '') {
            return [$d, 'nombre'];
        }
        if (!Validar::enLista($d['estado'], ['up', 'down'])) {
            return [$d, 'estado'];
        }
        if (!Validar::enLista($d['modo_puerto'], self::MODOS_PUERTO)) {
            return [$d, 'modo'];
        }
        if ($d['direccion_ip'] !== '' && !Validar::ipv4($d['direccion_ip']) && !Validar::cidr($d['direccion_ip'])) {
            return [$d, 'ip'];
        }
        $d['vlan'] = $d['vlan'] !== '' ? $d['vlan'] : null;
        $d['direccion_ip'] = $d['direccion_ip'] !== '' ? $d['direccion_ip'] : null;
        return [$d, null];
    }

    // Alta de un nuevo puerto
    public static function guardarPuerto() {
        Auth::require(Auth::STAFF);

        $id_equipo = Validar::entero($_POST['id_equipo'] ?? null);
        [$d, $error] = self::leerDatosPuerto();
        if ($id_equipo === null || $error !== null || !Equipo::obtenerPorId($id_equipo)) {
            $_SESSION['error_puerto'] = "Datos del puerto no válidos.";
            header("Location: /documentador-red/detalles?id=" . (int)$id_equipo);
            exit();
        }

        require_once __DIR__ . '/../core/database.php';
        $db = Database::conectar();

        // Validación de duplicado
        $stmt_check = $db->prepare("SELECT id FROM puertos WHERE id_equipo = ? AND nombre_puerto = ?");
        $stmt_check->execute([$id_equipo, $d['nombre_puerto']]);

        if ($stmt_check->fetch()) {
            $_SESSION['error_puerto'] = "El puerto '" . $d['nombre_puerto'] . "' ya existe en este dispositivo.";
            header("Location: /documentador-red/detalles?id=" . $id_equipo);
            exit();
        }

        $stmt = $db->prepare("INSERT INTO puertos (id_equipo, nombre_puerto, estado, modo_puerto, vlan, direccion_ip, destino) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_equipo, $d['nombre_puerto'], $d['estado'], $d['modo_puerto'], $d['vlan'], $d['direccion_ip'], $d['destino']]);

        Log::registrar('Cableado', 'Alta de Interfaz', "Se agregó el puerto {$d['nombre_puerto']} al equipo ID {$id_equipo}. Destino: {$d['destino']}.");

        header("Location: /documentador-red/detalles?id=" . $id_equipo);
        exit();
    }

    public static function eliminarPuerto() {
        Auth::require(Auth::STAFF);

        $id_puerto = Validar::entero($_POST['id_puerto'] ?? null);
        $id_equipo = Validar::entero($_POST['id_equipo'] ?? null);
        if ($id_puerto === null || $id_equipo === null) {
            header("Location: /documentador-red/inventario");
            exit();
        }

        require_once __DIR__ . '/../core/database.php';
        $db = Database::conectar();

        // El puerto debe pertenecer al equipo indicado
        $stmt = $db->prepare("DELETE FROM puertos WHERE id = ? AND id_equipo = ?");
        $stmt->execute([$id_puerto, $id_equipo]);

        Log::registrar('Cableado', 'Desconexión/Eliminación', "Se eliminó el puerto con ID interno {$id_puerto} del equipo ID {$id_equipo}.");

        header("Location: /documentador-red/detalles?id=" . $id_equipo);
        exit();
    }

    // Edición de un puerto
    public static function modificarPuerto() {
        Auth::require(Auth::STAFF);

        $id_equipo = Validar::entero($_POST['id_equipo'] ?? null);
        $id_puerto = Validar::entero($_POST['id_puerto'] ?? null);
        [$d, $error] = self::leerDatosPuerto();
        if ($id_equipo === null || $id_puerto === null || $error !== null) {
            $_SESSION['error_puerto'] = "Datos del puerto no válidos.";
            header("Location: /documentador-red/detalles?id=" . (int)$id_equipo);
            exit();
        }

        Equipo::actualizarPuerto($id_puerto, $d['nombre_puerto'], $d['estado'], $d['modo_puerto'], $d['vlan'], $d['direccion_ip'], $d['destino']);

        Log::registrar('Cableado', 'Edición de Interfaz', "Se modificó el puerto {$d['nombre_puerto']} (Equipo ID: {$id_equipo}). Estado: {$d['estado']}, Destino: {$d['destino']}.");

        header("Location: /documentador-red/detalles?id=" . $id_equipo);
        exit();
    }

    // Eliminación de un equipo (solo Administrador)
    public static function eliminarEquipo() {
        Auth::require(Auth::ADMIN);

        $id = Validar::entero($_POST['id_equipo'] ?? null);
        if ($id === null) {
            header("Location: /documentador-red/inventario");
            exit();
        }

        Equipo::eliminar($id);

        Log::registrar('Inventario', 'Eliminación de Dispositivo', "Se eliminó permanentemente el equipo con ID interno: {$id}.");

        header("Location: /documentador-red/inventario?mensaje=eliminado");
        exit();
    }

    // Actualización de los datos de un equipo
    public static function actualizarEquipo() {
        Auth::require(Auth::STAFF);

        $id_equipo = Validar::entero($_POST['id_equipo'] ?? null);
        [$d, $error] = self::leerDatosEquipo();
        if ($id_equipo === null || $error !== null) {
            header("Location: /documentador-red/detalles?id=" . (int)$id_equipo . "&mensaje=error");
            exit();
        }

        // Si se ingresó una contraseña nueva, se cifra; si no, no se toca la guardada
        $ssh_password_encrypted = null;
        if ($d['ssh_pass'] !== null) {
            $ssh_password_encrypted = vault_encrypt_credential($d['ssh_pass']);
            if ($ssh_password_encrypted === null) {
                header("Location: /documentador-red/detalles?id=" . $id_equipo . "&mensaje=error_cifrado");
                exit();
            }
        }

        Equipo::actualizar($id_equipo, $d['hostname'], $d['marca'], $d['modelo'], $d['tipo'], $d['plantilla_conexion'],
                           $d['ip_gestion'], $d['ubicacion'], $d['comentarios'], $d['ssh_user'], $ssh_password_encrypted);

        $detalles = "Se editaron los datos del equipo {$d['hostname']}.";
        if ($d['ssh_pass'] !== null) {
            $detalles .= " [ALERTA: Se modificó la contraseña en la bóveda SSH]";
        }
        Log::registrar('Inventario', 'Actualización de Dispositivo', $detalles);

        header("Location: /documentador-red/detalles?id=" . $id_equipo);
        exit();
    }
}
