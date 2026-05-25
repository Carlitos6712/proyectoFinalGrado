<?php
/**
 * Historial y registro de movimientos de stock.
 * Modo global (sin producto_id) o por producto (con ?producto_id=X).
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @version  1.1.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';
require_once __DIR__ . '/includes/Movimiento.php';

const POR_PAGINA_MOVIMIENTOS = 15;

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Parámetros comunes ───────────────────────────────────────────────────────
$productoId = filter_input(INPUT_GET,  'producto_id', FILTER_VALIDATE_INT)
           ?? filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);

$modoGlobal = ($productoId === null || $productoId === false);

// Filtros (modo global)
$filtroTipo    = $_GET['tipo']    ?? '';
$filtroDesde   = $_GET['desde']  ?? '';
$filtroHasta   = $_GET['hasta']  ?? '';
$filtroProdId  = filter_input(INPUT_GET, 'filtro_producto', FILTER_VALIDATE_INT) ?: null;
$paginaActual  = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));

// Inicializar variables
$producto       = null;
$movimientos    = [];
$resumen        = [];
$totalMovs      = 0;
$totalPaginas   = 1;
$stockBajoCount = 0;
$todosProductos = [];
$statsGlobal    = ['entradas' => 0, 'salidas' => 0, 'total' => 0];
$error          = '';

try {
    $productoModel   = new Producto();
    $movimientoModel = new Movimiento();
    $stockBajoCount  = count($productoModel->filtrarStockBajo());

    // ── POST: registrar movimiento ───────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postProductoId = filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);
        $tipo           = $_POST['tipo']         ?? '';
        $cantidad       = (int) ($_POST['cantidad'] ?? 0);
        $observaciones  = trim($_POST['observaciones'] ?? '');

        if (!$postProductoId) {
            throw new AppException('Debes seleccionar un producto.', 400);
        }
        $movimientoModel->registrar($postProductoId, $tipo, $cantidad, $observaciones);
        $_SESSION['flash_success'] = 'Movimiento registrado correctamente.';

        // Redirigir conservando contexto
        $qs = $modoGlobal
            ? http_build_query(array_filter(compact('filtroTipo','filtroDesde','filtroHasta') + ['tipo'=>$filtroTipo,'desde'=>$filtroDesde,'hasta'=>$filtroHasta]))
            : "producto_id={$postProductoId}";
        header("Location: movimientos.php" . ($qs ? "?{$qs}" : ''));
        exit;
    }

    if ($modoGlobal) {
        // ── MODO GLOBAL ──────────────────────────────────────────────────────
        $todosProductos = $productoModel->listar();

        $tipoFiltro   = in_array($filtroTipo, ['entrada','salida'], true) ? $filtroTipo : null;
        $desdeFiltro  = $filtroDesde ?: null;
        $hastaFiltro  = $filtroHasta ?: null;

        $totalMovs    = $movimientoModel->contarGlobal($filtroProdId, $tipoFiltro, $desdeFiltro, $hastaFiltro);
        $totalPaginas = max(1, (int) ceil($totalMovs / POR_PAGINA_MOVIMIENTOS));
        $paginaActual = min($paginaActual, $totalPaginas);
        $movimientos  = $movimientoModel->listarGlobalPaginado(
            $paginaActual, POR_PAGINA_MOVIMIENTOS,
            $filtroProdId, $tipoFiltro, $desdeFiltro, $hastaFiltro
        );

        // Stats globales (sin filtros de fecha para mostrar totales)
        $statsGlobal['total']    = $movimientoModel->contarGlobal();
        $statsGlobal['entradas'] = $movimientoModel->contarGlobal(null, 'entrada');
        $statsGlobal['salidas']  = $movimientoModel->contarGlobal(null, 'salida');

    } else {
        // ── MODO PER-PRODUCTO ────────────────────────────────────────────────
        $producto     = $productoModel->obtener($productoId);
        $resumen      = $movimientoModel->resumenStock($productoId);
        $totalMovs    = $movimientoModel->contarPorProducto($productoId);
        $totalPaginas = max(1, (int) ceil($totalMovs / POR_PAGINA_MOVIMIENTOS));
        $paginaActual = min($paginaActual, $totalPaginas);
        $movimientos  = $movimientoModel->listarPorProductoPaginado($productoId, $paginaActual, POR_PAGINA_MOVIMIENTOS);
        $todosProductos = [$producto]; // solo necesario para formulario
    }

} catch (AppException $e) {
    $error = $e->getMessage();
} catch (\Throwable $e) {
    $error = 'Error inesperado: ' . $e->getMessage();
}

$entradas = (int)($resumen['entradas'] ?? 0);
$salidas  = (int)($resumen['salidas']  ?? 0);
$balance  = $entradas - $salidas;

/**
 * Construye URL de paginación conservando todos los filtros GET activos.
 */
