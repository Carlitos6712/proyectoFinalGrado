<?php
/**
 * Gestión CRUD de marcas del inventario.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';
require_once __DIR__ . '/includes/Marca.php';

$accion         = $_GET['accion'] ?? 'listar';
$error          = '';
$editMarca      = null;
$stockBajoCount = 0;

$marcaModel = new Marca();
try { $stockBajoCount = count((new Producto())->filtrarStockBajo()); } catch (\Throwable $e) {}

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
            $marcaModel->crear($nombre, $descripcion);
            $_SESSION['flash_success'] = 'Marca creada correctamente.';
        } elseif ($postAccion === 'actualizar') {
            $id          = (int) ($_POST['id'] ?? 0);
            $nombre      = trim($_POST['nombre']      ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            if (!$id || $nombre === '') throw new AppException('Datos inválidos.', 400);
            $marcaModel->actualizar($id, $nombre, $descripcion);
            $_SESSION['flash_success'] = 'Marca actualizada correctamente.';
        } elseif ($postAccion === 'eliminar') {
            $id = (int) ($_POST['id'] ?? 0);
            $marcaModel->eliminar($id);
            $_SESSION['flash_success'] = 'Marca eliminada correctamente.';
        }
        header('Location: marcas.php');
        exit;
    } catch (AppException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Error inesperado: ' . $e->getMessage();
    }
}

if ($accion === 'editar') {
    try {
        $id        = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $editMarca = $marcaModel->obtenerPorId($id);
    } catch (AppException $e) {
        $error = $e->getMessage();
    }
}

try {
    $marcas = $marcaModel->listar();
} catch (\Throwable $e) {
    $marcas = [];
    $error  = 'Error al cargar marcas: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcas – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
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
                <span class="breadcrumb-item active">Marcas</span>
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
                <h1 class="page-title">Marcas</h1>
                <p class="page-subtitle">Gestiona las marcas y fabricantes del inventario</p>
            </div>
        </div>

        <!-- Two-column layout -->
        <div class="two-col-layout">

            <!-- Left column: Create / Edit form -->
            <div class="two-col-left">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= $editMarca ? 'Editar Marca' : 'Nueva Marca' ?></h2>
                        <p class="card-subtitle"><?= $editMarca ? 'Modifica los datos de la marca' : 'Añade una nueva marca al sistema' ?></p>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="<?= $editMarca ? 'actualizar' : 'crear' ?>">
                            <?php if ($editMarca): ?>
                                <input type="hidden" name="id" value="<?= (int)$editMarca['id'] ?>">
                            <?php endif; ?>

                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="field-label" for="nombre">Nombre <span class="field-required">*</span></label>
                                <input class="field-input" type="text" id="nombre" name="nombre" required
                                       placeholder="Nombre de la marca"
                                       value="<?= htmlspecialchars($editMarca['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-field" style="margin-bottom:1.5rem;">
                                <label class="field-label" for="descripcion">Descripción</label>
                                <textarea class="field-input field-textarea" id="descripcion" name="descripcion" rows="3"
                                          placeholder="Descripción opcional…"><?= htmlspecialchars($editMarca['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="card-footer" style="padding:0;border:0;margin-top:0;">
                                <?php if ($editMarca): ?>
                                    <a href="marcas.php" class="btn-ghost">Cancelar</a>
                                <?php endif; ?>
                                <button type="submit" class="btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <?= $editMarca ? 'Actualizar' : 'Crear Marca' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right column: Brands table -->
            <div class="two-col-right">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Marcas Existentes</h2>
                        <p class="card-subtitle"><?= count($marcas) ?> marca(s) registrada(s)</p>
                    </div>
                    <div class="card-body" style="padding:0;">
                        <?php if (empty($marcas)): ?>
                        <div class="td-empty" style="padding:2rem;text-align:center;">
                            No hay marcas registradas. Crea la primera.
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
                            <?php foreach ($marcas as $marca): ?>
                                <tr>
                                    <td>
                                        <span class="cat-name"><?= htmlspecialchars($marca['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-count"><?= (int)$marca['total_productos'] ?></span>
                                    </td>
                                    <td class="td-actions">
                                        <a href="marcas.php?accion=editar&id=<?= (int)$marca['id'] ?>"
                                           class="action-btn action-btn-green" title="Editar marca">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>
                                        <?php if ((int)$marca['total_productos'] === 0): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= (int)$marca['id'] ?>">
                                            <button type="submit" class="action-btn action-btn-red" title="Eliminar marca"
                                                    onclick="return confirm('¿Eliminar la marca «<?= htmlspecialchars($marca['nombre'], ENT_QUOTES, 'UTF-8') ?>»? Esta acción no se puede deshacer.')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                                </svg>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <button class="action-btn" disabled
                                                title="No se puede eliminar: tiene <?= (int)$marca['total_productos'] ?> producto(s) activo(s)"
                                                style="opacity:.35;cursor:not-allowed;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                            </svg>
                                        </button>
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
