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

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$totalProductos   = 0;
$valorInventario  = 0.0;
$stockBajoCount   = 0;
$movimientosMes   = 0;
$totalCategorias  = 0;
$pedidosPendientes = 0;
$productosStockBajo = [];
$ultimosMovimientos = [];
$error = '';

try {
    $productoModel   = new Producto();
    $movimientoModel = new Movimiento();
    $categoriaModel  = new Categoria();
    $pedidoModel     = new Pedido();

    $totalProductos     = $productoModel->contarActivos();
    $valorInventario    = $productoModel->valorInventario();
    $stockBajoCount     = count($productoModel->filtrarStockBajo());
    $movimientosMes     = $movimientoModel->contarEsteMes();
    $totalCategorias    = count($categoriaModel->listar());
    $pedidosPendientes  = $pedidoModel->contarPendientes();
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

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <svg class="logo-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
            </svg>
            <span class="logo-text">es21<strong>plus</strong></span>
        </div>
        <button class="sidebar-close" id="sidebarClose" aria-label="Cerrar menú">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-label">Principal</span>
            <a href="index.php" class="nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['index.php','dashboard.php']) ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="productos.php" class="nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['productos.php','nuevo_producto.php','editar_producto.php','eliminar_producto.php']) ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                </span>
                <span class="nav-label">Productos</span>
            </a>
            <a href="categorias.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
                    </svg>
                </span>
                <span class="nav-label">Categorías</span>
            </a>
            <a href="marcas.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'marcas.php' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>
                    </svg>
                </span>
                <span class="nav-label">Marcas</span>
            </a>
            <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
            <a href="modelos_moto.php"
               class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'modelos_moto.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="18" r="3"/><path d="M6 18H4a2 2 0 0 1-2-2v-5l2-5h13l2 5v7h-3M14 18H8"/></svg></span>
                <span class="nav-label">Modelos de Moto</span>
            </a>
            <a href="proveedores.php"
               class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'proveedores.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></span>
                <span class="nav-label">Proveedores</span>
            </a>
            <a href="pedidos.php"
               class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
                <span class="nav-label">Pedidos</span>
            </a>
            <?php endif; ?>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Operaciones</span>
            <a href="movimientos.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'movimientos.php' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                    </svg>
                </span>
                <span class="nav-label">Movimientos</span>
            </a>
            <a href="kits.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'kits.php' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                </span>
                <span class="nav-label">Kits</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Administración</span>
            <a href="auditoria.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'auditoria.php' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg>
                </span>
                <span class="nav-label">Auditoría</span>
            </a>
            <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
            <a href="usuarios.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'usuarios.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="nav-label">Usuarios</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar-sm">CV</div>
            <div class="sidebar-user-info">
                <span class="user-name-sm">Carlos Vico</span>
                <span class="user-role">Administrador</span>
            </div>
        </div>
    </div>
