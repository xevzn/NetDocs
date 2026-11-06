<?php
// app/core/routes.php
// Tabla única de rutas. Cada ruta declara método(s) HTTP y la lista blanca de roles.
//   'r' => null            -> ruta pública (solo login)
//   'r' => [1, 2, 3]       -> Administrador, Técnico, Lector
//   'api' => true          -> responde JSON ante 401/403
//   'c' => [archivo, Clase, método]   |   'v' => vista directa
//
// Para añadir una ruta nueva hay que decidir aquí quién puede usarla:
// el enrutador rechaza cualquier ruta sin esta tabla.

if (!defined('R_TODOS')) {
    define('R_TODOS', [1, 2, 3]);
    define('R_STAFF', [1, 2]);   // Administrador y Técnico
    define('R_ADMIN', [1]);
}

return [
    // --- Públicas ---
    '/login-procesar'        => ['m' => 'POST', 'r' => null, 'c' => ['authcontroller', 'AuthController', 'procesarLogin']],

    // --- Sesión ---
    '/logout'                => ['m' => 'POST', 'r' => R_TODOS, 'c' => ['authcontroller', 'AuthController', 'logout']],

    // --- Dashboard / consulta ---
    '/dashboard'             => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['dashboardcontroller', 'DashboardController', 'index']],
    '/inventario'            => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['equipocontroller', 'EquipoController', 'listar']],
    '/detalles'              => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['equipocontroller', 'EquipoController', 'detalles']],
    '/etiqueta'              => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['equipocontroller', 'EquipoController', 'etiqueta']],
    '/escaner'               => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['equipocontroller', 'EquipoController', 'escaner']],
    '/buscar'                => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['busquedacontroller', 'BusquedaController', 'buscar']],
    '/cableado'              => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['cablecontroller', 'CableController', 'index']],
    '/cableado-etiqueta'     => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['cablecontroller', 'CableController', 'etiqueta']],
    '/vlans'                 => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['vlancontroller', 'VlanController', 'index']],
    '/mapa-ips'              => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['vlancontroller', 'VlanController', 'mapa']],
    '/mapa-de-red'           => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['topologiacontroller', 'TopologiaController', 'index']],
    '/monitor_vivo'          => ['m' => 'GET',  'r' => R_TODOS, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'monitorVivo']],
    '/estadisticas'          => ['m' => ['GET'], 'r' => R_TODOS, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'verEstadisticas']],
    '/api-estadisticas'      => ['m' => 'GET',  'r' => R_TODOS, 'api' => true, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'obtenerEstadisticas']],
    '/api/snmp-live'         => ['m' => 'GET',  'r' => R_TODOS, 'api' => true, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'apiLiveSnmp']],

    // --- Escritura de documentación (Administrador y Técnico; el Lector es solo lectura) ---
    '/alta-equipo'           => ['m' => 'GET',  'r' => R_STAFF, 'c' => ['equipocontroller', 'EquipoController', 'crear']],
    '/alta-equipo-procesar'  => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['equipocontroller', 'EquipoController', 'guardar']],
    '/equipo-actualizar'     => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['equipocontroller', 'EquipoController', 'actualizarEquipo']],
    '/puerto-guardar'        => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['equipocontroller', 'EquipoController', 'guardarPuerto']],
    '/puerto-eliminar'       => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['equipocontroller', 'EquipoController', 'eliminarPuerto']],
    '/puerto-modificar'      => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['equipocontroller', 'EquipoController', 'modificarPuerto']],
    '/vlans-procesar'        => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['vlancontroller', 'VlanController', 'guardar']],
    '/ip-guardar'            => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['vlancontroller', 'VlanController', 'guardarIp']],

    // --- Borrados (solo Administrador) ---
    '/equipo-eliminar'       => ['m' => 'POST', 'r' => R_ADMIN, 'c' => ['equipocontroller', 'EquipoController', 'eliminarEquipo']],
    '/vlans-eliminar'        => ['m' => 'POST', 'r' => R_ADMIN, 'c' => ['vlancontroller', 'VlanController', 'eliminarVlan']],

    // --- Usuarios (solo Administrador) ---
    '/usuarios'              => ['m' => 'GET',  'r' => R_ADMIN, 'c' => ['usuariocontroller', 'UsuarioController', 'index']],
    '/usuario-guardar'       => ['m' => 'POST', 'r' => R_ADMIN, 'c' => ['usuariocontroller', 'UsuarioController', 'guardar']],
    '/usuario-eliminar'      => ['m' => 'POST', 'r' => R_ADMIN, 'c' => ['usuariocontroller', 'UsuarioController', 'eliminar']],
    '/usuario-actualizar-pass' => ['m' => 'POST', 'r' => R_ADMIN, 'c' => ['usuariocontroller', 'UsuarioController', 'actualizarPassword']],
    '/logs'                  => ['m' => 'GET',  'r' => R_ADMIN, 'c' => ['logcontroller', 'LogController', 'index']],

    // --- Automatización: actúa sobre equipos reales (Administrador y Técnico) ---
    '/auditoria-ansible'     => ['m' => 'GET',  'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'auditoriaAnsible']],
    '/ejecutar-orquestador'  => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'ejecutarOrquestador']],
    '/ejecutar-auditoria'    => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'ejecutarAuditoria']],
    '/ejecutar-snmp'         => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'ejecutarSnmp']],
    '/respaldos'             => ['m' => ['GET', 'POST'], 'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'gestionRespaldos']],
    '/ejecutar-backup'       => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'ejecutarBackup']],
    '/descubrimiento-napalm' => ['m' => 'GET',  'r' => R_STAFF, 'v' => 'automatizacion/descubrimiento_napalm.php'],
    '/ejecutar-napalm'       => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'ejecutarNapalm']],
    '/buscador-arp'          => ['m' => 'GET',  'r' => R_STAFF, 'v' => 'automatizacion/buscador_arp.php'],
    '/ejecutar-rastreo-arp'  => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'ejecutarRastreoArp']],
    '/gestion-vlans'         => ['m' => 'GET',  'r' => R_STAFF, 'v' => 'automatizacion/gestion_vlans.php'],
    '/ejecutar-vlan-info'    => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'consultarVlan']],
    '/ejecutar-vlan-cambio'  => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'aplicarCambioVlan']],
    '/ejecutar-vlan-rastreo' => ['m' => 'POST', 'r' => R_STAFF, 'c' => ['automatizacioncontroller', 'AutomatizacionController', 'rastrearParaVlan']],
];
