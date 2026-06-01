<?php
/**
 * Gestión CRUD de modelos de moto para compatibilidades de productos.
 * Solo accesible para administradores.
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
require_once __DIR__ . '/includes/ModeloMoto.php';

// Solo admins pueden acceder a esta página
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$error          = '';
$stockBajoCount = 0;

$modeloMotoModel = new ModeloMoto();
try { $stockBajoCount = count((new Producto())->filtrarStockBajo()); } catch (\Throwable $e) {}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $postAccion = $_POST['accion'] ?? '';

        if ($postAccion === 'crear') {
            $marca     = trim($_POST['marca']      ?? '');
            $modelo    = trim($_POST['modelo']     ?? '');
            $anioDes   = (int) ($_POST['anio_desde'] ?? 0);
            $anioHasta = ($_POST['anio_hasta'] ?? '') !== '' ? (int)$_POST['anio_hasta'] : null;

            if ($marca === '') throw new AppException('La marca es obligatoria.', 400);
            if ($modelo === '') throw new AppException('El modelo es obligatorio.', 400);
            if ($anioDes === 0) throw new AppException('El año de inicio es obligatorio.', 400);

            $modeloMotoModel->crear($marca, $modelo, $anioDes, $anioHasta);
            $_SESSION['flash_success'] = 'Modelo de moto creado correctamente.';
        } elseif ($postAccion === 'eliminar') {
            $id = (int) ($_POST['id'] ?? 0);
            $modeloMotoModel->eliminar($id);
            $_SESSION['flash_success'] = 'Modelo de moto eliminado correctamente.';
        }
        header('Location: modelos_moto.php');
        exit;
    } catch (AppException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Error inesperado: ' . $e->getMessage();
    }
}

try {
    $modelos = $modeloMotoModel->listar();
} catch (\Throwable $e) {
    $modelos = [];
    $error   = 'Error al cargar modelos de moto: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modelos de Moto – es21plus</title>
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
                <span class="breadcrumb-item active">Modelos de Moto</span>
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
                <h1 class="page-title">Modelos de Moto</h1>
                <p class="page-subtitle">Gestiona los modelos de moto para compatibilidades de productos</p>
            </div>
        </div>

        <!-- Two-column layout -->
        <div class="two-col-layout">

            <!-- Left column: Create form -->
            <div class="two-col-left">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Nuevo Modelo de Moto</h2>
                        <p class="card-subtitle">Añade un nuevo modelo de moto al sistema</p>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="crear">

                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="field-label" for="marca">Marca <span class="field-required">*</span></label>
                                <input class="field-input" type="text" id="marca" name="marca" required
                                       placeholder="Ej. Honda, Yamaha, Kawasaki">
                            </div>

                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="field-label" for="modelo">Modelo <span class="field-required">*</span></label>
                                <input class="field-input" type="text" id="modelo" name="modelo" required
                                       placeholder="Ej. CB500F, MT-07, Z650">
                            </div>

                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="field-label" for="anio_desde">Año desde <span class="field-required">*</span></label>
                                <input class="field-input" type="number" id="anio_desde" name="anio_desde" required
                                       min="1900" max="2099" placeholder="Ej. 2018">
                            </div>

                            <div class="form-field" style="margin-bottom:1.5rem;">
                                <label class="field-label" for="anio_hasta">Año hasta</label>
                                <input class="field-input" type="number" id="anio_hasta" name="anio_hasta"
                                       min="1900" max="2099" placeholder="Dejar vacío si sigue en producción">
                                <span class="field-hint">Dejar en blanco si el modelo sigue en producción.</span>
                            </div>

                            <div class="card-footer" style="padding:0;border:0;margin-top:0;">
                                <button type="submit" class="btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Crear Modelo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right column: Models table -->
            <div class="two-col-right">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Modelos Registrados</h2>
                        <p class="card-subtitle"><?= count($modelos) ?> modelo(s) registrado(s)</p>
                    </div>
                    <div class="card-body" style="padding:0;">
                        <?php if (empty($modelos)): ?>
                        <div class="td-empty" style="padding:2rem;text-align:center;">
                            No hay modelos de moto registrados. Crea el primero.
                        </div>
                        <?php else: ?>
                        <table class="data-table data-table-mini">
                            <thead>
                                <tr>
                                    <th>Marca</th>
                                    <th>Modelo</th>
                                    <th>Año desde</th>
                                    <th>Año hasta</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($modelos as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['marca'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($m['modelo'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int)$m['anio_desde'] ?></td>
                                    <td><?= $m['anio_hasta'] ? (int)$m['anio_hasta'] : '<em style="color:#6b7280;">Actual</em>' ?></td>
                                    <td class="td-actions">
                                        <button type="button"
                                                class="action-btn action-btn-red"
                                                title="Eliminar modelo"
                                                onclick="confirmarEliminar(<?= (int)$m['id'] ?>)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                            </svg>
                                        </button>
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

<!-- Hidden form para eliminar via POST -->
<form id="formEliminar" method="POST" style="display:none;">
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id" id="eliminarId" value="">
</form>

<script>
/**
 * Confirma y ejecuta la eliminación de un modelo de moto.
 * Muestra el error 409 si el modelo tiene compatibilidades.
 *
 * @param {number} id - ID del modelo a eliminar.
 */
function confirmarEliminar(id) {
    if (!confirm('¿Seguro que quieres eliminar este modelo de moto?')) return;

    fetch('api/modelos_moto.php?id=' + id, { method: 'DELETE' })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.success) {
                location.reload();
            } else {
                alert('Error: ' + json.message);
            }
        })
        .catch(function() {
            // Fallback al formulario POST si la API no está disponible
            document.getElementById('eliminarId').value = id;
            document.getElementById('formEliminar').submit();
        });
}
</script>
<script src="js/app.js"></script>
</body>
</html>
