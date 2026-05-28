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

$paginaActual = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
$terminoBusq  = trim(filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$catFiltro       = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT) ?: null;
$marcaFiltro     = filter_input(INPUT_GET, 'marca_id',     FILTER_VALIDATE_INT) ?: null;
$soloStockBajo   = isset($_GET['stock_bajo']) ? true : null;
$ordenActivo     = in_array($_GET['orden'] ?? '', $validOrdenes, true) ? $_GET['orden'] : 'nombre_asc';
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
        .product-avatar-img {
            object-fit: cover;
            background: #e2e8f0;
        }
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
                <h1 class="page-title">
                    Productos
                    <?php if ($soloStockBajo): ?>
                    <span class="status-pill status-pill-danger" style="font-size:.65rem;vertical-align:middle;margin-left:8px;">Stock bajo</span>
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    <?= $soloStockBajo ? 'Mostrando solo productos con stock por debajo del mínimo' : 'Catálogo completo de productos del inventario' ?>
                </p>
            </div>
            <div class="page-actions">
                <button type="button" onclick="abrirModalFotos()" class="btn-secondary" title="Subir fotos masivamente por código ref.">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                    Subir fotos
                </button>
                <a href="nuevo_producto.php" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo Producto
                </a>
            </div>
        </div>

        <!-- Toolbar -->
        <form method="get" class="data-toolbar" id="toolbar-form">
            <input type="hidden" name="orden" value="<?= htmlspecialchars($ordenActivo, ENT_QUOTES, 'UTF-8') ?>">
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

        <!-- Bulk action bar -->
        <div id="bulk-bar" style="display:none;padding:.7rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--radius-md);margin-bottom:.75rem;align-items:center;gap:1rem;flex-wrap:wrap;">
            <span id="bulk-count" style="font-weight:600;color:#1d4ed8;font-size:.875rem;"></span>
            <button type="button" onclick="eliminarSeleccionados()" class="btn btn-sm btn-danger" style="display:inline-flex;align-items:center;gap:.35rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                Eliminar seleccionados
            </button>
            <button type="button" onclick="deseleccionarTodo()" class="btn btn-sm btn-ghost">Cancelar</button>
        </div>

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
                        <td class="td-check"><input type="checkbox" class="row-check" data-id="<?= (int)$p['id'] ?>" aria-label="Seleccionar fila"></td>
                        <td>
                            <span class="ref-code"><?= htmlspecialchars($p['codigo_ref'] ?? '–', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <div class="product-cell">
                                <?php if (!empty($p['imagen'])): ?>
                                    <img src="<?= htmlspecialchars('uploads/productos/' . $p['imagen'], ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                         class="product-avatar product-avatar-img"
                                         onerror="this.outerHTML='<div class=\'product-avatar\'><?= $inicial ?></div>'">
                                <?php else: ?>
                                    <div class="product-avatar"><?= $inicial ?></div>
                                <?php endif; ?>
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

<!-- Modal subida masiva de fotos -->
<div id="modal-fotos" class="modal-overlay" style="display:none">
    <div class="modal-box" style="max-width:640px">
        <div class="modal-header">
            <h3>Subir fotos de productos</h3>
            <button type="button" onclick="cerrarModalFotos()" class="btn-close">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:12px;color:var(--text-secondary);font-size:.875rem">
                Nombra cada foto con el <strong>código ref.</strong> del producto.<br>
                Ejemplo: <code>2345.jpg</code> → producto con ref <code>2345</code>.<br>
                Formatos aceptados: JPG, PNG, WebP · Máx. 2 MB por imagen.
            </p>
            <!-- Zona de drop -->
            <div id="fotos-drop-zone" style="border:2px dashed #cbd5e1;border-radius:8px;padding:2rem;text-align:center;cursor:pointer;transition:border-color .2s;background:#f8fafc"
                 onclick="document.getElementById('fotos-input').click()"
                 ondragover="event.preventDefault();this.style.borderColor='#3b82f6'"
                 ondragleave="this.style.borderColor='#cbd5e1'"
                 ondrop="handleFotosDrop(event)">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" style="margin:0 auto 8px;display:block">
                    <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                <p style="color:#64748b;font-size:.875rem;margin:0">Arrastra imágenes aquí o <span style="color:#3b82f6;font-weight:600">haz clic para seleccionar</span></p>
                <input type="file" id="fotos-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none" onchange="actualizarListaFotos()">
            </div>
            <!-- Lista de archivos seleccionados -->
            <div id="fotos-lista" style="display:none;margin-top:12px;max-height:160px;overflow-y:auto;font-size:.82rem;color:#374151">
                <strong id="fotos-count"></strong>
            </div>
            <!-- Resultado -->
            <div id="fotos-result" style="display:none;margin-top:16px"></div>
        </div>
        <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
            <button type="button" onclick="subirFotos()" class="btn btn-primary" id="btn-subir-fotos">Subir fotos</button>
            <button type="button" onclick="cerrarModalFotos()" class="btn btn-ghost">Cancelar</button>
        </div>
    </div>
</div>

<script>
// ── Subida masiva de fotos ─────────────────────────────────

/** @type {File[]} */
let fotosSeleccionadas = [];

/** Abre el modal y resetea estado. */
function abrirModalFotos() {
    fotosSeleccionadas = [];
    document.getElementById('fotos-input').value = '';
    document.getElementById('fotos-lista').style.display = 'none';
    document.getElementById('fotos-result').style.display = 'none';
    document.getElementById('modal-fotos').style.display = 'flex';
}

/** Cierra el modal. */
function cerrarModalFotos() {
    document.getElementById('modal-fotos').style.display = 'none';
}

/** Actualiza la lista al seleccionar via input. */
function actualizarListaFotos() {
    fotosSeleccionadas = [...document.getElementById('fotos-input').files];
    renderListaFotos();
}

/**
 * Maneja el drop de archivos en la zona.
 * @param {DragEvent} e
 */
function handleFotosDrop(e) {
    e.preventDefault();
    document.getElementById('fotos-drop-zone').style.borderColor = '#cbd5e1';
    const nuevas = [...e.dataTransfer.files].filter(f => /\.(jpe?g|png|webp)$/i.test(f.name));
    fotosSeleccionadas = nuevas;
    renderListaFotos();
}

/** Renderiza la lista de archivos seleccionados. */
function renderListaFotos() {
    const lista = document.getElementById('fotos-lista');
    const count = document.getElementById('fotos-count');
    if (!fotosSeleccionadas.length) { lista.style.display = 'none'; return; }
    count.textContent = `${fotosSeleccionadas.length} archivo(s) seleccionado(s):`;
    lista.style.display = 'block';
}

/**
 * Envía los archivos al endpoint de subida masiva y muestra resultados.
 * @returns {Promise<void>}
 */
async function subirFotos() {
    if (!fotosSeleccionadas.length) { alert('Selecciona al menos una imagen.'); return; }
    const btn = document.getElementById('btn-subir-fotos');
    btn.disabled = true;
    btn.textContent = 'Subiendo…';
    document.getElementById('fotos-result').style.display = 'none';

    const fd = new FormData();
    fotosSeleccionadas.forEach(f => fd.append('imagenes[]', f));

    try {
        const res  = await fetch('api/productos.php?action=upload_imagenes', { method: 'POST', body: fd });
        const text = await res.text();
        const div  = document.getElementById('fotos-result');
        div.style.display = 'block';

        let json;
        try {
            json = JSON.parse(text);
        } catch {
            div.innerHTML = `<div class="alert alert-error">❌ Respuesta inválida del servidor (HTTP ${res.status}):<br><pre style="font-size:.75rem;overflow:auto;max-height:120px">${text.substring(0, 500)}</pre></div>`;
            return;
        }

        if (json.success) {
            const { ok, errores } = json.data;
            const okHtml = ok.length
                ? `<table style="width:100%;border-collapse:collapse;font-size:.82rem;margin-top:6px">
                    <thead><tr style="background:#f1f5f9"><th style="padding:4px 8px;text-align:left">Archivo</th><th style="padding:4px 8px;text-align:left">Producto</th></tr></thead>
                    <tbody>${ok.map(r => `<tr><td style="padding:3px 8px"><code>${r.archivo}</code></td><td style="padding:3px 8px">${r.producto}</td></tr>`).join('')}</tbody>
                   </table>` : '';
            const errHtml = errores.length
                ? `<ul style="margin:6px 0 0;padding-left:1.2rem;color:#b91c1c;font-size:.82rem">${errores.map(e => `<li><code>${e.archivo}</code>: ${e.motivo}</li>`).join('')}</ul>` : '';
            const reloadBtn = ok.length
                ? `<button onclick="location.reload()" class="btn btn-sm btn-primary" style="margin-top:8px">Recargar página</button>`
                : '';
            div.innerHTML = `<div class="alert ${errores.length && !ok.length ? 'alert-error' : 'alert-success'}">
                <strong>${ok.length} subida(s) correcta(s)</strong>${errores.length ? `, ${errores.length} error(es)` : ''}.
                ${okHtml}${errHtml}${reloadBtn}
            </div>`;
        } else {
            document.getElementById('fotos-result').innerHTML = `<div class="alert alert-error">❌ ${json.message}</div>`;
        }
    } catch (err) {
        document.getElementById('fotos-result').innerHTML = `<div class="alert alert-error">❌ Error de red: ${err.message}</div>`;
        document.getElementById('fotos-result').style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Subir fotos';
    }
}
</script>
<script>
/* ── Selección masiva y eliminación bulk ───────────────────────────────────── */

const selectAll   = document.getElementById('selectAll');
const tbodyProds  = document.getElementById('tbody-productos');
const bulkBar     = document.getElementById('bulk-bar');
const bulkCount   = document.getElementById('bulk-count');

/**
 * Actualiza la barra de acciones masivas según los checkboxes marcados.
 */
function actualizarBulkBar() {
    const total   = document.querySelectorAll('.row-check').length;
    const checked = document.querySelectorAll('.row-check:checked').length;
    bulkBar.style.display    = checked > 0 ? 'flex' : 'none';
    bulkCount.textContent    = `${checked} producto${checked !== 1 ? 's' : ''} seleccionado${checked !== 1 ? 's' : ''}`;
    selectAll.checked        = checked === total && total > 0;
    selectAll.indeterminate  = checked > 0 && checked < total;
}

/** Desmarca todos los checkboxes y oculta la barra. */
function deseleccionarTodo() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    selectAll.checked       = false;
    selectAll.indeterminate = false;
    bulkBar.style.display   = 'none';
}