</aside>

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
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= $totalProductos ?></span>
                    <span class="stat-label">Total Productos</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= number_format($valorInventario, 2, ',', '.') ?> €</span>
                    <span class="stat-label">Valor Inventario</span>
                </div>
            </div>
            <div class="stat-card <?= $stockBajoCount > 0 ? 'stat-card-warning' : '' ?>">
                <div class="stat-card-icon stat-icon-orange">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <a href="productos.php" class="stat-value stat-link"><?= $stockBajoCount ?></a>
                    <span class="stat-label">Stock Bajo</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-purple">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= $movimientosMes ?></span>
                    <span class="stat-label">Movimientos (mes)</span>
                </div>
            </div>
            <div class="stat-card <?= $pedidosPendientes > 0 ? 'stat-card-warning' : '' ?>">
                <div class="stat-card-icon stat-icon-blue">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </div>
                <div class="stat-card-body">
                    <a href="pedidos.php" class="stat-value stat-link"><?= $pedidosPendientes ?></a>
                    <span class="stat-label">Pedidos pendientes</span>
                </div>
            </div>
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
                                <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
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
                                <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
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
            <div class="card-body" style="padding:1.25rem;">
                <div id="top-ventas-wrapper" style="position:relative;min-height:200px;">
                    <canvas id="chart-top-ventas" style="max-height:320px;"></canvas>
                    <div id="top-ventas-empty" style="display:none;text-align:center;padding:3rem;color:#94a3b8;">
                        Sin datos de ventas en este período.
                    </div>
                </div>
            </div>
        </div>

        <!-- 17.4 Valor del inventario por categoría -->
        <div class="page-header">
            <div class="page-header-info">
                <h2 class="page-title" style="font-size:1.1rem;">Valor del inventario por categoría</h2>
                <p class="page-subtitle">Distribución del valor (precio × stock) por categoría</p>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr auto;gap:1.5rem;align-items:start;margin-bottom:2rem;">
            <div class="card">
                <div class="card-body" style="padding:1.25rem;">
                    <canvas id="chart-valor-categoria" style="max-height:300px;"></canvas>
                    <div id="valor-categoria-empty" style="display:none;text-align:center;padding:3rem;color:#94a3b8;">
                        Sin productos con stock.
                    </div>
                </div>
            </div>
            <div class="stat-card" style="min-width:180px;align-self:center;">
                <div class="stat-card-icon stat-icon-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="stat-card-body">
                    <span id="valor-total-global" class="stat-value stat-value-blue">–</span>
                    <span class="stat-label">Valor total inventario</span>
                </div>
            </div>
        </div>

        <!-- 17.5 Rotación de stock -->
        <div class="page-header">
            <div class="page-header-info">
                <h2 class="page-title" style="font-size:1.1rem;">Rotación de stock</h2>
                <p class="page-subtitle">Índice = unidades salidas / stock actual — top 10 productos</p>
            </div>
            <div class="page-actions">
                <select id="rotacion-dias" class="field-input field-select" style="width:140px;"
                        onchange="cargarRotacion()">
                    <option value="7">Últimos 7 días</option>
                    <option value="30" selected>Últimos 30 días</option>
                    <option value="90">Últimos 90 días</option>
                </select>
            </div>
        </div>
        <div class="data-table-wrapper" style="margin-bottom:2rem;">
            <table class="data-table" id="tabla-rotacion">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Producto</th>
                        <th>Stock actual</th>
                        <th>Uds. salidas</th>
                        <th>Índice rotación</th>
                    </tr>
                </thead>
                <tbody id="rotacion-body">
                    <tr><td colspan="5" style="text-align:center;padding:2rem;color:#94a3b8;">Cargando…</td></tr>
                </tbody>
            </table>
        </div>

        <!-- 17.6 Stock muerto -->
        <div class="page-header">
            <div class="page-header-info">
                <h2 class="page-title" style="font-size:1.1rem;">Stock muerto</h2>
                <p class="page-subtitle">Productos con stock sin movimiento en el umbral seleccionado</p>
            </div>
            <div class="page-actions">
                <select id="stock-muerto-umbral" class="field-input field-select" style="width:140px;"
                        onchange="cargarStockMuerto()">
                    <option value="30">30 días</option>
                    <option value="60">60 días</option>
                    <option value="90" selected>90 días</option>
                </select>
            </div>
        </div>
        <div class="data-table-wrapper" style="margin-bottom:2rem;">
            <table class="data-table" id="tabla-stock-muerto">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Stock</th>
                        <th>Valor inmovilizado</th>
                        <th>Último movimiento</th>
                    </tr>
                </thead>
                <tbody id="stock-muerto-body">
                    <tr><td colspan="5" style="text-align:center;padding:2rem;color:#94a3b8;">Cargando…</td></tr>
                </tbody>
            </table>
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

let chartTopVentas      = null;
let chartValorCategoria = null;

/** Paleta de colores para el gráfico de tarta */
const PALETA = [
    '#3b82f6','#22c55e','#f59e0b','#ef4444','#8b5cf6',
    '#06b6d4','#ec4899','#84cc16','#f97316','#6366f1'
];

/** Formatea un número como euros */
function formatEuro(v) {
    return parseFloat(v).toLocaleString('es-ES', { style:'currency', currency:'EUR', minimumFractionDigits:2 });
}

/** Escapa HTML para prevenir XSS */
function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── 17.3 Top 10 más vendidos ───────────────────────────────────── */

/**
 * Carga y renderiza el gráfico de barras horizontal de top ventas.
 * @returns {Promise<void>}
 */
