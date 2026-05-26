<?php
/**
 * Gestión CRUD de proveedores del inventario.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';
require_once __DIR__ . '/includes/Proveedor.php';
require_once __DIR__ . '/includes/csrf.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$error          = '';
$editProv       = null;
$stockBajoCount = 0;
$provProductos  = [];
$provSelId      = filter_input(INPUT_GET, 'ver_productos', FILTER_VALIDATE_INT);

$proveedorModel = new Proveedor();
try { $stockBajoCount = count((new Producto())->filtrarStockBajo()); } catch (\Throwable $e) {}

// Leer mensajes flash
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $postAccion = $_POST['accion'] ?? '';

        $csrfAccion = $postAccion === 'desactivar' ? 'desactivar_proveedor' : 'guardar_proveedor';
        if (!validateCsrfToken($csrfAccion, $_POST['csrf_token'] ?? '')) {
            throw new AppException('Token CSRF inválido.', 403);
        }

        if ($postAccion === 'crear') {
            $datos = [
                'nombre'   => trim($_POST['nombre']   ?? ''),
                'contacto' => trim($_POST['contacto'] ?? '') ?: null,
                'email'    => trim($_POST['email']    ?? '') ?: null,
                'telefono' => trim($_POST['telefono'] ?? '') ?: null,
                'web'      => trim($_POST['web']      ?? '') ?: null,
                'notas'    => trim($_POST['notas']    ?? '') ?: null,
            ];
            $proveedorModel->crear($datos);
            $_SESSION['flash_success'] = 'Proveedor creado correctamente.';
        } elseif ($postAccion === 'editar') {
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) throw new AppException('ID inválido.', 400);
            $datos = [
                'nombre'   => trim($_POST['nombre']   ?? ''),
                'contacto' => trim($_POST['contacto'] ?? '') ?: null,
                'email'    => trim($_POST['email']    ?? '') ?: null,
                'telefono' => trim($_POST['telefono'] ?? '') ?: null,
                'web'      => trim($_POST['web']      ?? '') ?: null,
                'notas'    => trim($_POST['notas']    ?? '') ?: null,
            ];
            $proveedorModel->actualizar($id, $datos);
            $_SESSION['flash_success'] = 'Proveedor actualizado correctamente.';
        } elseif ($postAccion === 'desactivar') {
            $id = (int) ($_POST['id'] ?? 0);
            $proveedorModel->desactivar($id);
            $_SESSION['flash_success'] = 'Proveedor desactivado correctamente.';
        }
        header('Location: proveedores.php');
        exit;
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    } catch (\Throwable $e) {
        $flashError = 'Error inesperado: ' . $e->getMessage();
    }
}

$accion = $_GET['accion'] ?? 'listar';
if ($accion === 'editar') {
    try {
        $editId  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $editProv = $proveedorModel->obtener($editId);
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    }
}

try {
    $proveedores = $proveedorModel->listar(false);
} catch (\Throwable $e) {
    $proveedores = [];
    $flashError  = 'Error al cargar proveedores: ' . $e->getMessage();
}

if ($provSelId) {
    try {
        $provProductos = $proveedorModel->listarProductos($provSelId);
    } catch (\Throwable $e) {
        $provProductos = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores – es21plus</title>
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
                <span class="breadcrumb-item active">Proveedores</span>
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
                <h1 class="page-title">Proveedores</h1>
                <p class="page-subtitle">Gestiona los proveedores de tu inventario</p>
            </div>
        </div>

        <!-- Two-column layout -->
        <div class="two-col-layout">

            <!-- Left column: Create / Edit form -->
            <div class="two-col-left">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= $editProv ? 'Editar Proveedor' : 'Nuevo Proveedor' ?></h2>
                        <p class="card-subtitle"><?= $editProv ? 'Modifica los datos del proveedor' : 'Añade un nuevo proveedor al sistema' ?></p>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken('guardar_proveedor') ?>">
                            <input type="hidden" name="accion" value="<?= $editProv ? 'editar' : 'crear' ?>">
                            <?php if ($editProv): ?>
                                <input type="hidden" name="id" value="<?= (int)$editProv['id'] ?>">
                            <?php endif; ?>

                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="field-label" for="nombre">Nombre <span class="field-required">*</span></label>
                                <input class="field-input" type="text" id="nombre" name="nombre" required
                                       placeholder="Nombre del proveedor"
                                       value="<?= htmlspecialchars($editProv['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="field-label" for="contacto">Contacto</label>
                                <input class="field-input" type="text" id="contacto" name="contacto"
                                       placeholder="Persona de contacto"
                                       value="<?= htmlspecialchars($editProv['contacto'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="field-label" for="email">Email</label>
                                <input class="field-input" type="email" id="email" name="email"
                                       placeholder="email@proveedor.com"
                                       value="<?= htmlspecialchars($editProv['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="field-label" for="telefono">Teléfono</label>
                                <input class="field-input" type="text" id="telefono" name="telefono"
                                       placeholder="+34 600 000 000"
                                       value="<?= htmlspecialchars($editProv['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="field-label" for="web">Web</label>
                                <input class="field-input" type="url" id="web" name="web"
                                       placeholder="https://proveedor.com"
                                       value="<?= htmlspecialchars($editProv['web'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="form-field" style="margin-bottom:1.5rem;">
                                <label class="field-label" for="notas">Notas</label>
                                <textarea class="field-input field-textarea" id="notas" name="notas" rows="3"
                                          placeholder="Notas adicionales…"><?= htmlspecialchars($editProv['notas'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="card-footer" style="padding:0;border:0;margin-top:0;">
                                <?php if ($editProv): ?>
                                    <a href="proveedores.php" class="btn-ghost">Cancelar</a>
                                <?php endif; ?>
                                <button type="submit" class="btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <?= $editProv ? 'Actualizar' : 'Crear Proveedor' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right column: Proveedores table -->
            <div class="two-col-right">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Proveedores Registrados</h2>
                        <p class="card-subtitle"><?= count($proveedores) ?> proveedor(es) registrado(s)</p>
                    </div>
                    <div class="card-body" style="padding:0;">
                        <?php if (empty($proveedores)): ?>
                        <div class="td-empty" style="padding:2rem;text-align:center;">
                            No hay proveedores registrados. Crea el primero.
                        </div>
                        <?php else: ?>
                        <table class="data-table data-table-mini">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Contacto</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($proveedores as $prov): ?>
                                <tr>
                                    <td>
                                        <a href="proveedores.php?ver_productos=<?= (int)$prov['id'] ?>" style="font-weight:600;">
                                            <?= htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <?php if ((int)$prov['total_productos'] > 0): ?>
                                            <span class="badge-count" title="Productos vinculados"><?= (int)$prov['total_productos'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($prov['contacto'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if (!empty($prov['email'])): ?>
                                            <a href="mailto:<?= htmlspecialchars($prov['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($prov['email'], ENT_QUOTES, 'UTF-8') ?></a>
                                        <?php else: ?>
                                            <span style="color:#aaa;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($prov['telefono'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ((int)$prov['activo'] === 1): ?>
                                            <span class="badge badge-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="td-actions">
                                        <a href="proveedores.php?accion=editar&id=<?= (int)$prov['id'] ?>"
                                           class="action-btn action-btn-green" title="Editar proveedor">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>
                                        <?php if ((int)$prov['activo'] === 1): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken('desactivar_proveedor') ?>">
                                            <input type="hidden" name="accion" value="desactivar">
                                            <input type="hidden" name="id" value="<?= (int)$prov['id'] ?>">
                                            <button type="submit" class="action-btn action-btn-red" title="Desactivar proveedor"
                                                    onclick="return confirm('¿Desactivar el proveedor «<?= htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8') ?>»?')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
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

                <!-- Sección productos del proveedor seleccionado -->
                <?php if ($provSelId && !empty($provProductos)): ?>
                <div class="card" style="margin-top:1.5rem;">
                    <div class="card-header">
                        <h2 class="card-title">Productos de este proveedor</h2>
                        <p class="card-subtitle"><?= count($provProductos) ?> producto(s) vinculado(s)</p>
                    </div>
                    <div class="card-body" style="padding:0;">
                        <table class="data-table data-table-mini">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($provProductos as $prod): ?>
                                <tr>
                                    <td>
                                        <a href="ver_producto.php?id=<?= (int)$prod['id'] ?>">
                                            <?= htmlspecialchars($prod['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($prod['categoria_nombre'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= number_format((float)$prod['precio'], 2) ?> €</td>
                                    <td><?= (int)$prod['stock'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php elseif ($provSelId): ?>
                <div class="card" style="margin-top:1.5rem;">
                    <div class="card-body">
                        <p style="color:#aaa;text-align:center;padding:1rem 0;">
                            Este proveedor no tiene productos activos vinculados.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

<script src="js/app.js"></script>
</body>
</html>
