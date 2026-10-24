<?php
// src/config/db.php

// En local, las credenciales se leen del .env de la raíz.
// En Docker, docker-compose ya define DB_* como variables de entorno, así que
// getenv() las encuentra primero y el .env se ignora.
$rutaEnv = dirname(__DIR__, 2) . '/.env';

if (is_file($rutaEnv)) {
    foreach (file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
        $linea = trim($linea);

        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
            continue;
        }

        [$clave, $valor] = explode('=', $linea, 2);

        if (trim($clave) !== '' && getenv(trim($clave)) === false) {
            putenv(trim($clave) . '=' . trim($valor, " \t\n\r\0\x0B\"'"));
        }
    }
}

function obtener_conexion(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // getenv() -> .env -> valores locales por defecto.
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: '127.0.0.1',
                getenv('DB_PORT') ?: '3306',
                getenv('DB_NAME') ?: 'motocons'
            ),
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    return $pdo;
}