function buildPaginationUrl(int $page, array $extra = []): string {
    $params = array_merge($_GET, $extra, ['page' => $page]);
    unset($params['page']);
    $params['page'] = $page;
    return 'movimientos.php?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .filter-bar{display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;margin-bottom:1.25rem;padding:1rem 1.25rem;background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:var(--radius,8px);}
        .filter-bar .filter-group{display:flex;flex-direction:column;gap:.25rem;}
        .filter-bar label{font-size:.75rem;font-weight:600;color:var(--color-text-muted,#6b7280);}
        .filter-bar .field-input{min-width:150px;}
        .filter-actions{display:flex;gap:.5rem;margin-left:auto;align-items:flex-end;}
        .stat-cards-3{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.25rem;}
        @media(max-width:640px){.stat-cards-3{grid-template-columns:1fr;}}
        .tipo-entrada{color:#15803d;font-weight:600;}
        .tipo-salida {color:#b91c1c;font-weight:600;}
        .badge-entrada{display:inline-flex;align-items:center;gap:4px;background:#dcfce7;color:#15803d;padding:2px 10px;border-radius:999px;font-size:.8rem;font-weight:600;}
        .badge-salida {display:inline-flex;align-items:center;gap:4px;background:#fee2e2;color:#b91c1c;padding:2px 10px;border-radius:999px;font-size:.8rem;font-weight:600;}
        .btn-form-inline{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:var(--color-primary,#2563eb);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.9rem;font-weight:600;}
        .btn-form-inline:hover{opacity:.9;}
        .btn-ghost-sm{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:transparent;color:var(--color-primary,#2563eb);border:1px solid var(--color-primary,#2563eb);border-radius:6px;cursor:pointer;font-size:.85rem;text-decoration:none;}
        .btn-ghost-sm:hover{background:var(--color-primary-light,#eff6ff);}
        .form-inline-card{background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:var(--radius,8px);padding:1.25rem;margin-bottom:1.25rem;}
        .form-inline-card h3{margin:0 0 .75rem;font-size:1rem;font-weight:700;}
        .form-inline-row{display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;}
        .form-inline-row .form-field{display:flex;flex-direction:column;gap:.25rem;flex:1;min-width:140px;}
        .form-inline-row label{font-size:.75rem;font-weight:600;color:var(--color-text-muted,#6b7280);}
        .form-inline-row .field-input{width:100%;}
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
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="productos.php" class="nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['productos.php','nuevo_producto.php','editar_producto.php','eliminar_producto.php']) ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span>
                <span class="nav-label">Productos</span>
            </a>
            <a href="categorias.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                <span class="nav-label">Categorías</span>
            </a>
            <a href="marcas.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'marcas.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></span>
                <span class="nav-label">Marcas</span>
            </a>
            <?php if (isAdmin()): ?>
            <a href="modelos_moto.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'modelos_moto.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="18" r="3"/><path d="M6 18H4a2 2 0 0 1-2-2v-5l2-5h13l2 5v7h-3M14 18H8"/></svg></span>
                <span class="nav-label">Modelos de Moto</span>
            </a>
            <a href="proveedores.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'proveedores.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></span>
                <span class="nav-label">Proveedores</span>
            </a>
            <a href="pedidos.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
                <span class="nav-label">Pedidos</span>
            </a>
            <?php endif; ?>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Operaciones</span>
            <a href="movimientos.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'movimientos.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></span>
                <span class="nav-label">Movimientos</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Administración</span>
            <a href="auditoria.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'auditoria.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
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

<!-- ===== MAIN ===== -->
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
                <?php if (!$modoGlobal && $producto): ?>
                <a href="movimientos.php" class="breadcrumb-item">Movimientos</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active"><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                <span class="breadcrumb-item active">Movimientos</span>
                <?php endif; ?>
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

    <main class="content">

        <?php if ($flashSuccess): ?>
        <div class="toast toast-success" data-autodismiss="4000">
            <span class="toast-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span>
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
            <span class="toast-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
            <div class="toast-content">
                <span class="toast-title">Error</span>
                <span class="toast-message"><?= htmlspecialchars($flashError ?: $error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button class="toast-close" aria-label="Cerrar">×</button>
            <div class="toast-progress"></div>
        </div>
        <?php endif; ?>

        <?php if ($modoGlobal): ?>
        <!-- ================================================================ -->
        <!-- MODO GLOBAL                                                       -->
        <!-- ================================================================ -->

        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Movimientos de Stock</h1>
                <p class="page-subtitle">Historial completo de entradas y salidas · <?= $totalMovs ?> registro(s) con filtros aplicados</p>
            </div>
            <div class="page-actions">
                <button type="button" class="btn-primary" onclick="toggleFormRegistro()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nuevo movimiento
                </button>
            </div>
        </div>

        <!-- Stats globales -->
        <div class="stat-cards stat-cards-3">
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= number_format($statsGlobal['total']) ?></span>
                    <span class="stat-label">Total movimientos</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value" style="color:#15803d"><?= number_format($statsGlobal['entradas']) ?></span>
                    <span class="stat-label">Entradas</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-red">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value" style="color:#b91c1c"><?= number_format($statsGlobal['salidas']) ?></span>
                    <span class="stat-label">Salidas</span>
                </div>
            </div>
        </div>

        <!-- Formulario nuevo movimiento (colapsable) -->
        <div id="formRegistro" class="form-inline-card" style="display:none;">
            <h3>Registrar nuevo movimiento</h3>
            <form method="POST">
                <div class="form-inline-row">
                    <div class="form-field">
                        <label for="reg_producto">Producto <span style="color:#ef4444">*</span></label>
                        <select class="field-input field-select" id="reg_producto" name="producto_id" required>
                            <option value="">— Selecciona producto —</option>
                            <?php foreach ($todosProductos as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                (stock: <?= (int)$p['stock'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field" style="max-width:160px">
                        <label for="reg_tipo">Tipo <span style="color:#ef4444">*</span></label>
                        <select class="field-input field-select" id="reg_tipo" name="tipo" required>
                            <option value="entrada">Entrada ↑</option>
                            <option value="salida">Salida ↓</option>
                        </select>
                    </div>
                    <div class="form-field" style="max-width:120px">
                        <label for="reg_cantidad">Cantidad <span style="color:#ef4444">*</span></label>
                        <input class="field-input" type="number" id="reg_cantidad" name="cantidad" min="1" value="1" required>
                    </div>
                    <div class="form-field" style="flex:2">
                        <label for="reg_obs">Observaciones</label>
                        <input class="field-input" type="text" id="reg_obs" name="observaciones" placeholder="Motivo, proveedor, OT...">
                    </div>
                    <div class="form-field" style="max-width:fit-content">
                        <label style="visibility:hidden">Acción</label>
                        <button type="submit" class="btn-form-inline">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Filtros -->
        <form method="GET" id="filtrosForm">
            <div class="filter-bar">
                <div class="filter-group">
                    <label>Producto</label>
                    <select class="field-input field-select" name="filtro_producto" style="min-width:200px">
                        <option value="">Todos los productos</option>
                        <?php foreach ($todosProductos as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (int)($filtroProdId ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Tipo</label>
                    <select class="field-input field-select" name="tipo">
                        <option value="">Todos</option>
                        <option value="entrada" <?= $filtroTipo === 'entrada' ? 'selected' : '' ?>>Entrada ↑</option>
                        <option value="salida"  <?= $filtroTipo === 'salida'  ? 'selected' : '' ?>>Salida ↓</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Desde</label>
                    <input class="field-input" type="date" name="desde" value="<?= htmlspecialchars($filtroDesde, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="filter-group">
                    <label>Hasta</label>
                    <input class="field-input" type="date" name="hasta" value="<?= htmlspecialchars($filtroHasta, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-form-inline">Filtrar</button>
                    <a href="movimientos.php" class="btn-ghost-sm">Limpiar</a>
                    <button type="button" onclick="exportarCSV()" class="btn-ghost-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        CSV
                    </button>
                </div>
            </div>
        </form>

        <!-- Tabla global -->
        <div class="data-table-wrapper">
            <?php if (empty($movimientos)): ?>
            <div class="td-empty" style="padding:2.5rem;text-align:center;">
                No hay movimientos con los filtros aplicados.
                <a href="movimientos.php" style="display:block;margin-top:.5rem;color:var(--color-primary)">Limpiar filtros</a>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Observaciones</th>
                        <th>Usuario</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><span class="ref-code"><?= (int)$mov['id'] ?></span></td>
                        <td style="white-space:nowrap"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($mov['fecha'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="ver_producto.php?id=<?= (int)$mov['producto_id'] ?>" style="font-weight:600;color:inherit">
                                <?= htmlspecialchars($mov['producto_nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <?php if (!empty($mov['codigo_ref'])): ?>
                            <br><small class="ref-code" style="font-size:.72rem"><?= htmlspecialchars($mov['codigo_ref'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($mov['categoria_nombre'] ?? '–', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($mov['tipo'] === 'entrada'): ?>
                            <span class="badge-entrada">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                                Entrada
                            </span>
                            <?php else: ?>
                            <span class="badge-salida">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                                Salida
                            </span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= (int)$mov['cantidad'] ?> uds.</strong></td>
                        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($mov['observaciones'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($mov['observaciones'] ?? '–', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td><?= htmlspecialchars($mov['usuario'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="movimientos.php?producto_id=<?= (int)$mov['producto_id'] ?>" class="btn-ghost-sm" style="padding:4px 8px;font-size:.75rem">Ver producto</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- ================================================================ -->
        <!-- MODO PER-PRODUCTO                                                 -->
        <!-- ================================================================ -->

        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Movimientos de Stock</h1>
                <p class="page-subtitle">
                    Producto: <strong><?= htmlspecialchars($producto['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                    &nbsp;·&nbsp; Stock actual: <strong><?= (int)($producto['stock'] ?? 0) ?> uds.</strong>
                </p>
            </div>
            <div class="page-actions">
                <a href="movimientos.php" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    Todos los movimientos
                </a>
                <a href="productos.php" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    Productos
                </a>
            </div>
        </div>

        <!-- Stats per-producto -->
        <div class="stat-cards stat-cards-3">
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value" style="color:#15803d"><?= $entradas ?></span>
                    <span class="stat-label">Entradas totales</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-red">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value" style="color:#b91c1c"><?= $salidas ?></span>
                    <span class="stat-label">Salidas totales</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon stat-icon-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                </div>
                <div class="stat-card-body">
                    <span class="stat-value"><?= $balance ?></span>
                    <span class="stat-label">Balance neto</span>
                </div>
            </div>
        </div>

        <!-- Formulario registro per-producto -->
        <div class="card card-form" style="max-width:520px;">
            <div class="card-header">
                <h2 class="card-title">Registrar Movimiento</h2>
                <p class="card-subtitle">Entrada o salida para este producto</p>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="producto_id" value="<?= (int)($producto['id'] ?? 0) ?>">
                    <div class="form-field" style="margin-bottom:1rem;">
                        <label class="field-label" for="tipo">Tipo <span class="field-required">*</span></label>
                        <select class="field-input field-select" id="tipo" name="tipo" required>
                            <option value="entrada">Entrada ↑ (suma stock)</option>
                            <option value="salida">Salida ↓ (resta stock)</option>
                        </select>
                    </div>
                    <div class="form-field" style="margin-bottom:1rem;">
                        <label class="field-label" for="cantidad">Cantidad <span class="field-required">*</span></label>
                        <input class="field-input" type="number" id="cantidad" name="cantidad" min="1" required value="1">
                    </div>
                    <div class="form-field" style="margin-bottom:1.5rem;">
                        <label class="field-label" for="observaciones">Observaciones</label>
                        <textarea class="field-input field-textarea" id="observaciones" name="observaciones" rows="2" placeholder="Motivo, proveedor, OT..."></textarea>
                    </div>
                    <div class="card-footer" style="padding:0;border:0;margin-top:0;">
                        <a href="productos.php" class="btn-ghost">Cancelar</a>
                        <button type="submit" class="btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Registrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Historial per-producto -->
        <div class="page-header" style="margin-top:2rem;">
            <div class="page-header-info">
                <h2 class="page-title" style="font-size:1.1rem;">Historial</h2>
                <p class="page-subtitle"><?= $totalMovs ?> movimiento(s)</p>
            </div>
            <div class="page-actions">
                <!-- Export CSV -->
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <input type="date" id="export-desde" class="field-input" style="width:145px" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                    <span style="font-size:.85rem">–</span>
                    <input type="date" id="export-hasta" class="field-input" style="width:145px" value="<?= date('Y-m-d') ?>">
                    <button type="button" onclick="exportarMovimientosCSV()" class="btn-ghost-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        CSV
                    </button>
                </div>
            </div>
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
                        <th>#</th><th>Fecha</th><th>Tipo</th><th>Cantidad</th><th>Observaciones</th><th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><span class="ref-code"><?= (int)$mov['id'] ?></span></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($mov['fecha'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($mov['tipo'] === 'entrada'): ?>
                            <span class="badge-entrada"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>Entrada</span>
                            <?php else: ?>
                            <span class="badge-salida"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>Salida</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= (int)$mov['cantidad'] ?> uds.</strong></td>
                        <td><?= htmlspecialchars($mov['observaciones'] ?? '–', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($mov['usuario'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- PAGINACIÓN (compartida)                                           -->
        <!-- ================================================================ -->
        <?php if ($totalPaginas > 1): ?>
        <div class="pagination-bar">
            <span class="pagination-info">
                <?php
                $offset_info = ($paginaActual - 1) * POR_PAGINA_MOVIMIENTOS + 1;
                $limit_info  = min($paginaActual * POR_PAGINA_MOVIMIENTOS, $totalMovs);
                echo $totalMovs > 0
                    ? "Mostrando {$offset_info}–{$limit_info} de {$totalMovs} movimientos"
                    : "0 movimientos";
                ?>
            </span>
            <nav class="pagination" aria-label="Paginación">
                <a href="<?= buildPaginationUrl(1) ?>" class="page-btn <?= $paginaActual === 1 ? 'page-btn-disabled' : '' ?>">«</a>
                <a href="<?= buildPaginationUrl(max(1, $paginaActual - 1)) ?>" class="page-btn <?= $paginaActual === 1 ? 'page-btn-disabled' : '' ?>">‹</a>
                <?php for ($i = max(1, $paginaActual - 2); $i <= min($totalPaginas, $paginaActual + 2); $i++): ?>
                <a href="<?= buildPaginationUrl($i) ?>" class="page-btn <?= $i === $paginaActual ? 'page-btn-active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="<?= buildPaginationUrl(min($totalPaginas, $paginaActual + 1)) ?>" class="page-btn <?= $paginaActual === $totalPaginas ? 'page-btn-disabled' : '' ?>">›</a>
                <a href="<?= buildPaginationUrl($totalPaginas) ?>" class="page-btn <?= $paginaActual === $totalPaginas ? 'page-btn-disabled' : '' ?>">»</a>
            </nav>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="js/app.js"></script>
<script>
/**
 * Alterna visibilidad del formulario de nuevo movimiento.
 */
function toggleFormRegistro() {
    const el = document.getElementById('formRegistro');
    if (!el) return;
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
    if (el.style.display === 'block') {
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        el.querySelector('select,input')?.focus();
    }
}

/**
 * Exporta CSV respetando filtros activos del formulario de filtros.
 */
function exportarCSV() {
    const params = new URLSearchParams({ export: 'csv' });
    const form = document.getElementById('filtrosForm');
    if (form) {
        new FormData(form).forEach((v, k) => { if (v) params.set(k, v); });
    }
    window.location.href = 'api/movimientos.php?' + params.toString();
}

/**
 * Exporta CSV en modo per-producto con rango de fechas manual.
 */
function exportarMovimientosCSV() {
    const desde = document.getElementById('export-desde')?.value;
    const hasta  = document.getElementById('export-hasta')?.value;
    const params = new URLSearchParams({ export: 'csv' });
    if (desde) params.set('desde', desde);
    if (hasta)  params.set('hasta',  hasta);
    const url = new URL(window.location.href);
    const pid = url.searchParams.get('producto_id');
    if (pid) params.set('producto_id', pid);
    window.location.href = 'api/movimientos.php?' + params.toString();
}
</script>
</body>
</html>
