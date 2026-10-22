<?php

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST'), getenv('DB_NAME'));

try {
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "<h1>MOTOCONS</h1><p>✅ PHP " . PHP_VERSION . " conectado a MySQL</p>";
} catch (PDOException $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}