<?php
/**
 * Formulario para crear un nuevo pedido de reposición de inventario.
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
require_once __DIR__ . '/includes/Pedido.php';
require_once __DIR__ . '/includes/csrf.php';

// Solo admin puede crear pedidos
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pedidoModel   = new Pedido();
$proveedorModel = new Proveedor();
$productoModel  = new Producto();

$proveedores = [];
$productos   = [];
$error       = '';
try {
    $proveedores = $proveedorModel->listar(true);
    $productos   = $productoModel->listar();
} catch (\Throwable $e) {
    $error = 'Error al cargar datos: ' . $e->getMessage();
}

// Pre-rellenar desde query string (venido del botón de stock bajo)
$preProductoId  = filter_input(INPUT_GET, 'producto_id', FILTER_VALIDATE_INT) ?: null;
$preCantidad    = filter_input(INPUT_GET, 'cantidad',    FILTER_VALIDATE_INT) ?: 1;
// Soporte múltiple: ?productos[]=X&cantidades[]=Y
$preProductosArr  = array_filter(array_map('intval', (array)($_GET['productos']  ?? [])));
$preCantidadesArr = array_filter(array_map('intval', (array)($_GET['cantidades'] ?? [])));

// Construir líneas iniciales
$lineasIniciales = [];
if ($preProductoId) {
    $lineasIniciales[] = ['producto_id' => $preProductoId, 'cantidad' => max(1, $preCantidad)];
}
foreach ($preProductosArr as $idx => $pid) {
    $lineasIniciales[] = ['producto_id' => $pid, 'cantidad' => max(1, (int)($preCantidadesArr[$idx] ?? 1))];
}
if (empty($lineasIniciales)) {
    $lineasIniciales[] = ['producto_id' => 0, 'cantidad' => 1];
}

// ── Manejar POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCsrfToken('crear_pedido', $_POST['csrf_token'] ?? '')) {
            throw new AppException('Token CSRF inválido.', 403);
        }

        $datos = [
            'proveedor_id' => filter_input(INPUT_POST, 'proveedor_id', FILTER_VALIDATE_INT) ?: null,
            'notas'        => trim($_POST['notas'] ?? '') ?: null,
        ];

        $productosPost  = (array) ($_POST['producto_id']  ?? []);
        $cantidadesPost = (array) ($_POST['cantidad']     ?? []);
        $preciosPost    = (array) ($_POST['precio_unit']  ?? []);

        // Validar que hay al menos una línea con producto y cantidad válidos
        $lineasValidas = [];
        foreach ($productosPost as $i => $pid) {
            $pid  = (int) $pid;
            $cant = (int) ($cantidadesPost[$i] ?? 0);
            if ($pid > 0 && $cant > 0) {
                $lineasValidas[] = [
                    'producto_id' => $pid,
                    'cantidad'    => $cant,
                    'precio_unit' => isset($preciosPost[$i]) && $preciosPost[$i] !== '' ? (float) $preciosPost[$i] : null,
                ];
            }
        }

        if (empty($lineasValidas)) {
            throw new AppException('Debes añadir al menos una línea con producto y cantidad válidos.', 400);
        }

        $pedidoId = $pedidoModel->crear($datos);
        foreach ($lineasValidas as $linea) {
            $pedidoModel->añadirLinea(
                $pedidoId,
                $linea['producto_id'],
                $linea['cantidad'],
                $linea['precio_unit']
            );
        }

        $_SESSION['flash_success'] = "Pedido #{$pedidoId} creado correctamente en estado borrador.";
        header('Location: pedidos.php');
        exit;
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    } catch (\Throwable $e) {
        $flashError = 'Error inesperado: ' . $e->getMessage();
    }
}

$stockBajoCount = 0;
try { $stockBajoCount = count($productoModel->filtrarStockBajo()); } catch (\Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Pedido – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .lineas-tabla { width:100%; border-collapse:collapse; margin-bottom:1rem; }
        .lineas-tabla th, .lineas-tabla td { padding:.5rem .75rem; border:1px solid #e2e8f0; text-align:left; }
        .lineas-tabla thead th { background:#f8fafc; font-weight:600; }
        .btn-add-linea { margin-top:.5rem; }
        .btn-rm { background:none; border:1px solid #ef4444; color:#ef4444; border-radius:.3em; cursor:pointer; padding:.25em .5em; }
        .btn-rm:hover { background:#fee2e2; }
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
            <a href="index.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="productos.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span>
                <span class="nav-label">Productos</span>
            </a>
            <a href="proveedores.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></span>
                <span class="nav-label">Proveedores</span>
            </a>
            <a href="pedidos.php" class="nav-item active">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
                <span class="nav-label">Pedidos</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Operaciones</span>
            <a href="movimientos.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></span>
                <span class="nav-label">Movimientos</span>
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
                <a href="pedidos.php" class="breadcrumb-item">Pedidos</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active">Nuevo Pedido</span>
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

        <?php if (!empty($flashError) || !empty($error)): ?>
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

        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Nuevo Pedido de Reposición</h1>
                <p class="page-subtitle">Crea un pedido en estado borrador. Podrás enviarlo cuando esté listo.</p>
            </div>
            <div class="page-actions">
                <a href="pedidos.php" class="btn-ghost">← Volver a Pedidos</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" id="formPedido">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken('crear_pedido') ?>">

                    <!-- Proveedor -->
                    <div class="form-field" style="margin-bottom:1rem;">
                        <label class="field-label" for="proveedor_id">Proveedor <span style="color:#aaa;">(opcional)</span></label>
                        <select class="field-input" id="proveedor_id" name="proveedor_id">
                            <option value="">— Sin proveedor —</option>
                            <?php foreach ($proveedores as $prov): ?>
                            <option value="<?= (int)$prov['id'] ?>"
                                <?= isset($_POST['proveedor_id']) && (int)$_POST['proveedor_id'] === (int)$prov['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Notas -->
                    <div class="form-field" style="margin-bottom:1.5rem;">
                        <label class="field-label" for="notas">Notas</label>
                        <textarea class="field-input field-textarea" id="notas" name="notas" rows="3"
                                  placeholder="Observaciones del pedido…"><?= htmlspecialchars($_POST['notas'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <!-- Líneas del pedido -->
                    <h3 style="margin-bottom:.75rem;font-size:1rem;font-weight:600;">Líneas del pedido <span style="color:#ef4444;">*</span></h3>

                    <table class="lineas-tabla" id="tablaDinamica">
                        <thead>
                            <tr>
                                <th style="width:40%;">Producto</th>
                                <th style="width:15%;">Cantidad</th>
                                <th style="width:20%;">Precio unitario (€)</th>
                                <th style="width:10%;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyLineas">
                        <?php foreach ($lineasIniciales as $li): ?>
                            <tr class="linea-row">
                                <td>
                                    <select name="producto_id[]" class="field-input" required>
                                        <option value="">— Selecciona producto —</option>
                                        <?php foreach ($productos as $prod): ?>
                                        <option value="<?= (int)$prod['id'] ?>"
                                            <?= (int)$li['producto_id'] === (int)$prod['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prod['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                            (stock: <?= (int)$prod['stock'] ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="cantidad[]" class="field-input"
                                           value="<?= (int)$li['cantidad'] ?>" min="1" required style="width:100%;">
                                </td>
                                <td>
                                    <input type="number" name="precio_unit[]" class="field-input"
                                           step="0.01" min="0" placeholder="0.00" style="width:100%;">
                                </td>
                                <td>
                                    <button type="button" class="btn-rm" onclick="eliminarFila(this)">✕</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <button type="button" class="btn-ghost btn-add-linea" id="btnAddLinea">
                        + Añadir línea
                    </button>

                    <div style="margin-top:1.5rem;display:flex;gap:.75rem;">
                        <a href="pedidos.php" class="btn-ghost">Cancelar</a>
                        <button type="submit" class="btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Crear Pedido
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>
</div>

<script>
(function () {
    /** Opciones de productos para la fila dinámica */
    var productosOptions = <?php
        $opts = [];
        foreach ($productos as $prod) {
            $opts[] = [
                'id'     => (int)$prod['id'],
                'nombre' => $prod['nombre'],
                'stock'  => (int)$prod['stock'],
            ];
        }
        echo json_encode($opts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    ?>;

    /**
     * Genera el HTML de una nueva fila de línea.
     * @returns {HTMLTableRowElement}
     */
    function crearFila() {
        var tr = document.createElement('tr');
        tr.className = 'linea-row';

        var selectHtml = '<select name="producto_id[]" class="field-input" required>'
            + '<option value="">— Selecciona producto —</option>';
        productosOptions.forEach(function (p) {
            selectHtml += '<option value="' + p.id + '">'
                + p.nombre.replace(/&/g,'&amp;').replace(/</g,'&lt;')
                + ' (stock: ' + p.stock + ')</option>';
        });
        selectHtml += '</select>';

        tr.innerHTML =
            '<td>' + selectHtml + '</td>'
            + '<td><input type="number" name="cantidad[]" class="field-input" value="1" min="1" required style="width:100%;"></td>'
            + '<td><input type="number" name="precio_unit[]" class="field-input" step="0.01" min="0" placeholder="0.00" style="width:100%;"></td>'
            + '<td><button type="button" class="btn-rm" onclick="eliminarFila(this)">&#x2715;</button></td>';
        return tr;
    }

    document.getElementById('btnAddLinea').addEventListener('click', function () {
        document.getElementById('tbodyLineas').appendChild(crearFila());
    });
}());

/**
 * Elimina la fila de línea del DOM. Mantiene siempre al menos una fila.
 * @param {HTMLElement} btn
 */
function eliminarFila(btn) {
    var tbody = document.getElementById('tbodyLineas');
    var filas = tbody.querySelectorAll('tr.linea-row');
    if (filas.length <= 1) {
        alert('El pedido debe tener al menos una línea.');
        return;
    }
    btn.closest('tr').remove();
}
</script>
<script src="js/app.js"></script>
</body>
</html>
