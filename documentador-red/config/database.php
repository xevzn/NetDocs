<?php
// C:\xampp\htdocs\documentador-red\config\database.php

$envPath = __DIR__ . '/../.env';
$env = file_exists($envPath) ? parse_ini_file($envPath) : [];

return [
    'host' => $env['MYSQL_HOST'] ?? 'db',
    'dbname' => $env['MYSQL_DATABASE'] ?? 'red_infraestructura', 
    'user' => $env['MYSQL_USER'] ?? '', 
    'password' => $env['MYSQL_ROOT_PASSWORD'] ?? '', 
    'charset' => 'utf8mb4'
];