<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/MantenimientoModel.php';
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../helpers/formato.php';

function mostrar_garantia(): void
{
    $pdo = obtener_conexion();
    $documento = trim((string)($_GET['documento'] ?? ''));
    $resultado = $documento !== ''
        ? consultar_garantia_cliente($pdo, $documento)
        : null;

    require __DIR__ . '/../views/garantia/index.php';
}
