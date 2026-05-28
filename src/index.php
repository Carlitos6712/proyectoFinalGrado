<?php
/**
 * Dashboard principal del sistema de inventario.
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
require_once __DIR__ . '/includes/Categoria.php';
require_once __DIR__ . '/includes/Pedido.php';
require_once __DIR__ . '/includes/Kit.php';

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$totalProductos    = 0;
$valorInventario   = 0.0;
$stockBajoCount    = 0;
$movimientosMes    = 0;
$totalCategorias   = 0;
$pedidosPendientes = 0;
$totalKits         = 0;
$productosStockBajo = [];
$ultimosMovimientos = [];
$error = '';

try {
    $productoModel   = new Producto();
    $movimientoModel = new Movimiento();
    $categoriaModel  = new Categoria();
    $pedidoModel     = new Pedido();

    $kitModel           = new Kit();
    $totalProductos     = $productoModel->contarActivos();
    $valorInventario    = $productoModel->valorInventario();
    $stockBajoCount     = count($productoModel->filtrarStockBajo());
    $movimientosMes     = $movimientoModel->contarEsteMes();
    $totalCategorias    = count($categoriaModel->listar());
    $pedidosPendientes  = $pedidoModel->contarPendientes();
    $totalKits          = count($kitModel->listar());
    $productosStockBajo = $productoModel->filtrarStockBajo();
    // Solo los top 5
    $productosStockBajo = array_slice($productosStockBajo, 0, 5);
    $ultimosMovimientos = $movimientoModel->ultimosMovimientos(5);
} catch (\Throwable $e) {
    $error = 'Error al cargar el dashboard: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
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
                <span class="breadcrumb-item active">Dashboard</span>
            </nav>
        </div>
        <div class="topbar-right">
            <a href="landing.php" class="topbar-back" title="Volver al inicio">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                </svg>
                <span>Inicio</span>
            </a>
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

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Resumen general del inventario de productos</p>
            </div>
            <div class="page-actions">
                <a href="productos.php" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                    Ver todos los productos →
                </a>
                <a href="nuevo_producto.php" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo Producto
                </a>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="stat-cards">
            <a href="productos.php" class="stat-card stat-card-link">
                <div class="stat-card-icon stat-icon-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= $totalProductos ?></span>
                    <span class="stat-label">Total Productos</span>
                </div>
            </a>
            <a href="productos.php" class="stat-card stat-card-link">
                <div class="stat-card-icon stat-icon-green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= number_format($valorInventario, 2, ',', '.') ?> €</span>
                    <span class="stat-label">Valor Inventario</span>
                </div>
            </a>
            <a href="productos.php?stock_bajo=1" class="stat-card stat-card-link <?= $stockBajoCount > 0 ? 'stat-card-warning' : '' ?>">
                <div class="stat-card-icon stat-icon-orange">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= $stockBajoCount ?></span>
                    <span class="stat-label">Stock Bajo</span>
                </div>
            </a>
            <a href="movimientos.php" class="stat-card stat-card-link">
                <div class="stat-card-icon stat-icon-purple">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= $movimientosMes ?></span>
                    <span class="stat-label">Movimientos (mes)</span>
                </div>
            </a>
            <a href="pedidos.php?estado=pendientes" class="stat-card stat-card-link <?= $pedidosPendientes > 0 ? 'stat-card-warning' : '' ?>">
                <div class="stat-card-icon stat-icon-blue">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= $pedidosPendientes ?></span>
                    <span class="stat-label">Pedidos pendientes</span>
                </div>
            </a>
            <a href="kits.php" class="stat-card stat-card-link">
                <div class="stat-card-icon stat-icon-purple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= $totalKits ?></span>
                    <span class="stat-label">Kits activos</span>
                </div>
            </a>
        </div>

        <!-- Chart card -->
        <div class="card chart-card">
            <div class="card-header">
                <h2 class="card-title">Movimientos – últimos 30 días</h2>
                <p class="card-subtitle">Entradas y salidas de stock por día</p>
            </div>
            <div class="card-body">
                <canvas id="chart-movimientos" height="120"></canvas>
            </div>
        </div>

        <!-- Dashboard grid: stock bajo + últimos movimientos -->
        <div class="dashboard-grid">

            <!-- Top 5 stock bajo -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Top 5 – Stock Bajo</h2>
                    <p class="card-subtitle">Productos que requieren reposición</p>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if (empty($productosStockBajo)): ?>
                    <div class="td-empty" style="padding:2rem;text-align:center;">
                        No hay productos con stock bajo.
                    </div>
                    <?php else: ?>
                    <table class="data-table data-table-mini">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Stock</th>
                                <th>Mínimo</th>
                                <?php if (isAdmin()): ?>
                                <th>Acción</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($productosStockBajo as $p): ?>
                            <tr>
                                <td>
                                    <a href="movimientos.php?producto_id=<?= (int)$p['id'] ?>" class="product-name">
                                        <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="category-pill"><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría', ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td>
                                    <span class="stock-value stock-value-low"><?= (int)$p['stock'] ?></span>
                                </td>
                                <td><?= (int)($p['stock_minimo'] ?? 5) ?></td>
                                <?php if (isAdmin()): ?>
                                <td>
                                    <a href="nuevo_pedido.php?producto_id=<?= (int)$p['id'] ?>&cantidad=<?= max(1, ($p['stock_minimo'] ?? 5) - ($p['stock'] ?? 0)) ?>"
                                       class="btn btn-sm btn-primary">
                                        Pedir reposición
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimos movimientos -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Últimos Movimientos</h2>
                    <p class="card-subtitle">Los 5 movimientos más recientes</p>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if (empty($ultimosMovimientos)): ?>
                    <div class="td-empty" style="padding:2rem;text-align:center;">
                        No hay movimientos registrados.
                    </div>
                    <?php else: ?>
                    <table class="data-table data-table-mini">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ultimosMovimientos as $mov): ?>
                            <tr>
                                <td><?= htmlspecialchars(substr($mov['fecha'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($mov['producto_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ($mov['tipo'] === 'entrada'): ?>
                                        <span class="status-pill status-pill-success">Entrada</span>
                                    <?php else: ?>
                                        <span class="status-pill status-pill-danger">Salida</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= (int)$mov['cantidad'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- ══════════════════════════════════════════════════════
             ANALYTICS SECTION — Fase 17
             ══════════════════════════════════════════════════════ -->

        <!-- 17.3 Top 10 más vendidos -->
        <div class="page-header" style="margin-top:2.5rem;">
            <div class="page-header-info">
                <h2 class="page-title" style="font-size:1.1rem;">Top 10 — Productos más vendidos</h2>
                <p class="page-subtitle">Unidades con más salidas en el período seleccionado</p>
            </div>
            <div class="page-actions">
                <select id="top-ventas-dias" class="field-input field-select" style="width:140px;"
                        onchange="cargarTopVentas()">
                    <option value="7">Últimos 7 días</option>
                    <option value="30" selected>Últimos 30 días</option>
                    <option value="90">Últimos 90 días</option>
                </select>
            </div>
        </div>
        <div class="card" style="margin-bottom:2rem;">
            <div class="card-body" style="padding:0;">
                <div id="top-ventas-wrapper">
                    <div id="top-ventas-empty" style="display:none;text-align:center;padding:3rem;color:#94a3b8;">
                        Sin datos de ventas en este período.
                    </div>
                    <table class="data-table" id="top-ventas-table" style="display:none;">
                        <thead>
                            <tr>
                                <th style="width:3rem;text-align:center;">#</th>
                                <th>Producto</th>
                                <th style="width:9rem;text-align:right;">Unidades salidas</th>
                            </tr>
                        </thead>
                        <tbody id="top-ventas-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
(async function initDashboard() {
    const canvas = document.getElementById('chart-movimientos');
    if (!canvas) return;
    try {
        const res  = await fetch('api/movimientos.php?grafico=1&dias=30');
        const json = await res.json();
        if (!json.success || !json.data.length) {
            canvas.parentElement.innerHTML =
                '<p class="empty-chart">Sin movimientos en los últimos 30 días.</p>';
            return;
        }

        const labels   = json.data.map(r => {
            const [y, m, d] = r.fecha.split('-');
            return `${d}/${m}`;
        });
        const entradas = json.data.map(r => parseInt(r.entradas, 10));
        const salidas  = json.data.map(r => parseInt(r.salidas,  10));

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Entradas',
                        data: entradas,
                        backgroundColor: 'rgba(34,197,94,0.75)',
                        borderColor:     'rgba(34,197,94,1)',
                        borderWidth: 1,
                        borderRadius: 5,
                        borderSkipped: false
                    },
                    {
                        label: 'Salidas',
                        data: salidas,
                        backgroundColor: 'rgba(239,68,68,0.75)',
                        borderColor:     'rgba(239,68,68,1)',
                        borderWidth: 1,
                        borderRadius: 5,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, padding: 16, font: { size: 13 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y} uds.`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    } catch (e) {
        console.error('Error cargando gráfico:', e);
    }
}());
</script>
<script src="js/app.js"></script>
<script>
/* ===================================================================
 * Dashboard analítico — Fase 17
 * @author Carlitos6712
 * =================================================================== */

