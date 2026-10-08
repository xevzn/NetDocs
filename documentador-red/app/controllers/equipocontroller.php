<?php
// C:\xampp\htdocs\documentador-red\app\controllers\EquipoController.php

require_once __DIR__ . '/../models/equipo.php';
require_once __DIR__ . '/../models/log.php';
require_once __DIR__ . '/../core/vault.php';

class EquipoController {
    // Muestra la tabla principal con todos los equipos
    public static function listar() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: /documentador-red/");
            exit();
        }

        // Le pedimos al Modelo (Equipo.php) que traiga todos los registros de la BD
        $equipos = Equipo::obtenerTodos();
        
        // Cargamos la vista pasándole los datos
        require_once __DIR__ . '/../views/infraestructura/inventario.php';
    }

    
    // Muestra el formulario HTML
    public static function crear() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: /documentador-red/");
            exit();
        }

        // --- BLOQUEO DE SEGURIDAD (RBAC) ---
        if ($_SESSION['rol_id'] > 2) {
            header("Location: /documentador-red/dashboard");
            exit();
        }

        require_once __DIR__ . '/../views/infraestructura/alta.php';
    }

    // Procesa los datos cuando se envía el formulario
    public static function guardar() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: /documentador-red/");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 1. Datos estándar de red
            $hostname = trim($_POST['hostname']);
            $marca = trim($_POST['marca']);
            $modelo = trim($_POST['modelo']);
            $tipo = $_POST['tipo'];
            $plantilla_conexion = $_POST['plantilla_conexion'] ?? 'cisco_ios_legacy';
            
            $ip_gestion = trim($_POST['ip_gestion']);
            $ubicacion = trim($_POST['ubicacion']);
            $comentarios = trim($_POST['comentarios']); 

            // 2. CAPTURA DE CREDENCIALES (Opcionales)
            $ssh_user = !empty($_POST['ssh_user']) ? trim($_POST['ssh_user']) : null;
            $ssh_pass = !empty($_POST['ssh_pass']) ? trim($_POST['ssh_pass']) : null;
            $ssh_password_encrypted = null;

            // 3. LÓGICA DE CIFRADO INTELIGENTE (Vault PHP)
            if ($ssh_pass !== null) {
                $ssh_password_encrypted = vault_encrypt_credential($ssh_pass);
                if ($ssh_password_encrypted === null) {
                    header("Location: /documentador-red/alta-equipo?mensaje=error");
                    exit();
                }
            }

            $exito = Equipo::registrar($hostname, $marca, $modelo, $tipo, $plantilla_conexion, $ip_gestion, $ubicacion, $comentarios, $ssh_user, $ssh_password_encrypted);

            if ($exito) {
                // LOG YA IMPLEMENTADO
                Log::registrar('Inventario', 'Alta de Dispositivo', "Se registró el equipo {$_POST['hostname']} ({$_POST['tipo']}). IP Gestión: {$_POST['ip_gestion']}.");
                header("Location: /documentador-red/alta-equipo?mensaje=exito");
            } else {
                header("Location: /documentador-red/alta-equipo?mensaje=error");
            }
            exit();
        }
    }

    // Muestra la vista de la etiqueta QR lista para imprimir
    public static function etiqueta() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: /documentador-red/");
            exit();
        }

        if (!isset($_GET['id'])) {
            die("Error: No se especificó el equipo a imprimir.");
        }

        $id = $_GET['id'];
        $equipo = Equipo::obtenerPorId($id);

        if (!$equipo) {
            die("Error: El equipo no existe en la base de datos.");
        }

        require_once __DIR__ . '/../views/infraestructura/etiqueta.php';
    }


    // Muestra los detalles completos de un equipo y sus puertos
    public static function detalles() {
        session_start();
        
        if (!isset($_SESSION['usuario_id'])) {
            $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
            header("Location: /documentador-red/");
            exit();
        }

        if (!isset($_GET['id'])) {
            die("Error: No se especificó el equipo a consultar.");
        }

        $id = $_GET['id'];
        
        $equipo = Equipo::obtenerPorId($id);
        if (!$equipo) {
            die("Error: El equipo no existe.");
        }

        $puertos = Equipo::obtenerPuertos($id);

        require_once __DIR__ . '/../views/infraestructura/detalles.php';
    }

    // Muestra SOLO la información al escanear el QR (Sin menús)
    public static function escaner() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) {
            $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
            header("Location: /documentador-red/");
            exit();
        }

        if (!isset($_GET['id'])) { die("Error: Equipo no especificado."); }

        $id = $_GET['id'];
        $equipo = Equipo::obtenerPorId($id);
        $puertos = Equipo::obtenerPuertos($id);

        if (!$equipo) { die("Error: El equipo no existe."); }

        require_once __DIR__ . '/../views/infraestructura/vista_qr.php';
    }


    // Procesa el alta de un nuevo puerto
    public static function guardarPuerto() {
        session_start();
 
        if ($_SESSION['rol_id'] > 2) die("Acceso denegado.");

        require_once __DIR__ . '/../core/database.php';
        $db = Database::conectar();

        $id_equipo = $_POST['id_equipo'];
        $nombre_puerto = $_POST['nombre_puerto'];
        $estado = $_POST['estado'];
        $modo_puerto = $_POST['modo_puerto'] ?? 'Acceso'; 
        $vlan = $_POST['vlan'] ?? null; 
        $direccion_ip = $_POST['direccion_ip'] ?? null;
        $destino = $_POST['destino'];

        // 1. VALIDACIÓN DE DUPLICADO
        $stmt_check = $db->prepare("SELECT id FROM puertos WHERE id_equipo = ? AND nombre_puerto = ?");
        $stmt_check->execute([$id_equipo, $nombre_puerto]);
        
        if ($stmt_check->fetch()) {
            $_SESSION['error_puerto'] = "El puerto '{$nombre_puerto}' ya existe en este dispositivo.";
            header("Location: /documentador-red/detalles?id=" . $id_equipo);
            exit();
        }

        // 2. INSERTAMOS CON LOS CAMPOS 'modo_puerto' y 'direccion_ip'
        $stmt = $db->prepare("INSERT INTO puertos (id_equipo, nombre_puerto, estado, modo_puerto, vlan, direccion_ip, destino) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_equipo, $nombre_puerto, $estado, $modo_puerto, $vlan, $direccion_ip, $destino]);

        // LOG IMPLEMENTADO: Creación de un enlace
        Log::registrar('Cableado', 'Alta de Interfaz', "Se agregó el puerto {$nombre_puerto} al equipo ID {$id_equipo}. Destino: {$destino}.");

        header("Location: /documentador-red/detalles?id=" . $id_equipo);
        exit();
    }

    public static function eliminarPuerto() {
        session_start();

        if ($_SESSION['rol_id'] > 2) die("Acceso denegado.");

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_puerto']) && isset($_POST['id_equipo'])) {
            require_once __DIR__ . '/../core/database.php';
            $db = Database::conectar();
            
            $id_puerto = $_POST['id_puerto'];
            $id_equipo = $_POST['id_equipo'];

            $stmt = $db->prepare("DELETE FROM puertos WHERE id = ?");
            $stmt->execute([$id_puerto]);

            // LOG IMPLEMENTADO: Eliminación de un enlace
            Log::registrar('Cableado', 'Desconexión/Eliminación', "Se eliminó el puerto con ID interno {$id_puerto} del equipo ID {$id_equipo}.");

            header("Location: /documentador-red/detalles?id=" . $id_equipo);
            exit();
        }
    }

    // Procesa la edición de un puerto
    public static function modificarPuerto() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) { header("Location: /documentador-red/"); exit(); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_equipo = $_POST['id_equipo'];
            $id_puerto = $_POST['id_puerto'];
            $nombre_puerto = trim($_POST['nombre_puerto']);
            $estado = $_POST['estado'];

            $modo_puerto = $_POST['modo_puerto'] ?? 'Acceso';
            
            $vlan = trim($_POST['vlan'] ?? ''); 
            if (empty($vlan)) $vlan = null; 
            
            $direccion_ip = trim($_POST['direccion_ip'] ?? '');
            if (empty($direccion_ip)) $direccion_ip = null;
            
            $destino = trim($_POST['destino']);

            Equipo::actualizarPuerto($id_puerto, $nombre_puerto, $estado, $modo_puerto, $vlan, $direccion_ip, $destino);
            
            // LOG IMPLEMENTADO: Edición de un enlace (ej. cambiar de Access a Trunk)
            Log::registrar('Cableado', 'Edición de Interfaz', "Se modificó el puerto {$nombre_puerto} (Equipo ID: {$id_equipo}). Estado: {$estado}, Destino: {$destino}.");

            header("Location: /documentador-red/detalles?id=" . $id_equipo);
            exit();
        }
    }
    
    // Procesa la eliminación de un equipo
    public static function eliminarEquipo() {
        session_start();
        if (!isset($_SESSION['usuario_id'])) { header("Location: /documentador-red/"); exit(); }

        // --- BLOQUEO DE SEGURIDAD CRÍTICO ---
        if ($_SESSION['rol_id'] != 1) {
            header("Location: /documentador-red/inventario");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_equipo'])) {
            Equipo::eliminar($_POST['id_equipo']);

            // LOG IMPLEMENTADO: Eliminación crítica
            Log::registrar('Inventario', 'Eliminación de Dispositivo', "Se eliminó permanentemente el equipo con ID interno: {$_POST['id_equipo']}.");

            header("Location: /documentador-red/inventario?mensaje=eliminado");
            exit();
        }
    }

    // Procesa la actualización de datos de un equipo (Edición)
    public static function actualizarEquipo() {
        session_start();
        
        // Seguridad: Solo Admin (1) o Técnico (2)
        if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] > 2) {
            header("Location: /documentador-red/dashboard");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_equipo = $_POST['id_equipo'];
            $hostname = trim($_POST['hostname']);
            $marca = trim($_POST['marca']);
            $modelo = trim($_POST['modelo']);
            $tipo = $_POST['tipo'];
            $plantilla_conexion = $_POST['plantilla_conexion'] ?? 'cisco_ios_legacy';
            $ip_gestion = trim($_POST['ip_gestion']);
            $ubicacion = trim($_POST['ubicacion']);
            $comentarios = trim($_POST['comentarios']); 

            // Datos de la bóveda
            $ssh_user = !empty($_POST['ssh_user']) ? trim($_POST['ssh_user']) : null;
            $ssh_pass = !empty($_POST['ssh_pass']) ? trim($_POST['ssh_pass']) : null;
            $ssh_password_encrypted = null;

            // Si se ingresó una contraseña nueva, la ciframos
            if ($ssh_pass !== null) {
                $ssh_password_encrypted = vault_encrypt_credential($ssh_pass);
                if ($ssh_password_encrypted === null) {
                    header("Location: /documentador-red/detalles?id=" . $id_equipo . "&mensaje=error_cifrado");
                    exit();
                }
            }

            // Enviamos todo al modelo
            Equipo::actualizar($id_equipo, $hostname, $marca, $modelo, $tipo, $plantilla_conexion, $ip_gestion, $ubicacion, $comentarios, $ssh_user, $ssh_password_encrypted);

            // LOG YA IMPLEMENTADO
            $detalles = "Se editaron los datos del equipo {$_POST['hostname']}.";
            if (!empty($_POST['ssh_pass'])) {
                $detalles .= " [ALERTA: Se modificó la contraseña en la bóveda SSH]";
            }
            
            Log::registrar('Inventario', 'Actualización de Dispositivo', $detalles);
            
            // Redirigimos de vuelta a la página de detalles
            header("Location: /documentador-red/detalles?id=" . $id_equipo);
            exit();
        }
    }
}