async function cargarTopVentas() {
    const dias = document.getElementById('top-ventas-dias').value;
    try {
        const res  = await fetch(`api/dashboard.php?seccion=top_ventas&dias=${dias}&limit=10`);
        const json = await res.json();
        const datos = json.data ?? [];

        const canvas = document.getElementById('chart-top-ventas');
        const empty  = document.getElementById('top-ventas-empty');

        if (!datos.length) {
            canvas.style.display = 'none';
            empty.style.display  = '';
            return;
        }
        canvas.style.display = '';
        empty.style.display  = 'none';

        const labels  = datos.map(r => escHtml(r.nombre));
        const valores = datos.map(r => parseInt(r.total_salidas, 10));

        if (chartTopVentas) chartTopVentas.destroy();
        chartTopVentas = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Unidades vendidas',
                    data: valores,
                    backgroundColor: 'rgba(59,130,246,0.75)',
                    borderColor:     'rgba(59,130,246,1)',
                    borderWidth: 1,
                    borderRadius: 5,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.x} uds.`
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(0,0,0,.06)' }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 12 } }
                    }
                }
            }
        });
    } catch (e) {
        console.error('Error top ventas:', e);
    }
}

/* ── 17.4 Valor por categoría ───────────────────────────────────── */

/**
 * Carga y renderiza el gráfico doughnut de valor por categoría.
 * @returns {Promise<void>}
 */
async function cargarValorCategoria() {
    try {
        const res   = await fetch('api/dashboard.php?seccion=valor_categorias');
        const json  = await res.json();
        const datos = json.data ?? [];

        const canvas = document.getElementById('chart-valor-categoria');
        const empty  = document.getElementById('valor-categoria-empty');
        const totalEl = document.getElementById('valor-total-global');

        if (!datos.length) {
            canvas.style.display = 'none';
            empty.style.display  = '';
            totalEl.textContent  = '–';
            return;
        }
        canvas.style.display = '';
        empty.style.display  = 'none';

        const labels  = datos.map(r => r.categoria);
        const valores = datos.map(r => parseFloat(r.valor_total));
        const total   = valores.reduce((a, b) => a + b, 0);
        totalEl.textContent = formatEuro(total);

        if (chartValorCategoria) chartValorCategoria.destroy();
        chartValorCategoria = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: valores,
                    backgroundColor: PALETA.slice(0, datos.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { usePointStyle: true, padding: 12, font: { size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const v = ctx.parsed;
                                const pct = ((v / total) * 100).toFixed(1);
                                const num = datos[ctx.dataIndex].num_productos;
                                return ` ${formatEuro(v)} (${pct}%) — ${num} producto(s)`;
                            }
                        }
                    }
                }
            }
        });
    } catch (e) {
        console.error('Error valor categoría:', e);
    }
}

/* ── 17.5 Rotación de stock ─────────────────────────────────────── */

/**
 * Carga y renderiza la tabla de rotación de stock con código de color.
 * @returns {Promise<void>}
 */
async function cargarRotacion() {
    const dias  = document.getElementById('rotacion-dias').value;
    const tbody = document.getElementById('rotacion-body');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:#94a3b8;">Cargando…</td></tr>';
    try {
        const res   = await fetch(`api/dashboard.php?seccion=rotacion&dias=${dias}`);
        const json  = await res.json();
        const datos = (json.data ?? []).slice(0, 10);

        if (!datos.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:#94a3b8;">Sin productos con stock en este período.</td></tr>';
            return;
        }

        tbody.innerHTML = datos.map((r, i) => {
            const rot = parseFloat(r.rotacion);
            let color, pill;
            if (rot >= 1) {
                color = '#166534'; pill = 'background:#dcfce7;color:#166534';
            } else if (rot >= 0.5) {
                color = '#92400e'; pill = 'background:#fef3c7;color:#92400e';
            } else {
                color = '#991b1b'; pill = 'background:#fee2e2;color:#991b1b';
            }
            return `<tr>
                <td><span class="ref-code">${i + 1}</span></td>
                <td><a href="ver_producto.php?id=${r.producto_id}" style="color:inherit;text-decoration:none;font-weight:600;">${escHtml(r.nombre)}</a></td>
                <td><strong>${parseInt(r.stock, 10)}</strong> uds.</td>
                <td>${parseInt(r.total_salidas, 10)} uds.</td>
                <td><span style="padding:3px 10px;border-radius:999px;font-size:.8rem;font-weight:600;${pill}">${rot.toFixed(2)}</span></td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:#ef4444">Error: ${e.message}</td></tr>`;
    }
}

/* ── 17.6 Stock muerto ──────────────────────────────────────────── */

/**
 * Carga y renderiza la tabla de stock muerto ordenada por valor inmovilizado.
 * @returns {Promise<void>}
 */
async function cargarStockMuerto() {
    const umbral = document.getElementById('stock-muerto-umbral').value;
    const tbody  = document.getElementById('stock-muerto-body');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:#94a3b8;">Cargando…</td></tr>';
    try {
        const res   = await fetch(`api/dashboard.php?seccion=stock_muerto&umbral=${umbral}`);
        const json  = await res.json();
        const datos = json.data ?? [];

        if (!datos.length) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2rem;color:#94a3b8;">No hay stock muerto en los últimos ${umbral} días. ¡Buen trabajo!</td></tr>`;
            return;
        }

        tbody.innerHTML = datos.map(r => {
            const valorInm = parseFloat(r.precio ?? 0) * parseInt(r.stock, 10);
            const ultimo   = r.ultimo_movimiento ?? 'Nunca';
            return `<tr>
                <td><a href="ver_producto.php?id=${r.producto_id}" style="color:inherit;text-decoration:none;font-weight:600;">${escHtml(r.nombre)}</a></td>
                <td><span class="ref-code">${escHtml(r.categoria_nombre ?? '–')}</span></td>
                <td><strong>${parseInt(r.stock, 10)}</strong> uds.</td>
                <td style="font-weight:600;color:#dc2626;">${formatEuro(valorInm)}</td>
                <td style="color:#94a3b8;">${escHtml(ultimo)}</td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:#ef4444">Error: ${e.message}</td></tr>`;
    }
}

/* ── Inicialización ─────────────────────────────────────────────── */
(function initAnalytics() {
    cargarTopVentas();
    cargarValorCategoria();
    cargarRotacion();
    cargarStockMuerto();
}());
</script>
</body>
</html>
