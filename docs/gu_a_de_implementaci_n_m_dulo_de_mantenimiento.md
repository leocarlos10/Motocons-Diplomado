# MOTOCONS: Guía de Implementación del Módulo de Mantenimiento (HU-08, HU-09 y HU-10)

> Guía práctica paso a paso en **PHP puro + PDO** sobre Docker para implementar el registro de mantenimientos, control de las 3 garantías, liquidación de facturas en ventana modal (`<dialog>`) y consulta de garantías por parte del cliente.

---

## 1. Estructura de archivos del módulo

Con los scripts `01_schema.sql` y `02_datos_prueba.sql` ya configurados en `db/init/`, la estructura de archivos de código dentro de `src/` queda organizada de la siguiente manera:

```text
motocons/
├── db/
│   └── init/
│       ├── 01_schema.sql          (Listo)
│       └── 02_datos_prueba.sql    (Listo)
└── src/
    ├── config/
    │   └── db.php                 ← Paso 1: Conexión PDO a MySQL
    ├── app/
    │   └── mantenimientos.php     ← Paso 2: Reglas de negocio y consultas SQL
    └── public/
        ├── index.php              ← Paso 3: Vista Taller (HU-08 y HU-09) con <dialog>
        └── mi_garantia.php        ← Paso 4: Vista Cliente (HU-10) con <dialog>
```

---

## 2. Paso 1: Conexión PDO (`src/config/db.php`)

Crea la carpeta `src/config/` (ubicada fuera de `public/` por seguridad) y dentro de ella el archivo `db.php`:

```php
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
```

---

## 3. Paso 2: Reglas de negocio y consultas (`src/app/mantenimientos.php`)

Crea la carpeta `src/app/` y el archivo `mantenimientos.php`. Aquí se centralizan las reglas de negocio en el servidor:
- **Número de servicio:** mantenimientos previos de la moto $+ 1$.
- **Condición de garantía:** aplica si el número de servicio es $\le 3$.
- **Mano de obra:** $\$0$ en servicios $1$ a $3$ (garantía) y $\$30.000\text{ COP}$ desde el $4.^\circ$ servicio en adelante.
- **Total de la factura:** suma de $(\text{cantidad} \times \text{valor\_unitario})$ de cada repuesto/insumo más el valor de la mano de obra.

```php
<?php
// src/app/mantenimientos.php

const VALOR_MANO_OBRA_SIN_GARANTIA = 30000;
const TOPE_MANTENIMIENTOS_GARANTIA = 3;

/**
 * Formatea un número como pesos colombianos: $45.000
 */
function formato_cop(float|int $valor): string
{
    return '$' . number_format((float)$valor, 0, ',', '.');
}

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
            m.vin,
            r.modelo AS referencia_modelo,
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
            m.id_moto, m.placa, m.vin, r.modelo, r.tipo_propulsion, 
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

/**
 * Consulta las motos y garantías asociadas al documento de un cliente (HU-10).
 */
function consultar_garantia_cliente(PDO $pdo, string $documento): ?array
{
    $doc = trim($documento);
    if ($doc === '') {
        return null;
    }

    $stmtCli = $pdo->prepare("SELECT id_cliente, nombre, documento, telefono, correo FROM cliente WHERE documento = :doc");
    $stmtCli->execute([':doc' => $doc]);
    $cliente = $stmtCli->fetch();

    if (!$cliente) {
        return ['cliente' => null, 'motos' => []];
    }

    $motos = obtener_motos_con_garantia($pdo, $doc);
    $motosCliente = array_values(array_filter($motos, fn($m) => (int)$m['id_cliente'] === (int)$cliente['id_cliente']));

    return [
        'cliente' => $cliente,
        'motos'   => $motosCliente,
    ];
}
```

---

## 4. Paso 3: Vista de Taller con `<dialog>` para facturas (`src/public/index.php`)

Reemplaza el contenido de `src/public/index.php` por este código. Conserva intacto el diseño que generaste e incorpora un modal `<dialog>` por cada motocicleta para visualizar sus facturas sin deformar la tabla:

