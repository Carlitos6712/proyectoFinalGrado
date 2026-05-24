<?php
/**
 * Búsqueda avanzada de productos del inventario.
 *
 * AJAX (?ajax=1): JSON { html, total, page, total_pages }
 * Normal: página completa con panel de filtros colapsable.
 *
 * @package  Es21Plus
 * @author   miguelrechefdez
 * @author   Carlitos6712
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';
require_once __DIR__ . '/includes/Categoria.php';
require_once __DIR__ . '/includes/Marca.php';
require_once __DIR__ . '/includes/ModeloMoto.php';

// ── Parámetros de filtrado ────────────────────────────────────────────────────
$termino     = trim($_GET['q']           ?? '');
$categoriaId = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT) ?: null;
$marcaId     = filter_input(INPUT_GET, 'marca_id',     FILTER_VALIDATE_INT) ?: null;
$modeloId    = isset($_GET['modelo_id']) && ctype_digit($_GET['modelo_id']) ? (int) $_GET['modelo_id'] : null;
$precioMin   = filter_input(INPUT_GET, 'precio_min', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
$precioMax   = filter_input(INPUT_GET, 'precio_max', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
$stockMin    = filter_input(INPUT_GET, 'stock_min',  FILTER_VALIDATE_INT,   FILTER_NULL_ON_FAILURE);
$stockMax    = filter_input(INPUT_GET, 'stock_max',  FILTER_VALIDATE_INT,   FILTER_NULL_ON_FAILURE);
$soloStockBajo = isset($_GET['stock_bajo']) && $_GET['stock_bajo'] === '1';
$orden       = in_array($_GET['orden'] ?? '', [
    'nombre_asc','nombre_desc','precio_asc','precio_desc','stock_asc','stock_desc',
    'fecha_asc','fecha_desc','ref_asc','ref_desc','categoria_asc','categoria_desc',
    'marca_asc','marca_desc','estado_asc','estado_desc',
], true) ? $_GET['orden'] : 'nombre_asc';
$pagina         = max(1, (int)(filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1));
$porPaginaOpts  = [10, 20, 25, 50, 100];
$porPaginaInput = filter_input(INPUT_GET, 'por_pagina', FILTER_VALIDATE_INT);
$porPagina      = in_array((int)$porPaginaInput, $porPaginaOpts, true) ? (int)$porPaginaInput : 20;
$soloAjax       = isset($_GET['ajax']);

// ── Consulta ──────────────────────────────────────────────────────────────────
$productos      = [];
$categorias     = [];
$total          = 0;
$totalPaginas   = 0;
$error          = '';
$stockBajoCount = 0;

try {
    $modelo         = new Producto();
    $catModelo      = new Categoria();
    $marcaModelo    = new Marca();
    $modeloMotoMod  = new ModeloMoto();
    $stockBajoCount = count($modelo->filtrarStockBajo());

    $total = $modelo->contarFiltrados(
        $termino ?: null,
        $categoriaId,
        $precioMin,
        $precioMax,
        $stockMin,
        $stockMax,
        $soloStockBajo ?: null,
        $marcaId,
        $modeloId
    );
    $productos = $modelo->listarPaginado(
        $pagina,
        $porPagina,
        $termino ?: null,
        $categoriaId,
        $precioMin,
        $precioMax,
        $stockMin,
        $stockMax,
        $orden,
        $soloStockBajo ?: null,
        $marcaId,
        $modeloId
    );
    $totalPaginas = (int) ceil($total / max(1, $porPagina));
    $categorias   = $catModelo->listar();
    $marcas       = $marcaModelo->listar();
    $modelos      = $modeloMotoMod->listarParaSelect();
} catch (\Throwable $e) {
    if ($soloAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'html'        => '<tr><td colspan="8" class="td-empty">Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</td></tr>',
            'total'       => 0,
            'page'        => 1,
            'total_pages' => 0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $error = $e->getMessage();
}

// ── Modo AJAX: JSON con HTML del tbody ────────────────────────────────────────
if ($soloAjax) {
    header('Content-Type: application/json; charset=utf-8');
    ob_start();
    foreach ($productos as $p):
        $esBajo   = (int)$p['stock'] <= (int)($p['stock_minimo'] ?? 5);
        $stockMax_ = max((int)($p['stock_minimo'] ?? 5) * 3, 1);
        $stockPct  = min(100, round((int)$p['stock'] / $stockMax_ * 100));
        $inicial   = mb_strtoupper(mb_substr($p['nombre'], 0, 1, 'UTF-8'), 'UTF-8');
?>
    <tr class="<?= $esBajo ? 'row-low-stock' : '' ?>">
        <td class="td-check"><input type="checkbox" aria-label="Seleccionar fila"></td>
        <td><span class="ref-code"><?= htmlspecialchars($p['codigo_ref'] ?? '–', ENT_QUOTES, 'UTF-8') ?></span></td>
        <td>
            <div class="product-cell">
                <div class="product-avatar"><?= $inicial ?></div>
                <span class="product-name"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </td>
        <td><span class="category-pill"><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría', ENT_QUOTES, 'UTF-8') ?></span></td>
        <td>
            <?php if (!empty($p['marca_nombre'])): ?>
                <span class="category-pill"><?= htmlspecialchars($p['marca_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
                <span style="color:var(--text-muted);font-size:.8rem;">—</span>
            <?php endif; ?>
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
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </a>
            <a href="editar_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-green" title="Editar">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </a>
            <a href="eliminar_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-red" title="Eliminar" data-confirm="delete">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </a>
        </td>
    </tr>
<?php endforeach;
    if (empty($productos)) {
        echo '<tr><td colspan="8" class="td-empty">No se encontraron productos con esos filtros.</td></tr>';
    }
    $html = ob_get_clean();
    echo json_encode([
        'html'        => $html,
        'total'       => $total,
        'page'        => $pagina,
        'total_pages' => $totalPaginas,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Hay filtros activos? ──────────────────────────────────────────────────────
$hayFiltros = $termino !== '' || $categoriaId !== null || $marcaId !== null
           || $precioMin !== null || $precioMax !== null
           || $stockMin !== null || $stockMax !== null || $soloStockBajo
           || $modeloId !== null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        /* ── Filter panel ── */
        .filter-panel {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: 1.25rem;
            overflow: hidden;
        }
        .filter-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .875rem 1.25rem;
            cursor: pointer;
            user-select: none;
            gap: .75rem;
        }
        .filter-panel-header:hover { background: var(--surface); }
        .filter-panel-title {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-weight: 600;
            font-size: .875rem;
            color: var(--text-primary);
        }
        .filter-panel-badge {
            background: var(--accent);
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            border-radius: var(--radius-full);
            padding: .1rem .45rem;
            line-height: 1.5;
        }
        .filter-chevron {
            transition: transform var(--transition-base);
            color: var(--text-muted);
            flex-shrink: 0;
        }
        .filter-panel.open .filter-chevron { transform: rotate(180deg); }
        .filter-body {
            display: none;
            padding: 1.25rem;
            border-top: 1px solid var(--border);
        }
        .filter-panel.open .filter-body { display: block; }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: .875rem 1.25rem;
        }
        .filter-field label {
            display: block;
            font-size: .75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: .35rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .filter-field input,
        .filter-field select {
            width: 100%;
            padding: .45rem .7rem;
            font-size: .875rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--surface);
            color: var(--text-primary);
            transition: border-color var(--transition-fast);
            box-sizing: border-box;
        }
        .filter-field input:focus,
        .filter-field select:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }
        .filter-checkbox-row {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-top: 1.5rem;
        }
        .filter-checkbox-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
            cursor: pointer;
        }
        .filter-checkbox-row label {
            font-size: .875rem;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
        }
        .filter-actions {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        /* ── Search bar ── */
        .search-bar-wrap {
            display: flex;
            gap: .75rem;
            margin-bottom: 1rem;
        }
        .search-input-wrap {
            flex: 1;
            position: relative;
        }
        .search-input-wrap svg {
            position: absolute;
            left: .75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        .search-input {
            width: 100%;
            padding: .6rem .75rem .6rem 2.25rem;
            font-size: .9rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--card-bg);
            color: var(--text-primary);
            box-sizing: border-box;
            transition: border-color var(--transition-fast);
        }
        .search-input:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }
        /* ── Toolbar ── */
        .search-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .search-count {
            font-size: .875rem;
            color: var(--text-secondary);
        }
        .search-count strong { color: var(--text-primary); }
        .view-toggle {
            display: flex;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        .view-btn {
            padding: .4rem .65rem;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            transition: background var(--transition-fast), color var(--transition-fast);
            line-height: 0;
        }
        .view-btn:hover, .view-btn.active { background: var(--accent-light); color: var(--accent); }
        /* ── Grid view ── */
        .products-grid {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .products-grid.active { display: grid; }
        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: .75rem;
            transition: box-shadow var(--transition-base);
        }
        .product-card:hover { box-shadow: var(--shadow-md); }
        .product-card.card-low-stock { border-left: 3px solid var(--warning); }
        .card-top {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
        }
        .card-avatar {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            background: var(--accent-light);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .card-info { flex: 1; min-width: 0; }
        .card-name {
            font-weight: 600;
            font-size: .9rem;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .card-ref { font-size: .75rem; color: var(--text-muted); margin-top: .1rem; }
        .card-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .85rem;
        }
        .card-price {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary);
        }
        .card-actions {
            display: flex;
            gap: .5rem;
            margin-top: auto;
        }
        /* ── Pagination ── */
        .pagination-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: 1rem 0;
        }
        .page-btn {
            padding: .4rem .75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--card-bg);
            color: var(--text-secondary);
            font-size: .875rem;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        .page-btn:hover { border-color: var(--accent); color: var(--accent); }
        .page-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            font-weight: 600;
        }
        .page-btn:disabled { opacity: .4; cursor: not-allowed; }
        /* ── Skeleton / loading ── */
        .search-loading { opacity: .5; pointer-events: none; }
        /* ── Sort headers ── */
        .th-sortable {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        .th-sortable:hover { color: var(--accent); }
        .th-arrow {
            display: inline-block;
            margin-left: 4px;
            font-size: 0.8rem;
            color: var(--text-muted);
            opacity: .5;
        }
        .th-arrow-active { color: var(--accent); opacity: 1; }
    </style>
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
            <a href="index.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="productos.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span>
                <span class="nav-label">Productos</span>
            </a>
            <a href="categorias.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                <span class="nav-label">Categorías</span>
            </a>
            <a href="marcas.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></span>
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
            <a href="movimientos.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></span>
                <span class="nav-label">Movimientos</span>
            </a>
            <a href="buscar.php" class="nav-item active">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                <span class="nav-label">Búsqueda</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Administración</span>
            <a href="auditoria.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'auditoria.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
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
            <div class="user-avatar-sm"><?= mb_strtoupper(mb_substr($_SESSION['user_name'] ?? $_SESSION['usuario_nombre'] ?? 'U', 0, 2)) ?></div>
            <div class="sidebar-user-info">
                <span class="user-name-sm"><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></span>
                <span class="user-role"><?= match($_SESSION['user_role'] ?? $_SESSION['rol'] ?? 'employee') { 'superadmin' => 'Super Admin', 'admin' => 'Administrador', default => 'Empleado' } ?></span>
            </div>
        </div>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ===== MAIN WRAPPER ===== -->
<div class="main-wrapper">

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
                <span class="breadcrumb-item active">Búsqueda Avanzada</span>
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

    <main class="content">

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Búsqueda Avanzada</h1>
                <p class="page-subtitle">Filtra productos por nombre, categoría, precio, stock y más</p>
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

        <?php if ($error): ?>
        <div class="toast toast-error">
            <span class="toast-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
            <div class="toast-content"><span class="toast-title">Error</span><span class="toast-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span></div>
        </div>
        <?php endif; ?>

        <!-- Search bar -->
        <div class="search-bar-wrap">
            <div class="search-input-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input
                    type="search"
                    id="searchQ"
                    class="search-input"
                    placeholder="Buscar por nombre o referencia…"
                    value="<?= htmlspecialchars($termino, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="off"
                >
            </div>
        </div>

        <!-- Filter panel -->
        <div class="filter-panel <?= $hayFiltros ? 'open' : '' ?>" id="filterPanel">
            <div class="filter-panel-header" id="filterPanelToggle">
                <div class="filter-panel-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filtros avanzados
                    <?php if ($hayFiltros): ?>
                    <span class="filter-panel-badge" id="filterBadge">activos</span>
                    <?php else: ?>
                    <span class="filter-panel-badge" id="filterBadge" style="display:none">activos</span>
                    <?php endif; ?>
                </div>
                <svg class="filter-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="filter-body">
                <div class="filter-grid">
                    <div class="filter-field">
                        <label for="filterCategoria">Categoría</label>
                        <select id="filterCategoria">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (int)($categoriaId ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="filterMarca">Marca</label>
                        <select id="filterMarca">
                            <option value="">Todas</option>
                            <?php foreach ($marcas as $m): ?>
                            <option value="<?= (int)$m['id'] ?>" <?= (int)($marcaId ?? 0) === (int)$m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="filterModeloMoto">Moto compatible</label>
                        <select id="filterModeloMoto">
                            <option value="">Todas</option>
                            <?php foreach ($modelos as $mm): ?>
                            <option value="<?= (int)$mm['id'] ?>" <?= (int)($modeloId ?? 0) === (int)$mm['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mm['marca'] . ' ' . $mm['modelo'] . ' (' . $mm['anio_desde'] . ($mm['anio_hasta'] ? '–' . $mm['anio_hasta'] : '–act.') . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="filterPrecioMin">Precio mínimo (€)</label>
                        <input type="number" id="filterPrecioMin" min="0" step="0.01" placeholder="0.00"
                            value="<?= $precioMin !== null ? htmlspecialchars((string)$precioMin, ENT_QUOTES, 'UTF-8') : '' ?>">
                    </div>
                    <div class="filter-field">
                        <label for="filterPrecioMax">Precio máximo (€)</label>
                        <input type="number" id="filterPrecioMax" min="0" step="0.01" placeholder="999.99"
                            value="<?= $precioMax !== null ? htmlspecialchars((string)$precioMax, ENT_QUOTES, 'UTF-8') : '' ?>">
                    </div>
                    <div class="filter-field">
                        <label for="filterStockMin">Stock mínimo</label>
                        <input type="number" id="filterStockMin" min="0" step="1" placeholder="0"
                            value="<?= $stockMin !== null ? (int)$stockMin : '' ?>">
                    </div>
                    <div class="filter-field">
                        <label for="filterStockMax">Stock máximo</label>
                        <input type="number" id="filterStockMax" min="0" step="1" placeholder="999"
                            value="<?= $stockMax !== null ? (int)$stockMax : '' ?>">
                    </div>
                    <div class="filter-field">
                        <label for="filterOrden">Ordenar por</label>
                        <select id="filterOrden">
                            <option value="nombre_asc"     <?= $orden === 'nombre_asc'     ? 'selected' : '' ?>>Nombre A→Z</option>
                            <option value="nombre_desc"    <?= $orden === 'nombre_desc'    ? 'selected' : '' ?>>Nombre Z→A</option>
                            <option value="ref_asc"        <?= $orden === 'ref_asc'        ? 'selected' : '' ?>>Referencia A→Z</option>
                            <option value="ref_desc"       <?= $orden === 'ref_desc'       ? 'selected' : '' ?>>Referencia Z→A</option>
                            <option value="categoria_asc"  <?= $orden === 'categoria_asc'  ? 'selected' : '' ?>>Categoría A→Z</option>
                            <option value="categoria_desc" <?= $orden === 'categoria_desc' ? 'selected' : '' ?>>Categoría Z→A</option>
                            <option value="marca_asc"      <?= $orden === 'marca_asc'      ? 'selected' : '' ?>>Marca A→Z</option>
                            <option value="marca_desc"     <?= $orden === 'marca_desc'     ? 'selected' : '' ?>>Marca Z→A</option>
                            <option value="precio_asc"     <?= $orden === 'precio_asc'     ? 'selected' : '' ?>>Precio ↑</option>
                            <option value="precio_desc"    <?= $orden === 'precio_desc'    ? 'selected' : '' ?>>Precio ↓</option>
                            <option value="stock_asc"      <?= $orden === 'stock_asc'      ? 'selected' : '' ?>>Stock ↑</option>
                            <option value="stock_desc"     <?= $orden === 'stock_desc'     ? 'selected' : '' ?>>Stock ↓</option>
                            <option value="estado_asc"     <?= $orden === 'estado_asc'     ? 'selected' : '' ?>>Estado (Disponible primero)</option>
                            <option value="estado_desc"    <?= $orden === 'estado_desc'    ? 'selected' : '' ?>>Estado (Stock bajo primero)</option>
                            <option value="fecha_desc"     <?= $orden === 'fecha_desc'     ? 'selected' : '' ?>>Más recientes</option>
                            <option value="fecha_asc"      <?= $orden === 'fecha_asc'      ? 'selected' : '' ?>>Más antiguos</option>
                        </select>
                    </div>
                </div>
                <div class="filter-checkbox-row">
                    <input type="checkbox" id="filterStockBajo" <?= $soloStockBajo ? 'checked' : '' ?>>
                    <label for="filterStockBajo">Solo productos con stock bajo</label>
                </div>
                <div class="filter-actions">
                    <button class="btn-primary" id="btnApply">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        Aplicar filtros
                    </button>
                    <button class="btn-ghost" id="btnClear">Limpiar filtros</button>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="search-toolbar">
            <span class="search-count" id="searchCount">
                <?php if ($hayFiltros): ?>
                    <strong><?= $total ?></strong> resultado<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?>
                <?php else: ?>
                    <strong><?= $total ?></strong> producto<?= $total !== 1 ? 's' : '' ?> en total
                <?php endif; ?>
            </span>
            <select id="filterPorPagina" class="filter-select" style="max-width:130px" title="Resultados por página">
                <?php foreach ($porPaginaOpts as $opt): ?>
                    <option value="<?= $opt ?>" <?= $porPagina === $opt ? 'selected' : '' ?>><?= $opt ?> por página</option>
                <?php endforeach; ?>
            </select>
            <div class="view-toggle">
                <button class="view-btn active" id="btnViewTable" title="Vista tabla" aria-label="Vista tabla">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                </button>
                <button class="view-btn" id="btnViewGrid" title="Vista cuadrícula" aria-label="Vista cuadrícula">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </button>
            </div>
        </div>

        <!-- Table view -->
        <div class="card" id="tableView">
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <table class="data-table" data-sortable>
                    <thead>
                        <tr>
                            <th class="td-check"></th>
                            <th class="th-sortable" data-sort-col="ref">Ref.<span class="th-arrow">⇅</span></th>
                            <th class="th-sortable" data-sort-col="nombre">Producto<span class="th-arrow">⇅</span></th>
                            <th class="th-sortable" data-sort-col="categoria">Categoría<span class="th-arrow">⇅</span></th>
                            <th class="th-sortable" data-sort-col="precio">Precio<span class="th-arrow">⇅</span></th>
                            <th class="th-sortable" data-sort-col="stock">Stock<span class="th-arrow">⇅</span></th>
                            <th class="th-sortable" data-sort-col="estado">Estado<span class="th-arrow">⇅</span></th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="resultsTbody">
                    <?php foreach ($productos as $p):
                        $esBajo   = (int)$p['stock'] <= (int)($p['stock_minimo'] ?? 5);
                        $stockMaxV = max((int)($p['stock_minimo'] ?? 5) * 3, 1);
                        $stockPct  = min(100, round((int)$p['stock'] / $stockMaxV * 100));
                        $inicial   = mb_strtoupper(mb_substr($p['nombre'], 0, 1, 'UTF-8'), 'UTF-8');
                    ?>
                    <tr class="<?= $esBajo ? 'row-low-stock' : '' ?>">
                        <td class="td-check"><input type="checkbox" aria-label="Seleccionar fila"></td>
                        <td><span class="ref-code"><?= htmlspecialchars($p['codigo_ref'] ?? '–', ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <div class="product-cell">
                                <div class="product-avatar"><?= $inicial ?></div>
                                <span class="product-name"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </td>
                        <td><span class="category-pill"><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría', ENT_QUOTES, 'UTF-8') ?></span></td>
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
                            <a href="movimientos.php?producto_id=<?= (int)$p['id'] ?>" class="action-btn action-btn-blue" title="Movimientos">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            </a>
                            <a href="editar_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-green" title="Editar">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <a href="eliminar_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-red" title="Eliminar" data-confirm="delete">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($productos)): ?>
                    <tr><td colspan="8" class="td-empty">No se encontraron productos.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Grid view -->
        <div class="products-grid" id="gridView">
            <?php foreach ($productos as $p):
                $esBajo  = (int)$p['stock'] <= (int)($p['stock_minimo'] ?? 5);
                $inicial = mb_strtoupper(mb_substr($p['nombre'], 0, 1, 'UTF-8'), 'UTF-8');
            ?>
            <div class="product-card <?= $esBajo ? 'card-low-stock' : '' ?>">
                <div class="card-top">
                    <div class="card-avatar"><?= $inicial ?></div>
                    <div class="card-info">
                        <div class="card-name"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="card-ref"><?= htmlspecialchars($p['codigo_ref'] ?? '–', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                <div class="card-row">
                    <span class="category-pill"><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($esBajo): ?>
                        <span class="status-pill status-pill-warning">Stock bajo</span>
                    <?php else: ?>
                        <span class="status-pill status-pill-success">Disponible</span>
                    <?php endif; ?>
                </div>
                <div class="card-row">
                    <span class="card-price"><?= number_format((float)$p['precio'], 2, ',', '.') ?> €</span>
                    <span class="stock-value <?= $esBajo ? 'stock-value-low' : '' ?>">Stock: <?= (int)$p['stock'] ?></span>
                </div>
                <div class="card-actions">
                    <a href="movimientos.php?producto_id=<?= (int)$p['id'] ?>" class="action-btn action-btn-blue" title="Movimientos">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    </a>
                    <a href="editar_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-green" title="Editar">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <a href="eliminar_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-red" title="Eliminar" data-confirm="delete">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($productos)): ?>
            <p style="color:var(--text-muted);text-align:center;padding:2rem;">No se encontraron productos.</p>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrap" id="paginationWrap">
            <?php if ($totalPaginas > 1): ?>
                <?php if ($pagina > 1): ?>
                <button class="page-btn" data-page="<?= $pagina - 1 ?>">‹ Anterior</button>
                <?php endif; ?>
                <?php for ($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++): ?>
                <button class="page-btn <?= $i === $pagina ? 'active' : '' ?>" data-page="<?= $i ?>"><?= $i ?></button>
                <?php endfor; ?>
                <?php if ($pagina < $totalPaginas): ?>
                <button class="page-btn" data-page="<?= $pagina + 1 ?>">Siguiente ›</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<script src="js/app.js"></script>
<script>
    // Datos iniciales para el módulo de búsqueda
    window.__searchState = {
        page:        <?= $pagina ?>,
        total_pages: <?= $totalPaginas ?>,
        total:       <?= $total ?>,
        has_filters: <?= $hayFiltros ? 'true' : 'false' ?>
    };
</script>
</body>
</html>
