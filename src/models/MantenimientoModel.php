<?php
// src/models/MantenimientoModel.php

const VALOR_MANO_OBRA_SIN_GARANTIA = 30000;
const TOPE_MANTENIMIENTOS_GARANTIA = 3;

/**
 * Obtiene los mecánicos activos para el desplegable.
 */
function obtener_mecanicos(PDO $pdo): array
{
    $sql = "SELECT id_empleado, nombre, documento 
            FROM empleado 
            WHERE LOWER(rol) LIKE 'mecanico%' AND activo = 1 
            ORDER BY nombre ASC";
    $stmt = $pdo->query($sql);
    $mecanicos = $stmt->fetchAll();

    // Respaldo en caso de que el rol tenga otra denominación en la tabla empleado
    if (empty($mecanicos)) {
        $stmt = $pdo->query("SELECT id_empleado, nombre, documento FROM empleado WHERE activo = 1 ORDER BY nombre ASC");
        $mecanicos = $stmt->fetchAll();
    }

    return $mecanicos;
}

/**
 * Obtiene las motos vendidas junto con su dueño, conteo de garantías y sus facturas.
 * Permite filtrar por placa, documento o nombre del cliente (HU-08 y HU-09).
 */
function obtener_motos_con_garantia(PDO $pdo, string $busqueda = ''): array
{
    $sql = "
        SELECT 
            m.id_moto,
            m.placa,
            m.chasis,
            r.nombre AS referencia_modelo,
            r.tipo_propulsion,
            ma.nombre AS marca_nombre,
            c.id_cliente,
            c.nombre AS cliente_nombre,
            c.documento AS cliente_documento,
            COUNT(mant.id_mantenimiento) AS total_servicios,
            COALESCE(SUM(CASE WHEN mant.es_garantia = 1 THEN 1 ELSE 0 END), 0) AS garantias_usadas
        FROM motocicleta m
        INNER JOIN referencia_moto r ON r.id_referencia = m.id_referencia
        INNER JOIN marca ma          ON ma.id_marca = r.id_marca
        INNER JOIN detalle_venta dv  ON dv.id_moto = m.id_moto
        INNER JOIN venta v           ON v.id_venta = dv.id_venta
        INNER JOIN cliente c         ON c.id_cliente = v.id_cliente
        LEFT JOIN mantenimiento mant ON mant.id_moto = m.id_moto
        WHERE (:busqueda1 = '' 
               OR m.placa LIKE :busqueda2 
               OR c.documento LIKE :busqueda3
               OR c.nombre LIKE :busqueda4)
        GROUP BY 
            m.id_moto, m.placa, m.chasis, r.nombre, r.tipo_propulsion, 
            ma.nombre, c.id_cliente, c.nombre, c.documento
        ORDER BY m.placa ASC
    ";

    $like = '%' . trim($busqueda) . '%';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':busqueda1' => trim($busqueda),
        ':busqueda2' => $like,
        ':busqueda3' => $like,
        ':busqueda4' => $like,
    ]);

    $motos = $stmt->fetchAll();

    foreach ($motos as &$moto) {
        $usadas = min((int)$moto['garantias_usadas'], TOPE_MANTENIMIENTOS_GARANTIA);
        $moto['garantias_usadas']    = $usadas;
        $moto['garantias_restantes'] = max(0, TOPE_MANTENIMIENTOS_GARANTIA - $usadas);
        $moto['garantia_vigente']    = $usadas < TOPE_MANTENIMIENTOS_GARANTIA;
        $moto['facturas']            = obtener_facturas_por_moto($pdo, (int)$moto['id_moto']);
    }
    unset($moto);

    return $motos;
}

/**
 * Obtiene el historial de mantenimientos (facturas) y sus líneas de detalle para una moto.
 */
