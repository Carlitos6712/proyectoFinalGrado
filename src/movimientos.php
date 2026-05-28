<?php
/**
 * Historial y registro de movimientos de stock.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';
require_once __DIR__ . '/includes/Movimiento.php';

$error          = '';
$producto       = null;
$movimientos    = [];
$resumen        = [];
$totalMovs      = 0;
$totalPaginas   = 1;
$stockBajoCount = 0;

const POR_PAGINA_MOVIMIENTOS = 10;

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$productoId = filter_input(INPUT_GET, 'producto_id', FILTER_VALIDATE_INT)
           ?? filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);

try {
    $productoModel   = new Producto();
    $movimientoModel = new Movimiento();
    $stockBajoCount  = count($productoModel->filtrarStockBajo());

    if (!$productoId) {
        $paginaActual = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
        $totalMovs    = $movimientoModel->contarTodos();
        $totalPaginas = max(1, (int) ceil($totalMovs / POR_PAGINA_MOVIMIENTOS));
        $paginaActual = min($paginaActual, $totalPaginas);
        $movimientos  = $movimientoModel->listarTodosPaginado($paginaActual, POR_PAGINA_MOVIMIENTOS);
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tipo          = $_POST['tipo']         ?? '';
            $cantidad      = (int) ($_POST['cantidad'] ?? 0);
            $observaciones = trim($_POST['observaciones'] ?? '');

            $movimientoModel->registrar($productoId, $tipo, $cantidad, $observaciones);
            $_SESSION['flash_success'] = 'Movimiento registrado correctamente.';
            header("Location: movimientos.php?producto_id={$productoId}");
            exit;
        }

        $producto     = $productoModel->obtener($productoId);
        $resumen      = $movimientoModel->resumenStock($productoId);
        $totalMovs    = $movimientoModel->contarPorProducto($productoId);
        $paginaActual = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
        $totalPaginas = max(1, (int) ceil($totalMovs / POR_PAGINA_MOVIMIENTOS));
        $paginaActual = min($paginaActual, $totalPaginas);
        $movimientos  = $movimientoModel->listarPorProductoPaginado($productoId, $paginaActual, POR_PAGINA_MOVIMIENTOS);
    }

} catch (AppException $e) {
    $error = $e->getMessage();
} catch (\Throwable $e) {
    $error = 'Error inesperado: ' . $e->getMessage();
}

$entradas = (int)($resumen['entradas'] ?? 0);
$salidas  = (int)($resumen['salidas']  ?? 0);
$balance  = $entradas - $salidas;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="layout">

<?php require_once __DIR__ . '/includes/_sidebar.php'; ?>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ===== MAIN WRAPPER ===== -->
<div class="main-wrapper">

    <!-- TOPBAR -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <nav class="breadcrumb-nav">
                <a href="index.php" class="breadcrumb-item">Inicio</a>
                <span class="breadcrumb-sep">›</span>
                <a href="productos.php" class="breadcrumb-item">Productos</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active">Movimientos</span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php if ($stockBajoCount > 0): ?>
            <a href="productos.php" class="topbar-alert" title="<?= $stockBajoCount ?> producto(s) con stock bajo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="notif-badge"><?= $stockBajoCount ?></span>
            </a>
            <?php endif; ?>
            <?php require_once __DIR__ . '/includes/topbar_user.php'; ?>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="content">

        <?php if ($flashSuccess): ?>
        <div class="toast toast-success" data-autodismiss="4000">
            <span class="toast-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </span>
            <div class="toast-content">
                <span class="toast-title">Éxito</span>
                <span class="toast-message"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button class="toast-close" aria-label="Cerrar">×</button>
            <div class="toast-progress"></div>
        </div>
        <?php endif; ?>

        <?php if ($flashError || $error): ?>
        <div class="toast toast-error" data-autodismiss="6000">
            <span class="toast-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </span>
            <div class="toast-content">
                <span class="toast-title">Error</span>
                <span class="toast-message"><?= htmlspecialchars($flashError ?: $error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button class="toast-close" aria-label="Cerrar">×</button>
            <div class="toast-progress"></div>
        </div>
        <?php endif; ?>

        <?php if ($producto): ?>

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Movimientos de Stock</h1>
                <p class="page-subtitle">
                    Producto: <strong><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                    &nbsp;·&nbsp; Stock actual: <strong><?= (int)$producto['stock'] ?> uds.</strong>
                </p>
            </div>
            <div class="page-actions">
                <a href="productos.php" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Volver
                </a>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="stat-cards stat-cards-3">
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value stat-value-green"><?= $entradas ?></span>
                    <span class="stat-label">Entradas totales</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-red">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value stat-value-red"><?= $salidas ?></span>
                    <span class="stat-label">Salidas totales</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value stat-value-blue"><?= $balance ?></span>
                    <span class="stat-label">Balance neto</span>
                </div>
            </div>
        </div>

        <!-- New movement form -->
        <div class="card card-form" style="max-width:520px;">
            <div class="card-header">
                <h2 class="card-title">Registrar Movimiento</h2>
                <p class="card-subtitle">Entrada o salida de stock para este producto</p>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="producto_id" value="<?= (int)$producto['id'] ?>">

                    <div class="form-field" style="margin-bottom:1rem;">
                        <label class="field-label" for="tipo">Tipo <span class="field-required">*</span></label>
                        <select class="field-input field-select" id="tipo" name="tipo" required onchange="onTipoChange()">
                            <option value="entrada">Entrada (suma stock)</option>
                            <option value="salida">Salida (resta stock)</option>
                        </select>
                    </div>

                    <!-- Selector de kit (solo visible en salidas) -->
                    <div class="form-field" id="kit-selector-wrapper" style="margin-bottom:1rem;display:none;">
                        <label class="field-label" for="kit-selector">Aplicar kit <span class="field-hint" style="font-weight:400">(opcional)</span></label>
                        <select class="field-input field-select" id="kit-selector" onchange="onKitChange()">
                            <option value="">— Sin kit —</option>
                        </select>
                        <div id="kit-aviso-stock" style="margin-top:6px;display:none;background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;padding:8px 10px;font-size:.82rem;color:#92400e;"></div>
                        <div id="kit-lineas-preview" style="margin-top:8px;display:none;"></div>
                    </div>

                    <div class="form-field" style="margin-bottom:1rem;">
                        <label class="field-label" for="cantidad">Cantidad <span class="field-required">*</span></label>
                        <input class="field-input" type="number" id="cantidad" name="cantidad" min="1" required value="1">
                        <span class="field-hint">Número de unidades del movimiento</span>
                    </div>

                    <div class="form-field" style="margin-bottom:1.5rem;">
                        <label class="field-label" for="observaciones">Observaciones</label>
                        <textarea class="field-input field-textarea" id="observaciones" name="observaciones" rows="2"
                                  placeholder="Motivo del movimiento, proveedor, etc."></textarea>
                    </div>

                    <div class="card-footer" style="padding:0;border:0;margin-top:0;">
                        <a href="productos.php" class="btn-ghost">Cancelar</a>
                        <button type="submit" class="btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Registrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Movement history -->
        <div class="page-header" style="margin-top:2rem;">
            <div class="page-header-info">
                <h2 class="page-title" style="font-size:1.1rem;">Historial de Movimientos</h2>
                <p class="page-subtitle"><?= $totalMovs ?> movimiento(s) registrado(s)</p>
            </div>
        </div>

        <!-- Exportar CSV de movimientos -->
        <div class="export-bar" style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
            <label class="field-label" style="margin:0">Exportar CSV:</label>
            <input type="date" id="export-desde" class="field-input" style="width:160px"
                   value="<?= htmlspecialchars(date('Y-m-d', strtotime('-30 days')), ENT_QUOTES, 'UTF-8') ?>"
                   max="<?= date('Y-m-d') ?>">
            <span>hasta</span>
            <input type="date" id="export-hasta" class="field-input" style="width:160px"
                   value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                   max="<?= date('Y-m-d') ?>">
            <button type="button" onclick="exportarMovimientosCSV()" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Exportar CSV
            </button>
        </div>

        <div class="data-table-wrapper">
            <?php if (empty($movimientos)): ?>
            <div class="td-empty" style="padding:2.5rem;text-align:center;">
                No hay movimientos registrados para este producto.
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><span class="ref-code"><?= (int)$mov['id'] ?></span></td>
                        <td><?= htmlspecialchars($mov['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($mov['tipo'] === 'entrada'): ?>
                                <span class="status-pill status-pill-success">Entrada</span>
                            <?php else: ?>
                                <span class="status-pill status-pill-danger">Salida</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= (int)$mov['cantidad'] ?></strong></td>
                        <td><?= htmlspecialchars($mov['observaciones'] ?? '–', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php if ($totalPaginas > 1 || $totalMovs > 0): ?>
        <div class="pagination-bar">
            <span class="pagination-info">
                <?php
                $desde = ($paginaActual - 1) * POR_PAGINA_MOVIMIENTOS + 1;
                $hasta = min($paginaActual * POR_PAGINA_MOVIMIENTOS, $totalMovs);
                echo $totalMovs > 0
                    ? "Mostrando {$desde}–{$hasta} de {$totalMovs} movimientos"
                    : "0 movimientos";
                ?>
            </span>
            <?php if ($totalPaginas > 1): ?>
            <nav class="pagination" aria-label="Paginación de movimientos">
                <?php
                $buildUrl = fn(int $p) => "movimientos.php?producto_id={$productoId}&page={$p}";
                ?>
                <a href="<?= $buildUrl(1) ?>"
                   class="page-btn <?= $paginaActual === 1 ? 'page-btn-disabled' : '' ?>">«</a>
                <a href="<?= $buildUrl(max(1, $paginaActual - 1)) ?>"
                   class="page-btn <?= $paginaActual === 1 ? 'page-btn-disabled' : '' ?>">‹</a>
                <?php
                $start = max(1, $paginaActual - 2);
                $end   = min($totalPaginas, $paginaActual + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <a href="<?= $buildUrl($i) ?>"
                       class="page-btn <?= $i === $paginaActual ? 'page-btn-active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <a href="<?= $buildUrl(min($totalPaginas, $paginaActual + 1)) ?>"
                   class="page-btn <?= $paginaActual === $totalPaginas ? 'page-btn-disabled' : '' ?>">›</a>
                <a href="<?= $buildUrl($totalPaginas) ?>"
                   class="page-btn <?= $paginaActual === $totalPaginas ? 'page-btn-disabled' : '' ?>">»</a>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>

        <!-- Vista global: todos los movimientos -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Movimientos de Stock</h1>
                <p class="page-subtitle"><?= $totalMovs ?> movimiento(s) registrado(s) en total</p>
            </div>
        </div>

        <div class="data-table-wrapper">
            <?php if (empty($movimientos)): ?>
            <div class="td-empty" style="padding:2.5rem;text-align:center;">
                No hay movimientos registrados.
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><span class="ref-code"><?= (int)$mov['id'] ?></span></td>
                        <td><?= htmlspecialchars($mov['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="movimientos.php?producto_id=<?= (int)$mov['producto_id'] ?>" class="product-name">
                                <?= htmlspecialchars($mov['producto_nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($mov['tipo'] === 'entrada'): ?>
                                <span class="status-pill status-pill-success">Entrada</span>
                            <?php else: ?>
                                <span class="status-pill status-pill-danger">Salida</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= (int)$mov['cantidad'] ?></strong></td>
                        <td><?= htmlspecialchars($mov['observaciones'] ?? '–', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php if ($totalPaginas > 1 || $totalMovs > 0): ?>
        <div class="pagination-bar">
            <span class="pagination-info">
                <?php
                $desde = ($paginaActual - 1) * POR_PAGINA_MOVIMIENTOS + 1;
                $hasta = min($paginaActual * POR_PAGINA_MOVIMIENTOS, $totalMovs);
                echo $totalMovs > 0
                    ? "Mostrando {$desde}–{$hasta} de {$totalMovs} movimientos"
                    : "0 movimientos";
                ?>
            </span>
            <?php if ($totalPaginas > 1): ?>
            <nav class="pagination" aria-label="Paginación de movimientos">
                <?php $buildUrl = fn(int $p) => "movimientos.php?page={$p}"; ?>
                <a href="<?= $buildUrl(1) ?>"
                   class="page-btn <?= $paginaActual === 1 ? 'page-btn-disabled' : '' ?>">«</a>
                <a href="<?= $buildUrl(max(1, $paginaActual - 1)) ?>"
                   class="page-btn <?= $paginaActual === 1 ? 'page-btn-disabled' : '' ?>">‹</a>
                <?php
                $start = max(1, $paginaActual - 2);
                $end   = min($totalPaginas, $paginaActual + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <a href="<?= $buildUrl($i) ?>"
                       class="page-btn <?= $i === $paginaActual ? 'page-btn-active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <a href="<?= $buildUrl(min($totalPaginas, $paginaActual + 1)) ?>"
                   class="page-btn <?= $paginaActual === $totalPaginas ? 'page-btn-disabled' : '' ?>">›</a>
                <a href="<?= $buildUrl($totalPaginas) ?>"
                   class="page-btn <?= $paginaActual === $totalPaginas ? 'page-btn-disabled' : '' ?>">»</a>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>

    </main>
</div>

<script src="js/app.js"></script>
<script>
/* ===================================================================
 * Kit selector — Aplicar kit en movimiento de salida
 * @author Carlitos6712
 * =================================================================== */

