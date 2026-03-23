<?php
/**
 * Listado principal de productos del inventario.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';
require_once __DIR__ . '/includes/Categoria.php';

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$productos      = [];
$categorias     = [];
$stockBajoCount = 0;
$error          = '';

try {
    $productoModel  = new Producto();
    $categoriaModel = new Categoria();
    $productos       = $productoModel->listar();
    $categorias      = $categoriaModel->listar();
    $stockBajoCount  = count($productoModel->filtrarStockBajo());
} catch (\Throwable $e) {
    $error = 'Error al cargar datos: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
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
                <span class="breadcrumb-item active">Productos</span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php if ($stockBajoCount > 0): ?>
            <a href="productos.php" class="topbar-alert" title="<?= $stockBajoCount ?> producto(s) con stock bajo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="notif-dot"></span>
            </a>
            <?php endif; ?>
            <div class="topbar-user">
                <div class="user-avatar">CV</div>
                <div class="user-info">
                    <span class="user-fullname">Carlos Vico</span>
                    <span class="user-role-label">Admin</span>
                </div>
            </div>
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
                <h1 class="page-title">Productos</h1>
                <p class="page-subtitle">Catálogo completo de productos del inventario</p>
            </div>
            <div class="page-actions">
                <a href="nuevo_producto.php" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo Producto
                </a>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="data-toolbar">
            <div class="search-box">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="buscar-input" class="search-input" placeholder="Buscar nombre o referencia…" autocomplete="off">
            </div>
            <select id="filtro-categoria" class="filter-select">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>">
                        <?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Data table -->
        <div class="data-table-wrapper">
            <table class="data-table" id="tabla-productos">
                <thead>
                    <tr>
                        <th class="td-check"><input type="checkbox" id="selectAll" aria-label="Seleccionar todos"></th>
                        <th>Ref</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-productos">
                    <?php foreach ($productos as $p):
                        $esBajo    = (int)$p['stock'] <= (int)($p['stock_minimo'] ?? 5);
                        $stockMax  = max((int)($p['stock_minimo'] ?? 5) * 3, 1);
                        $stockPct  = min(100, round((int)$p['stock'] / $stockMax * 100));
                        $inicial   = mb_strtoupper(mb_substr($p['nombre'], 0, 1, 'UTF-8'), 'UTF-8');
                    ?>
                    <tr class="<?= $esBajo ? 'row-low-stock' : '' ?>">
                        <td class="td-check"><input type="checkbox" aria-label="Seleccionar fila"></td>
                        <td>
                            <span class="ref-code"><?= htmlspecialchars($p['codigo_ref'] ?? '–', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <div class="product-cell">
                                <div class="product-avatar"><?= $inicial ?></div>
                                <span class="product-name"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="category-pill"><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td class="td-price"><?= number_format((float)$p['precio'], 2, ',', '.') ?> €</td>
                        <td>
                            <div class="stock-cell">
                                <span class="stock-value <?= $esBajo ? 'stock-value-low' : '' ?>"><?= (int)$p['stock'] ?></span>
                                <div class="stock-bar-track">
                                    <div class="stock-bar <?= $esBajo ? 'stock-bar-low' : '' ?>" style="width:<?= $stockPct ?>%"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($esBajo): ?>
                                <span class="status-pill status-pill-warning">Stock bajo</span>
                            <?php else: ?>
                                <span class="status-pill status-pill-success">Disponible</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-actions">
                            <a href="movimientos.php?producto_id=<?= (int)$p['id'] ?>" class="action-btn action-btn-blue" title="Ver movimientos">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                                </svg>
                            </a>
                            <a href="editar_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-green" title="Editar producto">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </a>
                            <a href="eliminar_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-red" title="Eliminar producto" data-confirm="delete">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($productos)): ?>
                    <tr>
                        <td colspan="8" class="td-empty">No hay productos registrados.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script src="js/app.js"></script>
</body>
</html>
