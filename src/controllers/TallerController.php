<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/MantenimientoModel.php';
require_once __DIR__ . '/../helpers/formato.php';

function mostrar_taller(): void
{
    $pdo = obtener_conexion();

    $mensajeExito = null;
    $mensajeError = null;
    $abrirModalMotoId = isset($_GET['ver_moto']) ? (int)$_GET['ver_moto'] : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registrar') {
        try {
            $resultado = registrar_mantenimiento($pdo, $_POST);
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

    if (isset($_GET['ok'])) {
        $placaOk = htmlspecialchars((string)($_GET['placa'] ?? ''));
        $numOk = (int)($_GET['num'] ?? 1);
        $garantiaOk = (int)($_GET['garantia'] ?? 0) === 1;
        $totalOk = formato_cop((float)($_GET['total'] ?? 0));
        $tipoTexto = $garantiaOk
            ? 'cubierto por Garantía (Mano de obra GRATIS)'
            : 'sin garantía (incluye $30.000 de mano de obra)';
        $mensajeExito = "Mantenimiento #{$numOk} para la moto {$placaOk} registrado correctamente — {$tipoTexto}. Total factura: {$totalOk}.";
    }

    $busqueda = trim((string)($_GET['q'] ?? ''));
    $todasLasMotos = obtener_motos_con_garantia($pdo);
    $motosFiltradas = $busqueda !== ''
        ? obtener_motos_con_garantia($pdo, $busqueda)
        : $todasLasMotos;
    $mecanicos = obtener_mecanicos($pdo);

    require __DIR__ . '/../views/taller/index.php';
}