```php
<?php
// src/public/index.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/mantenimientos.php';

$pdo = obtener_conexion();

$mensajeExito = null;
$mensajeError = null;
$abrirModalMotoId = isset($_GET['ver_moto']) ? (int)$_GET['ver_moto'] : 0;

// Procesar registro de mantenimiento (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registrar') {
    try {
        $resultado = registrar_mantenimiento($pdo, $_POST);
        // Patrón Post-Redirect-Get
        $params = http_build_query([
            'ok'       => 1,
            'placa'    => $resultado['placa'],
            'num'      => $resultado['numero_servicio'],
            'garantia' => $resultado['es_garantia'] ? 1 : 0,
            'total'    => $resultado['total'],
            'ver_moto' => $resultado['id_moto'],
        ]);
        header("Location: index.php?{$params}");
        exit;
    } catch (Throwable $e) {
        $mensajeError = $e->getMessage();
    }
}

// Mostrar alerta tras redirección exitosa
if (isset($_GET['ok'])) {
    $placaOk    = htmlspecialchars((string)($_GET['placa'] ?? ''));
    $numOk      = (int)($_GET['num'] ?? 1);
    $garantiaOk = (int)($_GET['garantia'] ?? 0) === 1;
    $totalOk    = formato_cop((float)($_GET['total'] ?? 0));
    $tipoTexto  = $garantiaOk ? 'cubierto por Garantía (Mano de obra GRATIS)' : 'sin garantía (incluye $30.000 de mano de obra)';
    $mensajeExito = "Mantenimiento #{$numOk} para la moto {$placaOk} registrado correctamente — {$tipoTexto}. Total factura: {$totalOk}.";
}

// Consultas para poblar el formulario y la tabla
$busqueda       = trim((string)($_GET['q'] ?? ''));
$todasLasMotos  = obtener_motos_con_garantia($pdo, '');
$motosFiltradas = ($busqueda !== '') ? obtener_motos_con_garantia($pdo, $busqueda) : $todasLasMotos;
$mecanicos      = obtener_mecanicos($pdo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MOTOCONS - Módulo de Taller</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-page: #F5F6F8;
      --topbar-bg: #1F2937;
      --topbar-text: #F9FAFB;
      --card-bg: #FFFFFF;
      --accent-orange: #F97316;
      --accent-orange-hover: #EA580C;
      --accent-orange-light: #FFF7ED;
      --accent-orange-border: #FFEDD5;
      --text-main: #111827;
      --text-muted: #6B7280;
      --border-color: #E5E7EB;
      --border-darker: #D1D5DB;
      --badge-green-bg: #DCFCE7;
      --badge-green-text: #166534;
      --badge-red-bg: #FEE2E2;
      --badge-red-text: #991B1B;
      --badge-gray-bg: #F3F4F6;
      --badge-gray-text: #4B5563;
      --alert-green-bg: #ECFDF5;
      --alert-green-border: #A7F3D0;
      --alert-green-text: #065F46;
      --alert-red-bg: #FEF2F2;
      --alert-red-border: #FECACA;
      --alert-red-text: #991B1B;
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
      --radius-md: 8px;
      --radius-lg: 12px;
      --radius-full: 9999px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: var(--bg-page);
      color: var(--text-main);
      line-height: 1.5;
      font-size: 14px;
      -webkit-font-smoothing: antialiased;
    }

    /* Top Bar */
    .topbar {
      background-color: var(--topbar-bg);
      color: var(--topbar-text);
      border-bottom: 3px solid var(--accent-orange);
      position: sticky;
      top: 0;
      z-index: 50;
      box-shadow: var(--shadow-md);
    }

    .topbar-container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 24px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .brand-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: #FFFFFF;
    }

    .brand-icon {
      background-color: var(--accent-orange);
      color: #FFFFFF;
      font-weight: 800;
      font-size: 16px;
      width: 34px;
      height: 34px;
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .brand-name { font-size: 20px; font-weight: 800; letter-spacing: 1px; color: #FFFFFF; }

    .brand-tag {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      background: #374151;
      color: #9CA3AF;
      padding: 2px 6px;
      border-radius: 4px;
      margin-left: 6px;
    }

    .nav-links { display: flex; align-items: center; gap: 8px; }

    .nav-link {
      text-decoration: none;
      color: #9CA3AF;
      padding: 8px 16px;
      border-radius: var(--radius-md);
      font-weight: 500;
      font-size: 14px;
      transition: all 0.2s ease;
    }

    .nav-link:hover { color: #FFFFFF; background-color: #374151; }

    .nav-link.active {
      color: #FFFFFF;
      background-color: #374151;
      border-bottom: 2px solid var(--accent-orange);
    }

    /* Page Container */
    .page-container {
      max-width: 1280px;
      margin: 28px auto 60px;
      padding: 0 24px;
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    /* Alerts */
    .alerts-container { display: flex; flex-direction: column; gap: 12px; }

    .alert {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      padding: 14px 18px;
      border-radius: var(--radius-md);
      border: 1px solid transparent;
      font-size: 14px;
      font-weight: 500;
    }

    .alert-content { display: flex; align-items: center; gap: 10px; }
    .alert-icon { font-weight: 700; font-size: 16px; line-height: 1; }
    .alert-close {
      background: none; border: none; font-size: 18px; line-height: 1;
      cursor: pointer; opacity: 0.6; padding: 0 4px; color: inherit;
    }
    .alert-close:hover { opacity: 1; }
    .alert-success { background-color: var(--alert-green-bg); border-color: var(--alert-green-border); color: var(--alert-green-text); }
    .alert-error { background-color: var(--alert-red-bg); border-color: var(--alert-red-border); color: var(--alert-red-text); }

    /* Cards */
    .card {
      background-color: var(--card-bg);
      border-radius: var(--radius-lg);
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }

    .card-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border-color);
      background-color: #FAFAFA;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .card-title {
      font-size: 18px; font-weight: 700; color: var(--text-main);
      display: flex; align-items: center; gap: 10px;
    }

    .card-title-icon {
      display: inline-flex; align-items: center; justify-content: center;
      width: 28px; height: 28px; border-radius: 6px;
      background: #FED7AA; color: #9A3412; font-size: 14px;
    }

    .card-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 2px; font-weight: 400; }
    .card-body { padding: 24px; }

    /* Forms */
    .form-grid-3 {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr;
      gap: 18px;
      margin-bottom: 22px;
    }

    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-label {
      font-weight: 600; font-size: 13px; color: #374151;
      display: flex; align-items: center; justify-content: space-between;
    }

    .form-control {
      height: 40px; padding: 0 12px; border-radius: var(--radius-md);
      border: 1px solid var(--border-darker); background-color: #FFFFFF;
      font-family: inherit; font-size: 14px; color: var(--text-main); outline: none;
    }

    .form-control:focus {
      border-color: var(--accent-orange);
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
    }

    /* Tables */
    .table-container {
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      overflow-x: auto;
      background: #FFFFFF;
    }

    .data-table { width: 100%; border-collapse: collapse; text-align: left; }
    .data-table th {
      background-color: #F9FAFB; color: #4B5563; font-weight: 600; font-size: 12px;
      text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px;
      border-bottom: 1px solid var(--border-color);
    }
    .data-table td {
      padding: 12px 16px; border-bottom: 1px solid var(--border-color);
      color: var(--text-main); vertical-align: middle;
    }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background-color: #F9FAFB; }

    .table-input {
      width: 100%; height: 36px; padding: 0 10px; border-radius: 6px;
      border: 1px solid var(--border-darker); font-family: inherit; font-size: 13px;
    }
    .table-input:focus { border-color: var(--accent-orange); outline: none; }

    .service-lines-section { margin-bottom: 20px; }
    .section-subhead {
      font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 10px;
      display: flex; justify-content: space-between; align-items: center;
    }
    .table-action-row { margin-top: 10px; display: flex; justify-content: flex-start; }

    /* Info Box */
    .info-box-orange {
      background-color: var(--accent-orange-light);
      border: 1px solid var(--accent-orange-border);
      border-left: 4px solid var(--accent-orange);
      padding: 14px 18px;
      border-radius: var(--radius-md);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      color: #9A3412;
      font-size: 13.5px;
    }

    /* Buttons */
    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px;
      font-family: inherit; font-weight: 600; font-size: 14px; padding: 0 20px;
      height: 42px; border-radius: var(--radius-md); cursor: pointer;
      border: 1px solid transparent; transition: all 0.15s ease; text-decoration: none;
    }
    .btn-primary { background-color: var(--accent-orange); color: #FFFFFF; }
    .btn-primary:hover { background-color: var(--accent-orange-hover); }
    .btn-secondary { background-color: #FFFFFF; color: #374151; border-color: var(--border-darker); }
    .btn-secondary:hover { background-color: #F3F4F6; }

    .btn-text {
      background: none; border: none; color: var(--accent-orange); font-weight: 600;
      font-size: 13px; padding: 6px 10px; cursor: pointer; border-radius: 6px;
      display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-text:hover { background-color: var(--accent-orange-light); }

    .btn-dialog-trigger {
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
      font-size: 13px;
      color: var(--accent-orange);
      padding: 6px 14px;
      border-radius: 6px;
      background-color: #FFF7ED;
      border: 1px solid #FFEDD5;
      transition: all 0.15s ease;
    }
    .btn-dialog-trigger:hover {
      background-color: var(--accent-orange);
      color: #FFFFFF;
      border-color: var(--accent-orange);
    }

    /* Filter Toolbar */
    .filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .filter-input-wrap { flex: 1; min-width: 280px; position: relative; }
    .filter-input {
      width: 100%; height: 42px; padding: 0 14px 0 38px;
      border-radius: var(--radius-md); border: 1px solid var(--border-darker);
      font-size: 14px; outline: none;
    }
    .filter-input:focus { border-color: var(--accent-orange); box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15); }
    .filter-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }

    /* Badges & Progress */
    .badge {
      display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px;
      border-radius: var(--radius-full); font-size: 12px; font-weight: 600; white-space: nowrap;
    }
    .badge-green { background-color: var(--badge-green-bg); color: var(--badge-green-text); }
    .badge-red { background-color: var(--badge-red-bg); color: var(--badge-red-text); }
    .badge-gray { background-color: var(--badge-gray-bg); color: var(--badge-gray-text); }
    .badge-dot { width: 6px; height: 6px; border-radius: 50%; background-color: currentColor; }

    .segment-bar-container { display: flex; align-items: center; gap: 8px; }
    .segments { display: flex; gap: 3px; width: 60px; height: 8px; }
    .segment { flex: 1; border-radius: 2px; background-color: #E5E7EB; }
    .segment.filled { background-color: var(--accent-orange); }
    .segment.filled-red { background-color: #EF4444; }
    .segment-text { font-size: 13px; font-weight: 600; color: var(--text-main); }

    .plate-badge {
      font-family: monospace; font-weight: 700; font-size: 13px; padding: 2px 8px;
      background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; border-radius: 4px;
    }

    /* Native HTML <dialog> Modal Styles */
    dialog.invoice-dialog {
      margin: auto;
      width: min(920px, 94vw);
      max-height: 86vh;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      background: #FFFFFF;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
      padding: 0;
      overflow: hidden;
    }

    dialog.invoice-dialog::backdrop {
      background: rgba(17, 24, 39, 0.6);
      backdrop-filter: blur(3px);
    }

    .dialog-header {
      padding: 18px 24px;
      background: var(--topbar-bg);
      color: #FFFFFF;
      border-bottom: 3px solid var(--accent-orange);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .dialog-title {
      font-size: 16px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .dialog-close-btn {
      background: #374151;
      color: #FFFFFF;
      border: none;
      width: 32px;
      height: 32px;
      border-radius: 6px;
      font-size: 18px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .dialog-close-btn:hover { background: var(--accent-orange); }

    .dialog-body {
      padding: 24px;
      background: #F9FAFB;
      max-height: calc(86vh - 135px);
      overflow-y: auto;
    }

    .dialog-footer {
      padding: 14px 24px;
      background: #FFFFFF;
      border-top: 1px solid var(--border-color);
      display: flex;
      justify-content: flex-end;
    }

    /* Invoice Cards Inside Dialog */
    .invoices-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
      gap: 16px;
    }

    .invoice-card {
      background: #FFFFFF;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .invoice-card-header {
      padding: 12px 14px;
      background: #F9FAFB;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .invoice-title-meta { font-weight: 700; font-size: 13.5px; color: var(--text-main); }
    .invoice-sub-meta { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }

    .invoice-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .invoice-table th {
      background: #FAFAFA; padding: 8px 12px; color: #6B7280;
      font-weight: 600; border-bottom: 1px solid var(--border-color); text-align: left;
    }
    .invoice-table td { padding: 8px 12px; border-bottom: 1px solid #F3F4F6; color: #374151; }
    .invoice-table .num { text-align: right; }

    .invoice-summary-row { background: #FAFAFA; font-size: 12.5px; }
    .invoice-summary-row td { border-bottom: 1px solid var(--border-color); padding: 8px 12px; }

    .invoice-total-row { background: #F8FAFC; font-size: 13.5px; font-weight: 700; }
    .invoice-total-row td { padding: 10px 12px; border-bottom: none; color: var(--text-main); }

    .labor-free {
      color: #166534; font-weight: 700; background: #DCFCE7;
      padding: 2px 6px; border-radius: 4px; display: inline-block;
    }
    .labor-charged { color: #1F2937; font-weight: 700; }

    @media (max-width: 900px) {
      .form-grid-3, .invoices-grid { grid-template-columns: 1fr; }
      .filter-bar { flex-direction: column; align-items: stretch; }
    }
  </style>
</head>
<body>

  <!-- 1. TOP BAR -->
  <header class="topbar">
    <div class="topbar-container">
      <a href="index.php" class="brand-logo">
        <span class="brand-icon">M</span>
        <span class="brand-name">MOTOCONS</span>
        <span class="brand-tag">Concesionario &amp; Taller</span>
      </a>
      <nav class="nav-links" aria-label="Navegación principal">
        <a href="index.php" class="nav-link active">Taller (HU-08 / HU-09)</a>
        <a href="mi_garantia.php" class="nav-link">Mi garantía (HU-10)</a>
      </nav>
    </div>
  </header>

  <main class="page-container">

    <!-- 2. ALERT AREA -->
    <?php if ($mensajeExito || $mensajeError): ?>
      <section class="alerts-container" aria-label="Notificaciones del sistema">
        <?php if ($mensajeExito): ?>
          <div class="alert alert-success" role="alert">
            <div class="alert-content">
              <span class="alert-icon" aria-hidden="true">✓</span>
              <span><strong>¡Éxito!</strong> <?= $mensajeExito ?></span>
            </div>
            <button type="button" class="alert-close" onclick="this.parentElement.style.display='none';">×</button>
          </div>
        <?php endif; ?>

        <?php if ($mensajeError): ?>
          <div class="alert alert-error" role="alert">
            <div class="alert-content">
              <span class="alert-icon" aria-hidden="true">⚠</span>
              <span><strong>Error:</strong> <?= htmlspecialchars($mensajeError) ?></span>
            </div>
            <button type="button" class="alert-close" onclick="this.parentElement.style.display='none';">×</button>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <!-- 3. CARD: REGISTRAR MANTENIMIENTO (HU-08 y HU-09) -->
    <section class="card" aria-labelledby="card-reg-title">
      <div class="card-header">
        <div>
          <h2 id="card-reg-title" class="card-title">
            <span class="card-title-icon" aria-hidden="true">🛠</span>
            Registrar mantenimiento
          </h2>
          <p class="card-subtitle">Ingreso de nueva orden de servicio técnico y control de los 3 mantenimientos de garantía</p>
        </div>
      </div>

      <div class="card-body">
        <form method="POST" action="index.php">
          <input type="hidden" name="accion" value="registrar">

          <div class="form-grid-3">
            <div class="form-group">
              <label for="select-moto" class="form-label">
                <span>Moto asignada</span>
                <span style="font-weight: normal; font-size: 12px; color: var(--text-muted);">Placa · Modelo · Cliente · Garantías</span>
              </label>
              <select id="select-moto" name="moto_id" class="form-control" required>
                <option value="">Seleccione una motocicleta...</option>
                <?php foreach ($todasLasMotos as $m): ?>
                  <option value="<?= (int)$m['id_moto'] ?>">
                    <?= htmlspecialchars($m['placa']) ?> · <?= htmlspecialchars($m['referencia_modelo']) ?> · <?= htmlspecialchars($m['cliente_nombre']) ?> · <?= (int)$m['garantias_usadas'] ?>/3 garantías
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="select-mecanico" class="form-label">Mecánico responsable</label>
              <select id="select-mecanico" name="mecanico_id" class="form-control" required>
                <?php foreach ($mecanicos as $mec): ?>
                  <option value="<?= (int)$mec['id_empleado'] ?>">
                    <?= htmlspecialchars($mec['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="input-fecha" class="form-label">Fecha de servicio</label>
              <input type="date" id="input-fecha" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <!-- Tabla de líneas de servicio -->
          <div class="service-lines-section">
            <div class="section-subhead">
              <span>Ítems y conceptos del servicio (Repuestos e Insumos)</span>
              <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">Las filas en blanco se ignoran automáticamente</span>
            </div>

            <div class="table-container">
              <table class="data-table" id="tabla-lineas" aria-label="Líneas de servicio">
                <thead>
                  <tr>
                    <th style="width: 170px;">Tipo</th>
                    <th>Descripción</th>
                    <th style="width: 120px; text-align: right;">Cantidad</th>
                    <th style="width: 180px; text-align: right;">Valor unitario (COP)</th>
                  </tr>
                </thead>
                <tbody id="tbody-lineas">
                  <?php for ($i = 0; $i < 4; $i++): ?>
                    <tr>
                      <td>
                        <select name="linea_tipo[]" class="table-input">
                          <option value="Insumo">Insumo</option>
                          <option value="Repuesto">Repuesto</option>
                        </select>
                      </td>
                      <td>
                        <input type="text" name="linea_desc[]" class="table-input" placeholder="Ej: Aceite semi-sintético 10W-30 o Filtro de aceite">
                      </td>
                      <td>
                        <input type="number" name="linea_cant[]" class="table-input" style="text-align: right;" value="1" min="1">
                      </td>
                      <td>
                        <input type="number" name="linea_valor[]" class="table-input" style="text-align: right;" placeholder="0" min="0" step="100">
                      </td>
                    </tr>
                  <?php endfor; ?>
                </tbody>
              </table>
            </div>

            <div class="table-action-row">
              <button type="button" class="btn-text" onclick="agregarFilaServicio()">
                <span aria-hidden="true">＋</span> Agregar línea
              </button>
            </div>
          </div>

          <div class="info-box-orange" role="note">
            <span aria-hidden="true">ℹ</span>
            <div>
              <strong>Condición de garantía MOTOCONS:</strong> Mano de obra: <strong>GRATIS</strong> en los servicios 1 a 3 (garantía). Desde el 4.º servicio se cobra <strong>$30.000 COP</strong>. Repuestos e insumos siempre se cobran.
            </div>
          </div>

          <div>
            <button type="submit" class="btn btn-primary">
              <span aria-hidden="true">💾</span> Registrar mantenimiento
            </button>
          </div>
        </form>
      </div>
    </section>

    <!-- 4. CARD: MOTOS Y MANTENIMIENTOS -->
    <section class="card" aria-labelledby="card-motos-title">
      <div class="card-header">
        <div>
          <h2 id="card-motos-title" class="card-title">
            <span class="card-title-icon" aria-hidden="true">🏍</span>
            Motos y mantenimientos
          </h2>
          <p class="card-subtitle">Historial de vehículos atendidos, cupos de garantía aplicados y facturas en ventana modal</p>
        </div>
      </div>

      <div class="card-body">
        <!-- Buscador por placa o documento del cliente -->
        <form method="GET" action="index.php" class="filter-bar">
          <div class="filter-input-wrap">
            <span class="filter-icon" aria-hidden="true">🔍</span>
            <input type="text" name="q" class="filter-input" placeholder="Buscar por placa o documento del cliente..." value="<?= htmlspecialchars($busqueda) ?>">
          </div>
          <button type="submit" class="btn btn-primary">Filtrar</button>
          <a href="index.php" class="btn btn-secondary">Limpiar</a>
        </form>

        <div class="table-container">
          <table class="data-table" aria-label="Listado de motos y garantías">
            <thead>
              <tr>
                <th>Placa</th>
                <th>Referencia</th>
                <th>Cliente</th>
                <th>Garantías usadas</th>
                <th>Restantes</th>
                <th>Estado</th>
                <th style="text-align: center;">Facturas</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($motosFiltradas)): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 28px; color: var(--text-muted);">
                    No se encontraron motocicletas para el criterio de búsqueda ingresado.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($motosFiltradas as $moto): ?>
                  <?php
                    $idMoto    = (int)$moto['id_moto'];
                    $usadas    = (int)$moto['garantias_usadas'];
                    $restantes = (int)$moto['garantias_restantes'];
                    $vigente   = (bool)$moto['garantia_vigente'];
                    $facturas  = $moto['facturas'];
                    $claseSeg  = $vigente ? 'filled' : 'filled-red';
                  ?>
                  <tr>
                    <td>
                      <span class="plate-badge"><?= htmlspecialchars($moto['placa']) ?></span>
                    </td>
                    <td>
                      <strong><?= htmlspecialchars($moto['marca_nombre'] . ' ' . $moto['referencia_modelo']) ?></strong>
                      <div style="font-size: 11px; color: var(--text-muted);">
                        <?= htmlspecialchars($moto['tipo_propulsion']) ?> · VIN: <?= htmlspecialchars($moto['vin']) ?>
                      </div>
                    </td>
                    <td>
                      <div><?= htmlspecialchars($moto['cliente_nombre']) ?></div>
                      <div style="font-size: 11px; color: var(--text-muted);">CC <?= htmlspecialchars($moto['cliente_documento']) ?></div>
                    </td>
                    <td>
                      <div class="segment-bar-container">
                        <div class="segments" title="<?= $usadas ?> de 3 usadas">
                          <?php for ($s = 1; $s <= 3; $s++): ?>
                            <div class="segment <?= ($s <= $usadas) ? $claseSeg : '' ?>"></div>
                          <?php endfor; ?>
                        </div>
                        <span class="segment-text"><?= $usadas ?> / 3</span>
                      </div>
                    </td>
                    <td>
                      <?php if ($restantes > 0): ?>
                        <strong style="color: #047857;"><?= $restantes ?> <?= $restantes === 1 ? 'servicio' : 'servicios' ?></strong>
                      <?php else: ?>
                        <span style="color: var(--text-muted);">0 servicios</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($vigente): ?>
                        <span class="badge badge-green">
                          <span class="badge-dot"></span> Garantía vigente
                        </span>
                      <?php else: ?>
                        <span class="badge badge-red">
                          <span class="badge-dot"></span> Garantía agotada
                        </span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                      <button
                        type="button"
                        class="btn-dialog-trigger"
                        onclick="document.getElementById('dialog-facturas-<?= $idMoto ?>').showModal()"
                      >
                        🧾 Ver facturas (<?= count($facturas) ?>)
                      </button>

                      <!-- DIALOG MODAL DE FACTURAS -->
                      <dialog
                        id="dialog-facturas-<?= $idMoto ?>"
                        class="invoice-dialog"
                        onclick="if (event.target === this) this.close();"
                      >
                        <div class="dialog-header">
                          <div class="dialog-title">
                            <span>🧾 Historial de Facturas · Placa <?= htmlspecialchars($moto['placa']) ?></span>
                            <span class="badge <?= $vigente ? 'badge-green' : 'badge-red' ?>">
                              <?= $usadas ?>/3 garantías usadas
                            </span>
                          </div>
                          <button
                            type="button"
                            class="dialog-close-btn"
                            aria-label="Cerrar ventana"
                            onclick="this.closest('dialog').close()"
                          >×</button>
                        </div>

                        <div class="dialog-body" style="text-align: left;">
                          <div style="margin-bottom: 16px; font-size: 13px; color: var(--text-muted);">
                            <strong>Vehículo:</strong> <?= htmlspecialchars($moto['marca_nombre'] . ' ' . $moto['referencia_modelo']) ?> &nbsp;|&nbsp;
                            <strong>Propietario:</strong> <?= htmlspecialchars($moto['cliente_nombre']) ?> (CC <?= htmlspecialchars($moto['cliente_documento']) ?>)
                          </div>

                          <?php if (empty($facturas)): ?>
                            <div style="text-align: center; padding: 36px 20px; background: #FFFFFF; border-radius: 8px; border: 1px solid var(--border-color); color: var(--text-muted);">
                              Esta motocicleta aún no tiene mantenimientos ni facturas registradas en el sistema MOTOCONS.
                            </div>
                          <?php else: ?>
                            <div class="invoices-grid">
                              <?php foreach ($facturas as $fac): ?>
                                <?php
                                  $esGarantiaFac = (int)$fac['es_garantia'] === 1;
                                  $numServ       = (int)$fac['numero_servicio'];
                                  $fechaForm     = date('d/m/Y', strtotime($fac['fecha']));
                                ?>
                                <div class="invoice-card" style="<?= !$esGarantiaFac ? 'border-color: #FED7AA;' : '' ?>">
                                  <div class="invoice-card-header" style="<?= !$esGarantiaFac ? 'background-color: #FFF7ED;' : '' ?>">
                                    <div>
                                      <div class="invoice-title-meta">
                                        Servicio #<?= $numServ ?> · <?= $fechaForm ?> <?= !$esGarantiaFac ? '(Extra)' : '' ?>
                                      </div>
                                      <div class="invoice-sub-meta">
                                        Mecánico: <?= htmlspecialchars($fac['mecanico_nombre']) ?>
                                      </div>
                                    </div>
                                    <?php if ($esGarantiaFac): ?>
                                      <span class="badge badge-green">Garantía</span>
                                    <?php else: ?>
                                      <span class="badge badge-gray">Pagado</span>
                                    <?php endif; ?>
                                  </div>

                                  <table class="invoice-table">
                                    <thead>
                                      <tr>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th class="num">Cant.</th>
                                        <th class="num">Subtotal</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <?php foreach ($fac['lineas'] as $linea): ?>
                                        <?php $subtotalLinea = (int)$linea['cantidad'] * (float)$linea['valor_unitario']; ?>
                                        <tr>
                                          <td><span style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($linea['tipo']) ?></span></td>
                                          <td><?= htmlspecialchars($linea['descripcion']) ?></td>
                                          <td class="num"><?= (int)$linea['cantidad'] ?></td>
                                          <td class="num"><?= formato_cop($subtotalLinea) ?></td>
                                        </tr>
                                      <?php endforeach; ?>

                                      <tr class="invoice-summary-row">
                                        <td colspan="3">
                                          <strong>Mano de obra <?= $esGarantiaFac ? "(Garantía {$numServ}/3)" : "({$numServ}.º servicio)" ?></strong>
                                        </td>
                                        <td class="num">
                                          <?php if ($esGarantiaFac): ?>
                                            <span class="labor-free">GRATIS</span>
                                          <?php else: ?>
                                            <span class="labor-charged"><?= formato_cop((float)$fac['valor_mano_obra']) ?></span>
                                          <?php endif; ?>
                                        </td>
                                      </tr>

                                      <tr class="invoice-total-row" style="<?= !$esGarantiaFac ? 'background-color: #FFF7ED;' : '' ?>">
                                        <td colspan="3">TOTAL FACTURA</td>
                                        <td class="num" style="<?= !$esGarantiaFac ? 'color: #9A3412;' : '' ?>">
                                          <?= formato_cop((float)$fac['total']) ?>
                                        </td>
                                      </tr>
                                    </tbody>
                                  </table>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                        </div>

                        <div class="dialog-footer">
                          <button type="button" class="btn btn-secondary" onclick="this.closest('dialog').close()">
                            Cerrar
                          </button>
                        </div>
                      </dialog>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </main>

  <script>
    function agregarFilaServicio() {
      const tbody = document.getElementById('tbody-lineas');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <select name="linea_tipo[]" class="table-input">
            <option value="Insumo">Insumo</option>
            <option value="Repuesto">Repuesto</option>
          </select>
        </td>
        <td>
          <input type="text" name="linea_desc[]" class="table-input" placeholder="Descripción del repuesto o insumo">
        </td>
        <td>
          <input type="number" name="linea_cant[]" class="table-input" style="text-align: right;" value="1" min="1">
        </td>
        <td>
          <input type="number" name="linea_valor[]" class="table-input" style="text-align: right;" placeholder="0" min="0" step="100">
        </td>
      `;
      tbody.appendChild(tr);
    }

    <?php if ($abrirModalMotoId > 0): ?>
    window.addEventListener('DOMContentLoaded', () => {
      const modal = document.getElementById('dialog-facturas-<?= $abrirModalMotoId ?>');
      if (modal) modal.showModal();
    });
    <?php endif; ?>
  </script>
