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
  <link rel="stylesheet" href="./assets/css/garantia.css">
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
        <a href="index.php" class="nav-link">Taller</a>
        <a href="mi_garantia.php" class="nav-link active">Mi garantía</a>
      </nav>
    </div>
  </header>

  <main class="page-container">
    <section class="card">
      <div class="card-header">
        <h2 class="card-title">🔍 Consulta tu garantía</h2>
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
