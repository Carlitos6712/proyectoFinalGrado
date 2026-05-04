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

$error        = '';
$producto     = null;
$movimientos  = [];
$resumen      = [];
$totalMovs    = 0;
$totalPaginas = 1;

const POR_PAGINA_MOVIMIENTOS = 10;

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$productoId = filter_input(INPUT_GET, 'producto_id', FILTER_VALIDATE_INT)
           ?? filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);

try {
    $productoModel   = new Producto();
    $movimientoModel = new Movimiento();

    if (!$productoId) {
        header('Location: index.php');
        exit;
    }

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
                <a href="productos.php" class="breadcrumb-item">Productos</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active">Movimientos</span>
            </nav>
        </div>
        <div class="topbar-right">
            <div class="topbar-user">
                <div class="user-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['usuario_nombre'] ?? 'U', 0, 2)), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="user-info">
                    <span class="user-fullname"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="user-role-label">Admin</span>
                </div>
                <a href="logout.php" class="logout-btn" title="Cerrar sesión">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </a>
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
                        <select class="field-input field-select" id="tipo" name="tipo" required>
                            <option value="entrada">Entrada (suma stock)</option>
                            <option value="salida">Salida (resta stock)</option>
                        </select>
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

        <?php endif; ?>

    </main>
</div>

<script src="js/app.js"></script>
</body>
</html>
