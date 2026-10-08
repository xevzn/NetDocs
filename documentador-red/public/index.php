<?php
// C:\xampp\htdocs\documentador-red\public\index.php

// --- 1. ENCENDEMOS EL RADAR DE ERRORES (Solo para etapa de desarrollo) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 2. REQUERIMOS LOS ARCHIVOS EN MINÚSCULAS ---
// Nota cómo ahora 'core' y 'database.php' están en minúsculas para coincidir con tus carpetas
require_once __DIR__ . '/../app/core/database.php';

// --- 3. ACTIVACION DEL LOG DE SEGURIDAD ---
require_once __DIR__ . '/../app/controllers/waf.php'; // Ajusta la ruta si es necesario
WAF::proteger();

// --- 4. ENRUTADOR SIMPLIFICADO ---
$uri_completa = $_SERVER['REQUEST_URI'];
// Le quitamos el nombre de la carpeta base para saber a dónde quiere ir el usuario
$ruta = str_replace('/documentador-red', '', $uri_completa);
// Limpiamos parámetros extra si los hay
$ruta = strtok($ruta, '?');

// --- ARCHIVOS ESTÁTICOS ---
// Permitimos que imágenes, CSS, JS, etc. se sirvan directamente
// sin pasar por el sistema de autenticación.

$archivo_estatico = __DIR__ . $ruta;

if (is_file($archivo_estatico)) {

    $extensiones = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'css'  => 'text/css',
        'js'   => 'application/javascript'
    ];

    $extension = strtolower(pathinfo($archivo_estatico, PATHINFO_EXTENSION));

    if (isset($extensiones[$extension])) {
        header('Content-Type: ' . $extensiones[$extension]);
        readfile($archivo_estatico);
        exit;
    }
}

if ($ruta === '/login-procesar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/authcontroller.php';
    AuthController::procesarLogin();

} elseif ($ruta === '/registro' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/views/auth/registro.php';

} elseif ($ruta === '/registro-procesar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/authcontroller.php';
    AuthController::procesarRegistro();

}elseif ($ruta === '/dashboard' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/dashboardcontroller.php';
    DashboardController::index();

}elseif ($ruta === '/inventario' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::listar(); 

} elseif ($ruta === '/logout' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/authcontroller.php';
    AuthController::logout();

}elseif ($ruta === '/alta-equipo' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::crear();

} elseif ($ruta === '/alta-equipo-procesar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::guardar();

} elseif ($ruta === '/equipo-actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::actualizarEquipo();

// --- RUTAS DE ETIQUETAS ---
} elseif ($ruta === '/etiqueta' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::etiqueta(); 

// --- NUEVA RUTA PARA CERRAR SESIÓN ---
} elseif ($ruta === '/detalles' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::detalles();
// --- NUEVA RUTA AISLADA PARA EL ESCÁNER CELULAR ---
} elseif ($ruta === '/escaner' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::escaner(); 

} elseif ($ruta === '/vlans' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/vlancontroller.php';
    VlanController::index();

} elseif ($ruta === '/vlans-procesar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/vlancontroller.php';
    VlanController::guardar();
    
} elseif ($ruta === '/vlans-eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/vlancontroller.php';
    VlanController::eliminarVlan(); 

// --- RUTAS DE CABLEADO ---
} elseif ($ruta === '/cableado' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/cablecontroller.php';
    CableController::index();

} elseif ($ruta === '/cableado-etiqueta' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/cablecontroller.php';
    CableController::etiqueta();

// --- RUTAS PARA GESTIÓN DE PUERTOS ---
} elseif ($ruta === '/puerto-guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::guardarPuerto();

} elseif ($ruta === '/puerto-eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::eliminarPuerto();

} elseif ($ruta === '/puerto-modificar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::modificarPuerto();

} elseif ($ruta === '/mapa-ips' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/vlancontroller.php';
    VlanController::mapa();

} elseif ($ruta === '/ip-guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/vlancontroller.php';
    VlanController::guardarIp();    

} elseif ($ruta === '/equipo-eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/equipocontroller.php';
    EquipoController::eliminarEquipo();

// --- RUTAS DE AUTOMATIZACIÓN ---
} elseif ($ruta === '/auditoria-ansible' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::auditoriaAnsible();

} elseif ($ruta === '/ejecutar-orquestador' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::ejecutarOrquestador();

} elseif ($ruta === '/ejecutar-auditoria' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::ejecutarAuditoria();

// NUEVA RUTA PARA RESPALDOS
} elseif ($ruta === '/respaldos') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::gestionRespaldos();

} elseif ($ruta === '/ejecutar-backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::ejecutarBackup();

// --- RUTAS DE USUARIOS ---
} elseif ($ruta === '/usuarios' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/usuariocontroller.php';
    UsuarioController::index();

} elseif ($ruta === '/usuario-guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/usuariocontroller.php';
    UsuarioController::guardar();

} elseif ($ruta === '/usuario-eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/usuariocontroller.php';
    UsuarioController::eliminar();

} elseif ($ruta === '/usuario-actualizar-pass' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/usuariocontroller.php';
    UsuarioController::actualizarPassword();
    
} elseif ($ruta === '/buscar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/busquedacontroller.php';
    BusquedaController::buscar();

// RUTAS NAPALM
} elseif ($ruta === '/descubrimiento-napalm' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/views/automatizacion/descubrimiento_napalm.php';

} elseif ($ruta === '/ejecutar-napalm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::ejecutarNapalm();

// RUTA MAPA DE RED
} elseif ($ruta === '/mapa-de-red' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/topologiacontroller.php';
    TopologiaController::index();

// RUTAS BUSCADOR ARP
} elseif ($ruta === '/buscador-arp' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/views/automatizacion/buscador_arp.php';

} elseif ($ruta === '/ejecutar-rastreo-arp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::ejecutarRastreoArp();

// RUTAS GESTIÓN DE VLANs
} elseif ($ruta === '/gestion-vlans' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/views/automatizacion/gestion_vlans.php';

} elseif ($ruta === '/ejecutar-vlan-info' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::consultarVlan();

} elseif ($ruta === '/ejecutar-vlan-cambio' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::aplicarCambioVlan();

} elseif ($ruta === '/ejecutar-vlan-rastreo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::rastrearParaVlan();

} elseif ($ruta === '/api-estadisticas' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::obtenerEstadisticas();

} elseif ($ruta === '/estadisticas') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::verEstadisticas();
}
 elseif ($ruta === '/ejecutar-snmp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php'; 
    AutomatizacionController::ejecutarSnmp();

// Ruta para consultar un equipo en tiempo real
} elseif ($ruta === '/api/snmp-live' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::apiLiveSnmp();

// Vista del Monitor en Vivo
} elseif ($ruta === '/monitor_vivo' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../app/controllers/automatizacioncontroller.php';
    AutomatizacionController::monitorVivo();

} elseif ($ruta === '/logs') {
    require_once __DIR__ . '/../app/controllers/logcontroller.php';
    LogController::index();

} else {
    // Si la ruta es '/', o cualquier otra cosa, mostramos el login
    try {
        $db = Database::conectar(); 
        require_once __DIR__ . '/../app/views/auth/login.php';
    } catch (Exception $e) {
        // Ahora si la base de datos falla, nos dirá exactamente por qué
        echo "<div style='color: white; background: red; padding: 20px;'>
                <b>Error Crítico:</b> Sin conexión a Base de Datos.<br>
                Detalle técnico: " . $e->getMessage() . "
              </div>";
    }
}