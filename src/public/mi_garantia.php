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