let kitsDisponibles = [];

/**
 * Muestra u oculta el selector de kit según el tipo de movimiento.
 * @returns {void}
 */
function onTipoChange() {
    const tipo    = document.getElementById('tipo').value;
    const wrapper = document.getElementById('kit-selector-wrapper');
    if (tipo === 'salida') {
        wrapper.style.display = '';
        if (!kitsDisponibles.length) cargarKits();
    } else {
        wrapper.style.display = 'none';
        limpiarKitUI();
    }
}

/**
 * Carga los kits activos desde la API y puebla el selector.
 * @returns {Promise<void>}
 */
async function cargarKits() {
    try {
        const res  = await fetch('api/kits.php');
        const json = await res.json();
        if (!json.success) return;
        kitsDisponibles = json.data ?? [];
        const sel = document.getElementById('kit-selector');
        kitsDisponibles.forEach(k => {
            const opt = document.createElement('option');
            opt.value       = k.id;
            opt.textContent = k.nombre + (k.total_lineas ? ` (${k.total_lineas} productos)` : '');
            sel.appendChild(opt);
        });
    } catch (_) { /* silencioso */ }
}

/**
 * Carga las líneas del kit seleccionado, muestra advertencias de stock
 * y rellena automáticamente las observaciones.
 * @returns {Promise<void>}
 */
