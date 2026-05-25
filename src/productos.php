<?php
/**
 * Listado principal de productos del inventario.
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
require_once __DIR__ . '/includes/Categoria.php';
require_once __DIR__ . '/includes/Marca.php';

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$productos      = [];
$categorias     = [];
$marcas         = [];
$stockBajoCount = 0;
$error          = '';

$porPaginaOpts  = [10, 15, 25, 50, 100];
$porPaginaInput = filter_input(INPUT_GET, 'por_pagina', FILTER_VALIDATE_INT);
$porPagina      = in_array((int)$porPaginaInput, $porPaginaOpts, true) ? (int)$porPaginaInput : 15;

$validOrdenes = [
    'nombre_asc','nombre_desc','precio_asc','precio_desc','stock_asc','stock_desc',
    'fecha_asc','fecha_desc','ref_asc','ref_desc','categoria_asc','categoria_desc',
    'marca_asc','marca_desc','estado_asc','estado_desc',
];

$paginaActual  = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
$terminoBusq   = trim(filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$catFiltro     = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT) ?: null;
$marcaFiltro   = filter_input(INPUT_GET, 'marca_id',     FILTER_VALIDATE_INT) ?: null;
$ordenActivo   = in_array($_GET['orden'] ?? '', $validOrdenes, true) ? $_GET['orden'] : 'nombre_asc';
$soloStockBajo = isset($_GET['stock_bajo']) && $_GET['stock_bajo'] === '1' ? true : null;
$totalPaginas = 1;
$totalItems   = 0;

try {
    $productoModel  = new Producto();
    $categoriaModel = new Categoria();
    $marcaModel     = new Marca();

    $totalItems     = $productoModel->contarFiltrados($terminoBusq ?: null, $catFiltro, null, null, null, null, $soloStockBajo, $marcaFiltro);
    $totalPaginas   = max(1, (int) ceil($totalItems / $porPagina));
    $paginaActual   = min($paginaActual, $totalPaginas);

    $productos      = $productoModel->listarPaginado($paginaActual, $porPagina, $terminoBusq ?: null, $catFiltro, null, null, null, null, $ordenActivo, $soloStockBajo, $marcaFiltro);
    $categorias     = $categoriaModel->listar();
    $marcas         = $marcaModel->listar();
    $stockBajoCount = count($productoModel->filtrarStockBajo());
} catch (\Throwable $e) {
    $error = 'Error al cargar datos: ' . $e->getMessage();
}

// Sort link helper
$filterQs = http_build_query(array_filter([
    'q'            => $terminoBusq ?: null,
    'categoria_id' => $catFiltro,
    'marca_id'     => $marcaFiltro,
    'stock_bajo'   => $soloStockBajo ? '1' : null,
    'por_pagina'   => $porPagina !== 15 ? $porPagina : null,
], fn($v) => $v !== null && $v !== ''));

$sortTh = function(string $campo, string $label) use ($filterQs, $ordenActivo): string {
    $asc  = "{$campo}_asc";
    $desc = "{$campo}_desc";
    if ($ordenActivo === $asc) {
        $newOrden = $desc;
        $arrow    = '<span class="th-arrow th-arrow-active">↓</span>';
    } elseif ($ordenActivo === $desc) {
        $newOrden = $asc;
        $arrow    = '<span class="th-arrow th-arrow-active">↑</span>';
    } else {
        $newOrden = $asc;
        $arrow    = '<span class="th-arrow">⇅</span>';
    }
    $qs  = $filterQs ? "{$filterQs}&orden={$newOrden}" : "orden={$newOrden}";
    $url = htmlspecialchars("productos.php?{$qs}", ENT_QUOTES, 'UTF-8');
    return "<a href=\"{$url}\" class=\"th-sort-link\">{$label}{$arrow}</a>";
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .th-sortable { padding: 0 !important; }
        .th-sort-link {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 12px 16px;
            color: inherit;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            user-select: none;
        }
        .th-sort-link:hover { color: var(--accent); }
        .th-arrow {
            font-size: 0.8rem;
            color: var(--text-muted);
            opacity: .5;
            line-height: 1;
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
            <?php if (isAdmin()): ?>
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
            <?php if (isAdmin()): ?>
            <a href="usuarios.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'usuarios.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="nav-label">Usuarios</span>
            </a>
            <?php endif; ?>
            <?php if ((($_SESSION['user_role'] ?? '') === 'admin')): ?>
            <a href="perfil_empresa.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'perfil_empresa.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
                <span class="nav-label">Perfil de empresa</span>
            </a>
            <?php endif; ?>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Ayuda</span>
            <a href="soporte/mis-tickets.php" class="nav-item <?= str_contains($_SERVER['PHP_SELF'] ?? '', '/soporte/') ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                <span class="nav-label">Soporte</span>
            </a>
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
                <h1 class="page-title">Productos</h1>
                <p class="page-subtitle">Catálogo completo de productos del inventario</p>
            </div>
            <div class="page-actions">
                <button type="button" onclick="abrirModalImport()" class="btn-secondary" title="Importar productos desde CSV">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                    </svg>
                    Importar CSV
                </button>
                <a href="nuevo_producto.php" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo Producto
                </a>
            </div>
        </div>

        <?php if ($soloStockBajo): ?>
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem;">
            <span style="font-size:.8rem;color:var(--text-muted);">Filtro activo:</span>
            <span style="display:inline-flex;align-items:center;gap:.4rem;padding:.25rem .65rem;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;border-radius:999px;font-size:.8rem;font-weight:600;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                Stock bajo
                <a href="productos.php" style="color:inherit;text-decoration:none;margin-left:.2rem;font-weight:700;" title="Quitar filtro">✕</a>
            </span>
        </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <form method="get" class="data-toolbar" id="toolbar-form">
            <input type="hidden" name="orden" value="<?= htmlspecialchars($ordenActivo, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($soloStockBajo): ?><input type="hidden" name="stock_bajo" value="1"><?php endif; ?>
            <div class="search-box">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" name="q" id="buscar-input" class="search-input"
                       placeholder="Buscar nombre o referencia…" autocomplete="off"
                       value="<?= htmlspecialchars($terminoBusq, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <select name="categoria_id" id="filtro-categoria" class="filter-select" onchange="this.form.submit()">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= (int)($catFiltro ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="marca_id" id="filtro-marca" class="filter-select" onchange="this.form.submit()">
                <option value="">Todas las marcas</option>
                <?php foreach ($marcas as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= (int)($marcaFiltro ?? 0) === (int)$m['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="por_pagina" id="filtro-por-pagina" class="filter-select" onchange="this.form.submit()" style="max-width:120px" title="Productos por página">
                <?php foreach ($porPaginaOpts as $opt): ?>
                    <option value="<?= $opt ?>" <?= $porPagina === $opt ? 'selected' : '' ?>><?= $opt ?> por página</option>
                <?php endforeach; ?>
            </select>
        </form>

        <!-- Data table -->
        <div class="data-table-wrapper">
            <table class="data-table" id="tabla-productos">
                <thead>
                    <tr>
                        <th class="td-check"><input type="checkbox" id="selectAll" aria-label="Seleccionar todos"></th>
                        <th class="th-sortable"><?= $sortTh('ref',       'Ref') ?></th>
                        <th class="th-sortable"><?= $sortTh('nombre',    'Producto') ?></th>
                        <th class="th-sortable"><?= $sortTh('categoria', 'Categoría') ?></th>
                        <th class="th-sortable"><?= $sortTh('marca',     'Marca') ?></th>
                        <th class="th-sortable"><?= $sortTh('precio',    'Precio') ?></th>
                        <th class="th-sortable"><?= $sortTh('stock',     'Stock') ?></th>
                        <th class="th-sortable"><?= $sortTh('estado',    'Estado') ?></th>
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
                                <a href="ver_producto.php?id=<?= (int)$p['id'] ?>" class="product-name product-name-link"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></a>
                            </div>
                        </td>
                        <td>
                            <span class="category-pill"><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
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
                            <a href="ver_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-gray" title="Ver detalle">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </a>
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

        <?php if ($totalPaginas > 1 || $totalItems > 0): ?>
        <div class="pagination-bar">
            <span class="pagination-info">
                <?php
                $desde = ($paginaActual - 1) * $porPagina + 1;
                $hasta = min($paginaActual * $porPagina, $totalItems);
                echo $totalItems > 0
                    ? "Mostrando {$desde}–{$hasta} de {$totalItems} productos"
                    : "0 productos";
                ?>
            </span>
            <?php if ($totalPaginas > 1): ?>
            <nav class="pagination" aria-label="Paginación de productos">
                <?php
                $baseParams = [];
                if ($terminoBusq)           $baseParams['q']            = $terminoBusq;
                if ($catFiltro)             $baseParams['categoria_id'] = $catFiltro;
                if ($marcaFiltro)           $baseParams['marca_id']     = $marcaFiltro;
                if ($ordenActivo !== 'nombre_asc') $baseParams['orden'] = $ordenActivo;
                if ($porPagina !== 15)      $baseParams['por_pagina']   = $porPagina;
                $buildUrl = fn(int $p) => 'productos.php?' . http_build_query(array_merge($baseParams, ['page' => $p]));
                ?>
                <a href="<?= $buildUrl(1) ?>"
                   class="page-btn <?= $paginaActual === 1 ? 'page-btn-disabled' : '' ?>"
                   aria-label="Primera página">«</a>
                <a href="<?= $buildUrl(max(1, $paginaActual - 1)) ?>"
                   class="page-btn <?= $paginaActual === 1 ? 'page-btn-disabled' : '' ?>"
                   aria-label="Página anterior">‹</a>
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
                   class="page-btn <?= $paginaActual === $totalPaginas ? 'page-btn-disabled' : '' ?>"
                   aria-label="Página siguiente">›</a>
                <a href="<?= $buildUrl($totalPaginas) ?>"
                   class="page-btn <?= $paginaActual === $totalPaginas ? 'page-btn-disabled' : '' ?>"
                   aria-label="Última página">»</a>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="js/app.js"></script>

<!-- Modal Importar CSV -->
<div id="modal-import" class="modal-overlay" style="display:none">
    <div class="modal-box" style="max-width:600px">
        <div class="modal-header">
            <h3>Importar productos desde CSV</h3>
            <button type="button" onclick="cerrarModalImport()" class="btn-close">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:12px;color:var(--text-secondary)">
                Formato esperado: <code>Ref,Nombre,Categoria,Marca,Precio,Stock,Stock Minimo,Descripcion,Proveedor,Ubicacion</code>
            </p>
            <div class="field-group">
                <label class="field-label">Archivo CSV <span style="color:red">*</span></label>
                <input type="file" id="import-file" accept=".csv" class="field-input">
            </div>
            <div class="field-group" style="margin-top:12px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" id="import-modo-actualizacion">
                    <span>Modo actualización (actualizar si la Ref ya existe)</span>
                </label>
            </div>
            <!-- Preview primeras 5 filas -->
            <div id="import-preview" style="display:none;margin-top:16px">
                <h4 style="margin-bottom:8px">Vista previa (primeras 5 filas)</h4>
                <div style="overflow-x:auto">
                    <table class="data-table" id="preview-table">
                        <thead id="preview-head"></thead>
                        <tbody id="preview-body"></tbody>
                    </table>
                </div>
            </div>
            <!-- Resultado -->
            <div id="import-result" style="display:none;margin-top:16px"></div>
        </div>
        <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
            <button type="button" onclick="previsualizarCSV()" class="btn btn-secondary">Previsualizar</button>
            <button type="button" onclick="importarCSV()" class="btn btn-primary" id="btn-importar">Importar</button>
            <button type="button" onclick="cerrarModalImport()" class="btn btn-ghost">Cancelar</button>
        </div>
    </div>
</div>

<script>
// ── Importación CSV ────────────────────────────────────────
/**
 * Abre el modal de importación CSV y resetea su estado.
 */
