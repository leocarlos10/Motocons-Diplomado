<?php
// src/config/db.php

function obtener_conexion(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'db',
            getenv('DB_NAME') ?: 'motocons'
        );

        $pdo = new PDO(
            $dsn,
            getenv('DB_USER') ?: 'motocons_user',
            getenv('DB_PASS') ?: 'motocons_pass',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    return $pdo;
}