/** Formatea un número como euros */
function formatEuro(v) {
    return parseFloat(v).toLocaleString('es-ES', { style:'currency', currency:'EUR', minimumFractionDigits:2 });
}

/** Escapa HTML para prevenir XSS */
function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── 17.3 Top 10 más vendidos ───────────────────────────────────── */

const MEDALLAS = ['🥇','🥈','🥉'];

/**
 * Carga y renderiza la tabla ranking de top ventas.
 * @returns {Promise<void>}
 */
async function cargarTopVentas() {
    const dias  = document.getElementById('top-ventas-dias').value;
    const tbody = document.getElementById('top-ventas-tbody');
    const table = document.getElementById('top-ventas-table');
    const empty = document.getElementById('top-ventas-empty');
    try {
        const res   = await fetch(`api/dashboard.php?seccion=top_ventas&dias=${dias}&limit=10`);
        const json  = await res.json();
        const datos = json.data ?? [];

        if (!datos.length) {
            table.style.display = 'none';
            empty.style.display = '';
            return;
        }

        const max = parseInt(datos[0].total_salidas, 10) || 1;

        tbody.innerHTML = datos.map((r, i) => {
            const uds  = parseInt(r.total_salidas, 10);
            const pct  = Math.round((uds / max) * 100);
            const pos  = i < 3 ? MEDALLAS[i] : `<span style="color:#94a3b8;font-weight:600;">${i + 1}</span>`;
            return `<tr>
                <td style="text-align:center;font-size:1.1rem;">${pos}</td>
                <td>
                    <div>${escHtml(r.nombre)}</div>
                    <div style="margin-top:4px;height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden;">
                        <div style="width:${pct}%;height:100%;background:#3b82f6;border-radius:3px;"></div>
                    </div>
                </td>
                <td style="text-align:right;font-weight:600;">${uds} uds.</td>
            </tr>`;
        }).join('');

        table.style.display = '';
        empty.style.display = 'none';
    } catch (e) {
        console.error('Error top ventas:', e);
    }
}

/* ── Inicialización ─────────────────────────────────────────────── */
(function initAnalytics() {
    cargarTopVentas();
}());
</script>
</body>
</html>