function abrirModalImport() {
    document.getElementById('modal-import').style.display = 'flex';
    document.getElementById('import-result').style.display = 'none';
    document.getElementById('import-preview').style.display = 'none';
    document.getElementById('import-file').value = '';
}

/**
 * Cierra el modal de importación CSV.
 */
function cerrarModalImport() {
    document.getElementById('modal-import').style.display = 'none';
}

/**
 * Lee el CSV seleccionado y muestra una vista previa de las primeras 5 filas.
 */
function previsualizarCSV() {
    const file = document.getElementById('import-file').files[0];
    if (!file) { alert('Selecciona un archivo CSV primero.'); return; }
    const reader = new FileReader();
    reader.onload = function(e) {
        const lines = e.target.result.split('\n').filter(l => l.trim());
        if (lines.length < 2) { alert('El CSV debe tener al menos una fila de datos.'); return; }
        const headers = lines[0].replace(/^﻿/, '').split(',');
        const rows = lines.slice(1, 6); // primeras 5 filas de datos
        const thead = document.getElementById('preview-head');
        const tbody = document.getElementById('preview-body');
        thead.innerHTML = '<tr>' + headers.map(h => `<th>${h.trim()}</th>`).join('') + '</tr>';
        tbody.innerHTML = rows.map(row => {
            const cells = row.split(',');
            return '<tr>' + cells.map(c => `<td>${c.trim()}</td>`).join('') + '</tr>';
        }).join('');
        document.getElementById('import-preview').style.display = 'block';
    };
    reader.readAsText(file, 'UTF-8');
}

