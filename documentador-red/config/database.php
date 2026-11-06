<?php
// C:\xampp\htdocs\documentador-red\config\database.php

$envPath = __DIR__ . '/../.env';
$env = file_exists($envPath) ? parse_ini_file($envPath) : [];

// La variable de entorno del contenedor tiene prioridad sobre el archivo .env.
$valor = function (string $clave, $defecto = null) use ($env) {
    $e = getenv($clave);
    return ($e !== false && $e !== '') ? $e : ($env[$clave] ?? $defecto);
};

return [
    'host' => $valor('MYSQL_HOST', 'db'),
    'dbname' => $valor('MYSQL_DATABASE', 'red_infraestructura'),
    'user' => $valor('MYSQL_USER', ''),
    // MYSQL_PASSWORD (usuario de aplicación); MYSQL_ROOT_PASSWORD solo por compatibilidad.
    'password' => $valor('MYSQL_PASSWORD', $valor('MYSQL_ROOT_PASSWORD', '')),
    'charset' => 'utf8mb4'
];