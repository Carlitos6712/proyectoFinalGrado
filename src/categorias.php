<?php
/**
 * Gestión CRUD de categorías del inventario.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @author   miguelrechefdez
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Categoria.php';

$accion  = $_GET['accion'] ?? 'listar';
$error   = '';
$editCat = null;

$categoriaModel = new Categoria();

// Leer mensajes flash
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $postAccion = $_POST['accion'] ?? '';

        if ($postAccion === 'crear') {
            $nombre      = trim($_POST['nombre']      ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            if ($nombre === '') throw new AppException('El nombre es obligatorio.', 400);
            $categoriaModel->crear($nombre, $descripcion);
            $_SESSION['flash_success'] = 'Categoría creada correctamente.';
        } elseif ($postAccion === 'actualizar') {
            $id          = (int) ($_POST['id'] ?? 0);
            $nombre      = trim($_POST['nombre']      ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            if (!$id || $nombre === '') throw new AppException('Datos inválidos.', 400);
            $categoriaModel->actualizar($id, $nombre, $descripcion);
            $_SESSION['flash_success'] = 'Categoría actualizada correctamente.';
        } elseif ($postAccion === 'eliminar') {
            $id = (int) ($_POST['id'] ?? 0);
            $categoriaModel->eliminar($id);
            $_SESSION['flash_success'] = 'Categoría eliminada correctamente.';
        }
        header('Location: categorias.php');
        exit;
    } catch (AppException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Error inesperado: ' . $e->getMessage();
    }
}

if ($accion === 'editar') {
    try {
        $id      = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $editCat = $categoriaModel->obtenerPorId($id);
    } catch (AppException $e) {
        $error = $e->getMessage();
    }
}

try {
    $categorias = $categoriaModel->listar();
} catch (\Throwable $e) {
    $categorias = [];
    $error = 'Error al cargar categorías: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías – es21plus</title>
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
                <span class="breadcrumb-item active">Categorías</span>
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

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Categorías</h1>
                <p class="page-subtitle">Organiza tus productos en categorías</p>
            </div>
        </div>

        <!-- Two-column layout -->
        <div class="two-col-layout">

            <!-- Left column: Create / Edit form -->
            <div class="two-col-left">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= $editCat ? 'Editar Categoría' : 'Nueva Categoría' ?></h2>
                        <p class="card-subtitle"><?= $editCat ? 'Modifica los datos de la categoría' : 'Añade una nueva categoría al sistema' ?></p>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="<?= $editCat ? 'actualizar' : 'crear' ?>">
                            <?php if ($editCat): ?>
                                <input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>">
                            <?php endif; ?>

                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="field-label" for="nombre">Nombre <span class="field-required">*</span></label>
                                <input class="field-input" type="text" id="nombre" name="nombre" required
                                       placeholder="Nombre de la categoría"
                                       value="<?= htmlspecialchars($editCat['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-field" style="margin-bottom:1.5rem;">
                                <label class="field-label" for="descripcion">Descripción</label>
                                <textarea class="field-input field-textarea" id="descripcion" name="descripcion" rows="3"
                                          placeholder="Descripción opcional…"><?= htmlspecialchars($editCat['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="card-footer" style="padding:0;border:0;margin-top:0;">
                                <?php if ($editCat): ?>
                                    <a href="categorias.php" class="btn-ghost">Cancelar</a>
                                <?php endif; ?>
                                <button type="submit" class="btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <?= $editCat ? 'Actualizar' : 'Crear Categoría' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right column: Categories table -->
            <div class="two-col-right">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Categorías Existentes</h2>
                        <p class="card-subtitle"><?= count($categorias) ?> categoría(s) registrada(s)</p>
                    </div>
                    <div class="card-body" style="padding:0;">
                        <?php if (empty($categorias)): ?>
                        <div class="td-empty" style="padding:2rem;text-align:center;">
                            No hay categorías registradas. Crea la primera.
                        </div>
                        <?php else: ?>
                        <table class="data-table data-table-mini">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Productos</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <td>
                                        <span class="cat-name"><?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-count"><?= (int)$cat['total_productos'] ?></span>
                                    </td>
                                    <td class="td-actions">
                                        <a href="categorias.php?accion=editar&id=<?= (int)$cat['id'] ?>"
                                           class="action-btn action-btn-green" title="Editar categoría">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>
                                        <?php if ((int)$cat['total_productos'] === 0): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                            <button type="submit" class="action-btn action-btn-red" title="Eliminar categoría"
                                                    data-confirm-cat="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                                </svg>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

<script src="js/app.js"></script>
</body>
</html>