function obtener_facturas_por_moto(PDO $pdo, int $idMoto): array
{
    $sqlMant = "
        SELECT 
            mant.id_mantenimiento,
            mant.numero_servicio,
            mant.es_garantia,
            mant.fecha,
            mant.valor_mano_obra,
            mant.total,
            e.nombre AS mecanico_nombre
        FROM mantenimiento mant
        INNER JOIN empleado e ON e.id_empleado = mant.id_empleado
        WHERE mant.id_moto = :id_moto
        ORDER BY mant.numero_servicio ASC, mant.id_mantenimiento ASC
    ";
    $stmt = $pdo->prepare($sqlMant);
    $stmt->execute([':id_moto' => $idMoto]);
    $mantenimientos = $stmt->fetchAll();

    if (empty($mantenimientos)) {
        return [];
    }

    $stmtDet = $pdo->prepare("
        SELECT id_detalle, tipo, descripcion, cantidad, valor_unitario
        FROM detalle_mantenimiento
        WHERE id_mantenimiento = :id_mant
        ORDER BY id_detalle ASC
    ");

    foreach ($mantenimientos as &$mant) {
        $stmtDet->execute([':id_mant' => $mant['id_mantenimiento']]);
        $mant['lineas'] = $stmtDet->fetchAll();
    }
    unset($mant);

    return $mantenimientos;
}

/**
 * Registra un nuevo mantenimiento dentro de una transacción PDO.
 */
function registrar_mantenimiento(PDO $pdo, array $post): array
{
    $idMoto     = (int)($post['moto_id'] ?? 0);
    $idMecanico = (int)($post['mecanico_id'] ?? 0);
    $fecha      = trim($post['fecha'] ?? '');

    $tipos         = $post['linea_tipo']  ?? [];
    $descripciones = $post['linea_desc']  ?? [];
    $cantidades    = $post['linea_cant']  ?? [];
    $valores       = $post['linea_valor'] ?? [];

    if ($idMoto <= 0) {
        throw new InvalidArgumentException('Debe seleccionar una motocicleta válida.');
    }
    if ($idMecanico <= 0) {
        throw new InvalidArgumentException('Debe seleccionar un mecánico responsable.');
    }
    if ($fecha === '') {
        throw new InvalidArgumentException('La fecha de servicio es obligatoria.');
    }

    // Filtrar filas vacías y validar líneas con datos
    $lineasValidas = [];
    $totalFilas = count($descripciones);

    for ($i = 0; $i < $totalFilas; $i++) {
        $desc  = trim((string)($descripciones[$i] ?? ''));
        $tipo  = trim((string)($tipos[$i] ?? 'Insumo'));
        $cant  = $cantidades[$i] ?? '';
        $valor = $valores[$i] ?? '';

        if ($desc === '' && ($valor === '' || $valor === null)) {
            continue;
        }

        $numLinea = $i + 1;
        if ($desc === '') {
            throw new InvalidArgumentException("En la línea {$numLinea} falta ingresar la descripción.");
        }
        if (!in_array($tipo, ['Repuesto', 'Insumo'], true)) {
            throw new InvalidArgumentException("El tipo en la línea {$numLinea} no es válido.");
        }
        if (!is_numeric($cant) || (int)$cant <= 0) {
            throw new InvalidArgumentException("Error en la línea {$numLinea}: la cantidad debe ser mayor a 0.");
        }
        if (!is_numeric($valor) || (float)$valor < 0) {
            throw new InvalidArgumentException("Error en la línea {$numLinea}: el valor unitario debe ser mayor o igual a 0.");
        }

        $lineasValidas[] = [
            'tipo'           => $tipo,
            'descripcion'    => $desc,
            'cantidad'       => (int)$cant,
            'valor_unitario' => (float)$valor,
        ];
    }

    if (empty($lineasValidas)) {
        throw new InvalidArgumentException('Debe ingresar al menos un repuesto o insumo válido para registrar el mantenimiento.');
    }

    $pdo->beginTransaction();

    try {
        // 1. Verificar que la moto exista y esté vendida (bloqueando la fila durante la transacción)
        $stmtMoto = $pdo->prepare("
            SELECT m.id_moto, m.placa 
            FROM motocicleta m
            INNER JOIN detalle_venta dv ON dv.id_moto = m.id_moto
            WHERE m.id_moto = :id_moto
            FOR UPDATE
        ");
        $stmtMoto->execute([':id_moto' => $idMoto]);
        $moto = $stmtMoto->fetch();

        if (!$moto) {
            throw new RuntimeException('La motocicleta seleccionada no existe o no registra una venta activa.');
        }

        // 2. Verificar que el mecánico exista
        $stmtMec = $pdo->prepare("SELECT id_empleado FROM empleado WHERE id_empleado = :id_emp");
        $stmtMec->execute([':id_emp' => $idMecanico]);
        if (!$stmtMec->fetch()) {
            throw new RuntimeException('El mecánico seleccionado no existe en el sistema.');
        }

        // 3. Contar mantenimientos previos y calcular reglas de negocio
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM mantenimiento WHERE id_moto = :id_moto");
        $stmtCount->execute([':id_moto' => $idMoto]);
        $serviciosPrevios = (int)$stmtCount->fetchColumn();

        $numeroServicio = $serviciosPrevios + 1;
        $esGarantia     = ($numeroServicio <= TOPE_MANTENIMIENTOS_GARANTIA) ? 1 : 0;
        $valorManoObra  = $esGarantia ? 0.00 : (float)VALOR_MANO_OBRA_SIN_GARANTIA;

        // 4. Insertar cabecera en `mantenimiento`
        $stmtInsertMant = $pdo->prepare("
            INSERT INTO mantenimiento (numero_servicio, es_garantia, fecha, valor_mano_obra, total, id_moto, id_empleado)
            VALUES (:num, :garantia, :fecha, :mano_obra, 0, :id_moto, :id_empleado)
        ");
        $stmtInsertMant->execute([
            ':num'         => $numeroServicio,
            ':garantia'    => $esGarantia,
            ':fecha'       => $fecha,
            ':mano_obra'   => $valorManoObra,
            ':id_moto'     => $idMoto,
            ':id_empleado' => $idMecanico,
        ]);

        $idMantenimiento = (int)$pdo->lastInsertId();

        // 5. Insertar líneas en `detalle_mantenimiento` y acumular subtotal
        $subtotalLineas = 0.00;
        $stmtInsertDet = $pdo->prepare("
            INSERT INTO detalle_mantenimiento (tipo, descripcion, cantidad, valor_unitario, id_mantenimiento)
            VALUES (:tipo, :desc, :cant, :valor, :id_mant)
        ");

        foreach ($lineasValidas as $linea) {
            $stmtInsertDet->execute([
                ':tipo'    => $linea['tipo'],
                ':desc'    => $linea['descripcion'],
                ':cant'    => $linea['cantidad'],
                ':valor'   => $linea['valor_unitario'],
                ':id_mant' => $idMantenimiento,
            ]);
            $subtotalLineas += ($linea['cantidad'] * $linea['valor_unitario']);
        }

        // 6. Actualizar el total en `mantenimiento`
        $totalFactura = $subtotalLineas + $valorManoObra;
        $stmtUpdateTotal = $pdo->prepare("UPDATE mantenimiento SET total = :total WHERE id_mantenimiento = :id_mant");
        $stmtUpdateTotal->execute([
            ':total'   => $totalFactura,
            ':id_mant' => $idMantenimiento,
        ]);

        $pdo->commit();

        return [
            'id_mantenimiento' => $idMantenimiento,
            'id_moto'          => $idMoto,
            'placa'            => $moto['placa'],
            'numero_servicio'  => $numeroServicio,
            'es_garantia'      => (bool)$esGarantia,
            'total'            => $totalFactura,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