async function onKitChange() {
    const kitId = document.getElementById('kit-selector').value;
    limpiarKitUI();
    if (!kitId) return;

    try {
        const res  = await fetch(`api/kits.php?id=${kitId}&lineas`);
        const json = await res.json();
        if (!json.success) return;
        const lineas = json.data ?? [];

        // Advertencias de stock insuficiente (no bloqueantes)
        const sinStock = lineas.filter(l => l.stock_actual < l.cantidad);
        const avisoDiv = document.getElementById('kit-aviso-stock');
        if (sinStock.length) {
            avisoDiv.style.display = '';
            avisoDiv.innerHTML = '⚠ Stock insuficiente para: <strong>'
                + sinStock.map(l => escHtml(l.producto_nombre)).join(', ')
                + '</strong>. El movimiento se registrará igualmente, pero el stock quedará negativo.';
        }

        // Preview de líneas
        const previewDiv = document.getElementById('kit-lineas-preview');
        if (lineas.length) {
            let html = '<table style="width:100%;font-size:.82rem;border-collapse:collapse;">';
            html += '<thead><tr><th style="text-align:left;padding:3px 6px;background:#f1f5f9">Producto</th><th style="padding:3px 6px;background:#f1f5f9">Cant.</th><th style="padding:3px 6px;background:#f1f5f9">Stock</th></tr></thead><tbody>';
            lineas.forEach(l => {
                const warn = l.stock_actual < l.cantidad ? ' style="color:#ef4444"' : '';
                html += `<tr><td style="padding:2px 6px"${warn}>${escHtml(l.producto_nombre)}</td><td style="padding:2px 6px;text-align:center">${l.cantidad}</td><td style="padding:2px 6px;text-align:center"${warn}>${l.stock_actual}</td></tr>`;
            });
            html += '</tbody></table>';
            previewDiv.style.display = '';
            previewDiv.innerHTML = html;
        }

        // Rellenar observaciones
        const obs = document.getElementById('observaciones');
        const kitNombre = kitsDisponibles.find(k => k.id == kitId)?.nombre ?? '';
        if (!obs.value.trim()) {
            obs.value = `Aplicar kit: ${kitNombre}`;
        }

    } catch (_) { /* silencioso */ }
}

/** Limpia la UI del kit selector. */
function limpiarKitUI() {
    document.getElementById('kit-aviso-stock').style.display   = 'none';
    document.getElementById('kit-lineas-preview').style.display = 'none';
    document.getElementById('kit-aviso-stock').innerHTML        = '';
    document.getElementById('kit-lineas-preview').innerHTML     = '';
}

/** Escapa HTML para prevenir XSS. */
function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/**
 * Descarga el CSV de movimientos respetando los filtros activos.
 * Pasa producto_id y tipo si están en la URL actual.
 */
function exportarMovimientosCSV() {
    const desde = document.getElementById('export-desde').value;
    const hasta = document.getElementById('export-hasta').value;
    const params = new URLSearchParams({ export: 'csv' });
    if (desde) params.set('desde', desde);
    if (hasta) params.set('hasta', hasta);
    // Respetar filtros activos de la vista actual
    const url = new URL(window.location.href);
    const productoId = url.searchParams.get('producto_id');
    const tipo = url.searchParams.get('tipo');
    if (productoId) params.set('producto_id', productoId);
    if (tipo) params.set('tipo', tipo);
    window.location.href = 'api/movimientos.php?' + params.toString();
}
</script>
</body>
</html>