</body>
</html>
```

---

## 5. Paso 4: Vista del Cliente (`src/public/mi_garantia.php`)

Crea el archivo `src/public/mi_garantia.php` para cubrir la **HU-10 (Consultar mi garantía)** con el mismo estilo visual y su respectivo `<dialog>` de detalle de facturas:

```php
<?php
// src/public/mi_garantia.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/mantenimientos.php';

$pdo = obtener_conexion();
$documento = trim((string)($_GET['documento'] ?? ''));
$resultado = ($documento !== '') ? consultar_garantia_cliente($pdo, $documento) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MOTOCONS - Consulta tu Garantía</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-page: #F5F6F8;
      --topbar-bg: #1F2937;
      --topbar-text: #F9FAFB;
      --card-bg: #FFFFFF;
      --accent-orange: #F97316;
      --accent-orange-hover: #EA580C;
      --text-main: #111827;
      --text-muted: #6B7280;
      --border-color: #E5E7EB;
      --border-darker: #D1D5DB;
      --badge-green-bg: #DCFCE7;
      --badge-green-text: #166534;
      --badge-red-bg: #FEE2E2;
      --badge-red-text: #991B1B;
      --badge-gray-bg: #F3F4F6;
      --badge-gray-text: #4B5563;
      --radius-md: 8px;
      --radius-lg: 12px;
      --radius-full: 9999px;
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background-color: var(--bg-page);
      color: var(--text-main);
      line-height: 1.5;
      font-size: 14px;
    }

    .topbar {
      background-color: var(--topbar-bg);
      color: var(--topbar-text);
      border-bottom: 3px solid var(--accent-orange);
      box-shadow: var(--shadow-md);
    }
    .topbar-container {
      max-width: 1280px; margin: 0 auto; padding: 0 24px; height: 64px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .brand-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #FFFFFF; }
    .brand-icon {
      background-color: var(--accent-orange); color: #FFFFFF; font-weight: 800;
      font-size: 16px; width: 34px; height: 34px; border-radius: var(--radius-md);
      display: flex; align-items: center; justify-content: center;
    }
    .brand-name { font-size: 20px; font-weight: 800; letter-spacing: 1px; }
    .brand-tag {
      font-size: 11px; text-transform: uppercase; background: #374151;
      color: #9CA3AF; padding: 2px 6px; border-radius: 4px;
    }
    .nav-links { display: flex; align-items: center; gap: 8px; }
    .nav-link {
      text-decoration: none; color: #9CA3AF; padding: 8px 16px;
      border-radius: var(--radius-md); font-weight: 500;
    }
    .nav-link:hover { color: #FFFFFF; background-color: #374151; }
    .nav-link.active {
      color: #FFFFFF; background-color: #374151; border-bottom: 2px solid var(--accent-orange);
    }

    .page-container {
      max-width: 960px; margin: 32px auto 60px; padding: 0 24px;
      display: flex; flex-direction: column; gap: 24px;
    }

    .card {
      background-color: var(--card-bg); border-radius: var(--radius-lg);
      border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;
    }
    .card-header { padding: 20px 24px; border-bottom: 1px solid var(--border-color); background: #FAFAFA; }
    .card-title { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .card-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
    .card-body { padding: 24px; }

    .search-row { display: flex; gap: 12px; flex-wrap: wrap; }
    .form-control {
      flex: 1; min-width: 240px; height: 42px; padding: 0 14px;
      border-radius: var(--radius-md); border: 1px solid var(--border-darker); font-size: 14px;
    }
    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px;
      font-weight: 600; font-size: 14px; padding: 0 20px; height: 42px;
      border-radius: var(--radius-md); cursor: pointer; border: 1px solid transparent; text-decoration: none;
    }
    .btn-primary { background-color: var(--accent-orange); color: #FFFFFF; }
    .btn-primary:hover { background-color: var(--accent-orange-hover); }
    .btn-secondary { background: #FFFFFF; color: #374151; border-color: var(--border-darker); }

    .moto-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
    .moto-card {
      background: #FFFFFF; border: 1px solid var(--border-color);
      border-radius: var(--radius-lg); padding: 20px; display: flex; flex-direction: column; gap: 14px;
    }
    .plate-badge {
      font-family: monospace; font-weight: 700; font-size: 14px; padding: 3px 10px;
      background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; border-radius: 4px;
    }
    .badge {
      display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
      border-radius: var(--radius-full); font-size: 12px; font-weight: 600;
    }
    .badge-green { background-color: var(--badge-green-bg); color: var(--badge-green-text); }
    .badge-red { background-color: var(--badge-red-bg); color: var(--badge-red-text); }
    .badge-gray { background-color: var(--badge-gray-bg); color: var(--badge-gray-text); }

    .segments { display: flex; gap: 6px; height: 10px; margin: 6px 0; }
    .segment { flex: 1; border-radius: 3px; background-color: #E5E7EB; }
    .segment.filled { background-color: var(--accent-orange); }
    .segment.filled-red { background-color: #EF4444; }

    .service-list { list-style: none; border-top: 1px solid var(--border-color); padding-top: 12px; display: flex; flex-direction: column; gap: 8px; }
    .service-item { display: flex; justify-content: space-between; font-size: 13px; color: #374151; }

    /* Dialog styles */
    dialog.invoice-dialog {
      margin: auto; width: min(860px, 94vw); max-height: 85vh;
      border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 0;
    }
    dialog.invoice-dialog::backdrop { background: rgba(17, 24, 39, 0.6); backdrop-filter: blur(3px); }
    .dialog-header {
      padding: 16px 22px; background: var(--topbar-bg); color: #FFFFFF;
      border-bottom: 3px solid var(--accent-orange); display: flex; justify-content: space-between; align-items: center;
    }
    .dialog-body { padding: 22px; background: #F9FAFB; max-height: calc(85vh - 120px); overflow-y: auto; }
    .dialog-footer { padding: 12px 22px; background: #FFFFFF; border-top: 1px solid var(--border-color); text-align: right; }
    .invoice-card { background: #FFFFFF; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 14px; overflow: hidden; }
    .invoice-card-header { padding: 10px 14px; background: #F9FAFB; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; font-weight: 700; }
    .invoice-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .invoice-table th, .invoice-table td { padding: 8px 12px; border-bottom: 1px solid #F3F4F6; text-align: left; }
    .invoice-table .num { text-align: right; }
  </style>
</head>
<body>

  <header class="topbar">
    <div class="topbar-container">
      <a href="index.php" class="brand-logo">
        <span class="brand-icon">M</span>
        <span class="brand-name">MOTOCONS</span>
        <span class="brand-tag">Portal de Clientes</span>
      </a>
      <nav class="nav-links">
        <a href="index.php" class="nav-link">Taller (HU-08 / HU-09)</a>
        <a href="mi_garantia.php" class="nav-link active">Mi garantía (HU-10)</a>
      </nav>
    </div>
  </header>

  <main class="page-container">
    <section class="card">
      <div class="card-header">
        <h2 class="card-title">🔍 Consulta tu garantía (HU-10)</h2>
        <p class="card-subtitle">Ingresa tu número de documento para ver los mantenimientos usados y disponibles de tus motocicletas</p>
      </div>
      <div class="card-body">
        <form method="GET" action="mi_garantia.php" class="search-row">
          <input
            type="text"
            name="documento"
            class="form-control"
            placeholder="Ingresa tu número de documento..."
            value="<?= htmlspecialchars($documento) ?>"
            required
          >
          <button type="submit" class="btn btn-primary">Consultar</button>
          <?php if ($documento !== ''): ?>
            <a href="mi_garantia.php" class="btn btn-secondary">Limpiar</a>
          <?php endif; ?>
        </form>
      </div>
    </section>

    <?php if ($resultado !== null): ?>
      <?php if ($resultado['cliente'] === null): ?>
        <div class="card">
          <div class="card-body" style="text-align: center; color: var(--text-muted);">
            No se encontró ningún cliente registrado con el documento <strong><?= htmlspecialchars($documento) ?></strong>.
          </div>
        </div>
      <?php else: ?>
        <?php $cli = $resultado['cliente']; ?>
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <h3>Motocicletas de <?= htmlspecialchars($cli['nombre']) ?> (CC <?= htmlspecialchars($cli['documento']) ?>)</h3>
          <span style="color: var(--text-muted); font-size: 13px;"><?= count($resultado['motos']) ?> vehículo(s)</span>
        </div>

        <div class="moto-grid">
          <?php foreach ($resultado['motos'] as $moto): ?>
            <?php
              $idMoto    = (int)$moto['id_moto'];
              $usadas    = (int)$moto['garantias_usadas'];
              $restantes = (int)$moto['garantias_restantes'];
              $vigente   = (bool)$moto['garantia_vigente'];
              $claseSeg  = $vigente ? 'filled' : 'filled-red';
            ?>
            <div class="moto-card">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <span class="plate-badge"><?= htmlspecialchars($moto['placa']) ?></span>
                  <strong style="margin-left: 8px; font-size: 16px;">
                    <?= htmlspecialchars($moto['marca_nombre'] . ' ' . $moto['referencia_modelo']) ?>
                  </strong>
                </div>
                <?php if ($vigente): ?>
                  <span class="badge badge-green">Garantía vigente</span>
                <?php else: ?>
                  <span class="badge badge-red">Garantía agotada</span>
                <?php endif; ?>
              </div>

              <div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600;">
                  <span><?= $usadas ?> usados · <?= $restantes ?> disponible(s)</span>
                  <span><?= $usadas ?> / 3</span>
                </div>
                <div class="segments">
                  <?php for ($s = 1; $s <= 3; $s++): ?>
                    <div class="segment <?= ($s <= $usadas) ? $claseSeg : '' ?>"></div>
                  <?php endfor; ?>
                </div>
              </div>

              <?php if (!$vigente): ?>
                <div style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; padding: 10px 12px; border-radius: 6px; font-size: 12.5px;">
                  <strong>Garantía agotada:</strong> Los próximos servicios en taller incluyen el cobro de mano de obra ($30.000 COP).
                </div>
              <?php endif; ?>

              <ul class="service-list">
                <?php if (empty($moto['facturas'])): ?>
                  <li style="color: var(--text-muted); font-size: 13px;">Aún no registra revisiones en el taller.</li>
                <?php else: ?>
                  <?php foreach ($moto['facturas'] as $fac): ?>
                    <li class="service-item">
                      <span>
                        <strong>Servicio #<?= (int)$fac['numero_servicio'] ?></strong> · <?= date('d/m/Y', strtotime($fac['fecha'])) ?>
                        (<?= (int)$fac['es_garantia'] === 1 ? 'Garantía' : 'Servicio Pagado' ?>)
                      </span>
                      <strong><?= formato_cop((float)$fac['total']) ?></strong>
                    </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>

              <div style="margin-top: auto; padding-top: 8px;">
                <button
                  type="button"
                  class="btn btn-secondary"
                  style="width: 100%; height: 38px;"
                  onclick="document.getElementById('dialog-cli-<?= $idMoto ?>').showModal()"
                >
                  🧾 Ver detalle de facturas (<?= count($moto['facturas']) ?>)
                </button>
              </div>

              <dialog id="dialog-cli-<?= $idMoto ?>" class="invoice-dialog" onclick="if (event.target === this) this.close();">
                <div class="dialog-header">
                  <strong>🧾 Facturas de <?= htmlspecialchars($moto['placa']) ?> · <?= htmlspecialchars($moto['marca_nombre'] . ' ' . $moto['referencia_modelo']) ?></strong>
                  <button type="button" class="btn btn-secondary" style="height: 30px; padding: 0 10px;" onclick="this.closest('dialog').close()">×</button>
                </div>
                <div class="dialog-body">
                  <?php if (empty($moto['facturas'])): ?>
                    <p style="color: var(--text-muted); text-align: center;">No hay facturas registradas para esta motocicleta.</p>
                  <?php else: ?>
                    <?php foreach ($moto['facturas'] as $fac): ?>
                      <?php $esGar = (int)$fac['es_garantia'] === 1; ?>
                      <div class="invoice-card">
                        <div class="invoice-card-header">
                          <span>Servicio #<?= (int)$fac['numero_servicio'] ?> · <?= date('d/m/Y', strtotime($fac['fecha'])) ?></span>
                          <span class="badge <?= $esGar ? 'badge-green' : 'badge-gray' ?>"><?= $esGar ? 'Garantía' : 'Pagado' ?></span>
                        </div>
                        <table class="invoice-table">
                          <thead>
                            <tr>
                              <th>Tipo</th>
                              <th>Descripción</th>
                              <th class="num">Cant.</th>
                              <th class="num">Subtotal</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($fac['lineas'] as $l): ?>
                              <tr>
                                <td><?= htmlspecialchars($l['tipo']) ?></td>
                                <td><?= htmlspecialchars($l['descripcion']) ?></td>
                                <td class="num"><?= (int)$l['cantidad'] ?></td>
                                <td class="num"><?= formato_cop((int)$l['cantidad'] * (float)$l['valor_unitario']) ?></td>
                              </tr>
                            <?php endforeach; ?>
                            <tr>
                              <td colspan="3"><strong>Mano de obra</strong></td>
                              <td class="num"><strong><?= $esGar ? 'GRATIS' : formato_cop((float)$fac['valor_mano_obra']) ?></strong></td>
                            </tr>
                            <tr>
                              <td colspan="3"><strong>TOTAL</strong></td>
                              <td class="num"><strong><?= formato_cop((float)$fac['total']) ?></strong></td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <div class="dialog-footer">
                  <button type="button" class="btn btn-secondary" onclick="this.closest('dialog').close()">Cerrar</button>
                </div>
              </dialog>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>

</body>
</html>
```

---

## 6. Lista de verificación para sustentar las Historias de Usuario

1. **HU-08 (Registrar mantenimiento y control de 3 garantías):**
   - Entra a `http://localhost:8080/index.php`.
   - En el buscador de la tabla filtra por placa o cédula del cliente.
   - Selecciona una moto con menos de 3 garantías usadas, registra un servicio y verifica que el contador suba automáticamente.
2. **HU-09 (Facturar servicio de taller con mano de obra gratis vs. cobrada):**
   - Haz clic en el botón **"Ver facturas"** de cualquier fila: se abrirá el `<dialog>` mostrando los servicios $1$ a $3$ con mano de obra **GRATIS**.
   - Registra un $4.^\circ$ servicio sobre una moto que ya tenga `3/3 garantías`: al guardar, el `<dialog>` se abrirá automáticamente mostrando la nueva factura con el cobro de **$30.000 COP** de mano de obra.
3. **HU-10 (Consultar mi garantía como cliente):**
   - Entra a `http://localhost:8080/mi_garantia.php`, ingresa el número de documento del cliente y verifica las barras de progreso de sus motocicletas junto con sus facturas en ventana modal.