/**
 * Envía el archivo CSV al endpoint de importación y muestra el resultado.
 */
function importarCSV() {
    const file = document.getElementById('import-file').files[0];
    if (!file) { alert('Selecciona un archivo CSV primero.'); return; }
    const modo = document.getElementById('import-modo-actualizacion').checked ? 'actualizacion' : '';
    const formData = new FormData();
    formData.append('csv', file);
    formData.append('modo', modo);
    const btn = document.getElementById('btn-importar');
    btn.disabled = true;
    btn.textContent = 'Importando…';
    fetch('api/productos.php?action=import', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            const div = document.getElementById('import-result');
            div.style.display = 'block';
            if (data.success) {
                const d = data.data;
                const errHtml = d.errores && d.errores.length
                    ? `<ul style="margin-top:8px">${d.errores.map(e => `<li>Línea ${e.linea}: ${e.motivo}</li>`).join('')}</ul>`
                    : '';
                div.innerHTML = `<div class="alert alert-success">
                    ✅ Importación completada: <strong>${d.insertados}</strong> insertados,
                    <strong>${d.actualizados}</strong> actualizados,
                    <strong>${d.errores ? d.errores.length : 0}</strong> errores.${errHtml}
                </div>`;
            } else {
                div.innerHTML = `<div class="alert alert-error">❌ ${data.message}</div>`;
            }
        })
        .catch(() => {
            document.getElementById('import-result').innerHTML = '<div class="alert alert-error">❌ Error de red al importar.</div>';
            document.getElementById('import-result').style.display = 'block';
        })
        .finally(() => { btn.disabled = false; btn.textContent = 'Importar'; });
}
</script>
</body>
</html>
