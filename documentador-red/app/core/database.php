<?php
// C:\xampp\htdocs\documentador-red\app\Core\Database.php

class Database {
    private static $conexion = null;

    public static function conectar() {
        // Si ya hay una conexión, la devolvemos (Patrón Singleton para ahorrar memoria)
        if (self::$conexion !== null) {
            return self::$conexion;
        }

        // Cargamos las credenciales
        $config = require __DIR__ . '/../../config/database.php';

        try {
            $dsn = "mysql:host=" . $config['host'] . ";dbname=" . $config['dbname'] . ";charset=" . $config['charset'];
            
            // Opciones de seguridad y manejo de errores para PDO
            $opciones = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Que los errores lancen excepciones
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Devolver los datos como un arreglo asociativo
                PDO::ATTR_EMULATE_PREPARES => false, // Evitar emulación para mayor seguridad contra Inyección SQL
            ];

            self::$conexion = new PDO($dsn, $config['user'], $config['password'], $opciones);
            return self::$conexion;

        } catch (PDOException $e) {
            // En producción aquí guardaríamos el error en un log de texto
            die("Error crítico: No se pudo conectar a la base de datos de infraestructura.");
        }
    }
}