if (selectAll) {
    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
        actualizarBulkBar();
    });
}

if (tbodyProds) {
    tbodyProds.addEventListener('change', function (e) {
        if (e.target.classList.contains('row-check')) {
            actualizarBulkBar();
        }
    });
}

/**
 * Elimina en bloque los productos seleccionados mediante la API.
 * @returns {Promise<void>}
 */
async function eliminarSeleccionados() {
    const ids = [...document.querySelectorAll('.row-check:checked')]
        .map(cb => parseInt(cb.dataset.id, 10));
    if (!ids.length) return;
    if (!confirm(`¿Eliminar ${ids.length} producto${ids.length !== 1 ? 's' : ''}? Esta acción no se puede deshacer.`)) return;

    try {
        const res  = await fetch('api/productos.php?action=bulk_delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids })
        });
        const json = await res.json();
        if (json.success) {
            ids.forEach(id => {
                const cb = document.querySelector(`.row-check[data-id="${id}"]`);
                if (cb) cb.closest('tr').remove();
            });
            deseleccionarTodo();
            // Recargar para actualizar paginación y contadores
            window.location.reload();
        } else {
            alert('Error: ' + (json.message ?? 'Error desconocido'));
        }
    } catch {
        alert('Error de red al eliminar.');
    }
}
</script>
</body>
</html>
