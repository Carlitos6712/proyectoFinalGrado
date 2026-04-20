<?php
/**
 * Formulario de edición de un producto existente.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @author   miguelrechefdez
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';
require_once __DIR__ . '/includes/Categoria.php';

$error   = '';
$success = '';

try {
    $productoModel  = new Producto();
    $categoriaModel = new Categoria();

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: productos.php');
        exit;
    }

    $producto   = $productoModel->obtener($id);
    $categorias = $categoriaModel->listar();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre      = trim($_POST['nombre']      ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio      = (float)  ($_POST['precio']      ?? 0);
        $categoriaId = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT) ?: null;
        $stockMinimo = (int)    ($_POST['stock_minimo'] ?? 5);
        $codigoRef   = trim($_POST['codigo_ref'] ?? '') ?: null;

        if ($nombre === '') {
            throw new AppException('El nombre del producto es obligatorio.', 400);
        }
        if ($precio < 0) {
            throw new AppException('El precio no puede ser negativo.', 400);
        }

        $productoModel->actualizar($id, $nombre, $descripcion, $precio, $categoriaId, $stockMinimo, $codigoRef);
        $_SESSION['flash_success'] = 'Producto actualizado correctamente.';
        header('Location: productos.php');
        exit;
    }
} catch (AppException $e) {
    $error = $e->getMessage();
} catch (\Throwable $e) {
    $error = 'Error inesperado: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto – es21plus</title>
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
                <span class="breadcrumb-item active">Editar Producto</span>
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
                <h1 class="page-title">Editar Producto</h1>
                <p class="page-subtitle">
                    <?php if (isset($producto)): ?>
                        Modificando: <strong><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php else: ?>
                        Modifica los datos del producto seleccionado
                    <?php endif; ?>
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

        <?php if ($error): ?>
        <div class="alert-banner alert-banner-error">
            <svg class="alert-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <?php if (isset($producto)): ?>
        <div class="card card-form">
            <div class="card-header">
                <h2 class="card-title">Datos del Producto</h2>
                <p class="card-subtitle">Los campos marcados con <span class="field-required">*</span> son obligatorios</p>
            </div>
            <div class="card-body">
                <form method="POST" class="form-grid-wrapper">
                    <div class="form-grid">

                        <div class="form-field form-field-full">
                            <label class="field-label" for="nombre">Nombre <span class="field-required">*</span></label>
                            <input class="field-input" type="text" id="nombre" name="nombre" required
                                   placeholder="Nombre del producto"
                                   value="<?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-field form-field-full">
                            <label class="field-label" for="descripcion">Descripción</label>
                            <textarea class="field-input field-textarea" id="descripcion" name="descripcion" rows="3"
                                      placeholder="Descripción opcional del producto…"><?= htmlspecialchars($producto['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="precio">Precio (€) <span class="field-required">*</span></label>
                            <input class="field-input" type="number" id="precio" name="precio" step="0.01" min="0" required
                                   placeholder="0.00"
                                   value="<?= htmlspecialchars((string)$producto['precio'], ENT_QUOTES, 'UTF-8') ?>">
                            <span class="field-hint">Precio de venta al público</span>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="stock_minimo">Stock Mínimo</label>
                            <input class="field-input" type="number" id="stock_minimo" name="stock_minimo" min="0"
                                   placeholder="5"
                                   value="<?= (int)($producto['stock_minimo'] ?? 5) ?>">
                            <span class="field-hint">Alerta cuando el stock baje de este valor</span>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="codigo_ref">Código de Referencia</label>
                            <input class="field-input" type="text" id="codigo_ref" name="codigo_ref"
                                   placeholder="Ej. REF-001"
                                   value="<?= htmlspecialchars($producto['codigo_ref'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <span class="field-hint">Referencia interna o del fabricante</span>
                        </div>

                        <div class="form-field form-field-full">
                            <label class="field-label" for="categoria_id">Categoría</label>
                            <select class="field-input field-select" id="categoria_id" name="categoria_id">
                                <option value="">Sin categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"
                                        <?= (int)$cat['id'] === (int)$producto['categoria_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="card-footer">
                        <a href="productos.php" class="btn-ghost">Cancelar</a>
                        <button type="submit" class="btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="js/app.js"></script>
</body>
</html>
