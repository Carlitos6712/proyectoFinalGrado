<?php
/**
 * Eliminación (soft-delete) de un producto del inventario.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';

try {
    $productoModel = new Producto();

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
       ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        header('Location: productos.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
        $movimientos = $productoModel->contarMovimientos($id);
        // Permitimos soft delete aunque tenga movimientos (solo avisamos)
        $productoModel->eliminar($id);
        $_SESSION['flash_success'] = 'Producto eliminado correctamente.';
        header('Location: productos.php');
        exit;
    }

    $producto = $productoModel->obtener($id);
    $totalMovimientos = $productoModel->contarMovimientos($id);

} catch (AppException $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: productos.php');
    exit;
} catch (\Throwable $e) {
    $_SESSION['flash_error'] = 'Error inesperado al eliminar.';
    header('Location: productos.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Producto – es21plus</title>
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
                <span class="breadcrumb-item active">Eliminar Producto</span>
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

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Eliminar Producto</h1>
                <p class="page-subtitle">Confirma la eliminación del producto seleccionado</p>
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

        <?php if (isset($producto)): ?>
        <div class="card card-confirm">

            <div class="confirm-icon-wrap">
                <div class="confirm-icon-circle confirm-icon-circle-danger">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                    </svg>
                </div>
                <h2 class="confirm-title">¿Eliminar este producto?</h2>
                <p class="confirm-subtitle">Esta acción marcará el producto como inactivo. El historial de movimientos se conservará.</p>
            </div>

            <table class="detail-table">
                <tr>
                    <th>Nombre</th>
                    <td><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th>Código Ref.</th>
                    <td><?= htmlspecialchars($producto['codigo_ref'] ?? '–', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th>Precio</th>
                    <td><?= number_format((float)$producto['precio'], 2, ',', '.') ?> €</td>
                </tr>
                <tr>
                    <th>Stock actual</th>
                    <td><?= (int)$producto['stock'] ?> uds.</td>
                </tr>
                <tr>
                    <th>Movimientos</th>
                    <td><?= (int)$totalMovimientos ?></td>
                </tr>
            </table>

            <?php if ($totalMovimientos > 0): ?>
            <div class="alert-banner alert-banner-warning">
                <svg class="alert-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                Este producto tiene <strong><?= (int)$totalMovimientos ?></strong> movimiento(s) registrado(s).
                El historial se conservará pero el producto quedará inactivo.
            </div>
            <?php endif; ?>

            <form method="POST" id="form-eliminar">
                <input type="hidden" name="id"      value="<?= (int)$producto['id'] ?>">
                <input type="hidden" name="confirm" value="1">
                <div class="confirm-actions">
                    <a href="productos.php" class="btn-ghost">Cancelar</a>
                    <button type="submit" class="btn-danger" id="btn-confirmar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                        </svg>
                        Confirmar eliminación
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="js/app.js"></script>
</body>
</html>
