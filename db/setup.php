<?php
// db/setup.php
// Prepara la base de datos local:
//   1. La crea si no existe.
//   2. Carga db/init (esquema + datos de prueba).
//   3. Aplica las migraciones pendientes de db/migrations.
//
// Uso: php db/setup.php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Este script solo se ejecuta desde la consola.\n");
}

require_once __DIR__ . '/../src/config/db.php';

function env_o(string $nombre, string $defecto): string
{
    $valor = getenv($nombre);

    return $valor === false ? $defecto : $valor;
}

function ejecutar_sql(PDO $pdo, string $ruta): void
{
    $sql = file_get_contents($ruta);

    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Archivo vacío: ' . basename($ruta));
    }

    $resultado = $pdo->query($sql);

    if ($resultado !== false) {
        while ($resultado->nextRowset()) {
        }
        $resultado->closeCursor();
    }
}

function tabla_existe(PDO $pdo, string $tabla): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla'
    );
    $stmt->execute([':tabla' => $tabla]);

    return (int) $stmt->fetchColumn() > 0;
}

$host    = env_o('DB_HOST', '127.0.0.1');
$puerto  = env_o('DB_PORT', '3306');
$base    = env_o('DB_NAME', 'motocons');
$usuario = env_o('DB_USER', 'root');
$clave   = env_o('DB_PASS', '');

if (preg_match('/^[A-Za-z0-9_]+$/', $base) !== 1) {
    exit("Nombre de base inválido: {$base}\n");
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$puerto};charset=utf8mb4",
        $usuario,
        $clave,
        [
            PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES       => true,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );

    $pdo->exec(
        "CREATE DATABASE IF NOT EXISTS `{$base}`
         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    $pdo->exec("USE `{$base}`");

    echo "Base: {$base} ({$host}:{$puerto})\n";

    // Primera instalación: esquema y datos de prueba.
    if (!tabla_existe($pdo, 'mantenimiento')) {
        foreach (glob(__DIR__ . '/init/*.sql') ?: [] as $archivo) {
            echo 'Ejecutando init/' . basename($archivo) . "...\n";
            ejecutar_sql($pdo, $archivo);
        }

        echo "Listo. Base creada con esquema y datos de prueba.\n";
    }

    // Migraciones nuevas, solo si la carpeta tiene archivos.
    $migraciones = glob(__DIR__ . '/migrations/*.sql') ?: [];

    if ($migraciones === []) {
        echo "Todo al día. No hay migraciones.\n";
        exit(0);
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS migraciones (
            id INT NOT NULL AUTO_INCREMENT,
            archivo VARCHAR(255) NOT NULL,
            lote INT NOT NULL DEFAULT 1,
            aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_migraciones_archivo (archivo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $aplicadas = 0;

    foreach ($migraciones as $archivo) {
        $nombre = basename($archivo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM migraciones WHERE archivo = :archivo');
        $stmt->execute([':archivo' => $nombre]);

        if ((int) $stmt->fetchColumn() > 0) {
            continue;
        }

        echo "Aplicando migrations/{$nombre}...\n";
        ejecutar_sql($pdo, $archivo);

        $stmt = $pdo->prepare('INSERT INTO migraciones (archivo, lote) VALUES (:archivo, 1)');
        $stmt->execute([':archivo' => $nombre]);
        $aplicadas++;
    }

    echo $aplicadas > 0
        ? "Listo. Migraciones aplicadas: {$aplicadas}.\n"
        : "Todo al día. No hay migraciones pendientes.\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
