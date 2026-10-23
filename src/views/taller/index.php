<?php
// Rendered by TallerController.
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
  <link rel="stylesheet" href="./assets/css/app.css">
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
        <a href="index.php" class="nav-link active">Taller</a>
        <a href="mi_garantia.php" class="nav-link">Mi garantía</a>
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
                        <?= htmlspecialchars($moto['tipo_propulsion']) ?> · Chasis: <?= htmlspecialchars($moto['chasis']) ?>
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
