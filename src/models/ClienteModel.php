<?php

require_once __DIR__ . '/MantenimientoModel.php';

/**
 * Consulta las motos y garantías asociadas al documento de un cliente.
 */
function consultar_garantia_cliente(PDO $pdo, string $documento): ?array
{
    $doc = trim($documento);
    if ($doc === '') {
        return null;
    }

    $stmtCliente = $pdo->prepare(
        'SELECT id_cliente, nombre, documento, telefono, correo
         FROM cliente
         WHERE documento = :documento'
    );
    $stmtCliente->execute([':documento' => $doc]);
    $cliente = $stmtCliente->fetch();

    if (!$cliente) {
        return ['cliente' => null, 'motos' => []];
    }

    $motos = obtener_motos_con_garantia($pdo, $doc);
    $motosCliente = array_values(array_filter(
        $motos,
        fn(array $moto): bool => (int)$moto['id_cliente'] === (int)$cliente['id_cliente']
    ));

    return [
        'cliente' => $cliente,
        'motos' => $motosCliente,
    ];
}
