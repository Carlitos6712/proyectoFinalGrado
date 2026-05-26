<?php
/**
 * Listado y gestión de pedidos de reposición de inventario.
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

$pedidoModel    = new Pedido();
$stockBajoCount = 0;
try { $stockBajoCount = count((new Producto())->filtrarStockBajo()); } catch (\Throwable $e) {}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Manejar acciones POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if (!validateCsrfToken('cambiar_estado_pedido', $_POST['csrf_token'] ?? '')) {
            throw new AppException('Token CSRF inválido.', 403);
        }

        if ($accion === 'cambiar_estado') {
            $pedidoId   = (int) ($_POST['pedido_id'] ?? 0);
            $nuevoEstado = trim($_POST['nuevo_estado'] ?? '');
            if (!$pedidoId || !$nuevoEstado) {
                throw new AppException('Datos inválidos.', 400);
            }
            if (!isAdmin()) {
                throw new AppException('No tienes permisos para esta acción.', 403);
            }
            $pedidoModel->cambiarEstado($pedidoId, $nuevoEstado);
            $mensajes = [
                'enviado'  => 'Pedido marcado como enviado.',
                'recibido' => 'Pedido recibido. Se han generado los movimientos de entrada.',
                'cancelado'=> 'Pedido cancelado.',
            ];
            $_SESSION['flash_success'] = $mensajes[$nuevoEstado] ?? 'Estado actualizado.';
        }
    } catch (AppException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    } catch (\Throwable $e) {
        $_SESSION['flash_error'] = 'Error inesperado: ' . $e->getMessage();
    }
    header('Location: pedidos.php' . (isset($_GET['estado']) ? '?estado=' . urlencode($_GET['estado']) : ''));
    exit;
}

// ── Exportar PDF ───────────────────────────────────────────────────────────────
if (isset($_GET['pdf'])) {
    $pdfId = (int) $_GET['pdf'];
    try {
        $pedidoModel->exportarPdf($pdfId);
    } catch (\Throwable $e) {
        $flashError = 'Error al generar PDF: ' . $e->getMessage();
    }
}

// ── Cargar listado ─────────────────────────────────────────────────────────────
$filtroEstado = $_GET['estado'] ?? '';
$pedidos      = [];
$contadores   = ['todos' => 0, 'borrador' => 0, 'enviado' => 0, 'recibido' => 0, 'cancelado' => 0];
try {
    $pedidos = $pedidoModel->listar($filtroEstado ?: null);
    // Contadores por estado para las tabs
    $pdo  = Database::getInstance();
    $rows = $pdo->query("SELECT estado, COUNT(*) AS n FROM pedidos GROUP BY estado")->fetchAll();
    foreach ($rows as $r) {
        $contadores[$r['estado']] = (int)$r['n'];
        $contadores['todos'] += (int)$r['n'];
    }
} catch (\Throwable $e) {
    $flashError = 'Error al cargar pedidos: ' . $e->getMessage();
}

$esAdmin = isAdmin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .badge-estado { display:inline-block; padding:.25em .6em; border-radius:.4em; font-size:.8em; font-weight:600; }
        .badge-borrador  { background:#e2e8f0; color:#475569; }
        .badge-enviado   { background:#dbeafe; color:#1d4ed8; }
        .badge-recibido  { background:#dcfce7; color:#15803d; }
        .badge-cancelado { background:#fee2e2; color:#b91c1c; }
        .filter-links a { margin-right:.5rem; text-decoration:none; color:var(--color-primary,#2563eb); }
        .filter-links a.active { font-weight:700; text-decoration:underline; }
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
                <span class="breadcrumb-item active">Pedidos</span>
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

        <?php if (!empty($flashSuccess)): ?>
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

        <?php if (!empty($flashError)): ?>
        <div class="toast toast-error" data-autodismiss="6000">
            <span class="toast-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
            <div class="toast-content">
                <span class="toast-title">Error</span>
                <span class="toast-message"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button class="toast-close" aria-label="Cerrar">×</button>
            <div class="toast-progress"></div>
        </div>
        <?php endif; ?>

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Pedidos de Reposición</h1>
                <p class="page-subtitle">Gestiona los pedidos de reposición de inventario</p>
            </div>
            <div class="page-actions">
                <?php if ($esAdmin): ?>
                <a href="nuevo_pedido.php" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo Pedido
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtros por estado -->
        <div class="pedido-tabs" role="tablist" aria-label="Filtrar por estado">
            <?php
            $tabs = [
                ''          => ['label' => 'Todos',     'color' => '#64748b', 'bg' => '#f1f5f9'],
                'borrador'  => ['label' => 'Borrador',  'color' => '#475569', 'bg' => '#e2e8f0'],
                'enviado'   => ['label' => 'Enviado',   'color' => '#1d4ed8', 'bg' => '#dbeafe'],
                'recibido'  => ['label' => 'Recibido',  'color' => '#15803d', 'bg' => '#dcfce7'],
                'cancelado' => ['label' => 'Cancelado', 'color' => '#b91c1c', 'bg' => '#fee2e2'],
            ];
            foreach ($tabs as $estado => $cfg):
                $activo  = $filtroEstado === $estado;
                $href    = $estado ? "pedidos.php?estado={$estado}" : "pedidos.php";
                $count   = $estado === '' ? $contadores['todos'] : ($contadores[$estado] ?? 0);
                $style   = $activo
                    ? "background:{$cfg['bg']};color:{$cfg['color']};border-color:{$cfg['color']};"
                    : "background:#fff;color:#64748b;border-color:#e2e8f0;";
            ?>
            <a href="<?= $href ?>"
               role="tab"
               aria-selected="<?= $activo ? 'true' : 'false' ?>"
               style="<?= $style ?>display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border:1.5px solid;border-radius:8px;text-decoration:none;font-size:.875rem;font-weight:<?= $activo ? '700' : '500' ?>;white-space:nowrap;transition:all .15s;">
                <?= htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8') ?>
                <span style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;padding:0 6px;border-radius:999px;font-size:.75rem;font-weight:700;background:<?= $activo ? $cfg['color'] : '#e2e8f0' ?>;color:<?= $activo ? '#fff' : '#475569' ?>;">
                    <?= $count ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Tabla de pedidos -->
        <div class="card">
            <div class="card-body" style="padding:0;">
                <?php if (empty($pedidos)): ?>
                <div class="td-empty" style="padding:2rem;text-align:center;">
                    No hay pedidos<?= $filtroEstado ? " en estado '{$filtroEstado}'" : '' ?>.
                    <?php if ($esAdmin): ?>
                    <a href="nuevo_pedido.php">Crea el primero</a>.
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>Líneas</th>
                            <th>Creado</th>
                            <th>Enviado</th>
                            <th>Recibido</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pedidos as $ped): ?>
                        <tr>
                            <td><strong>#<?= (int)$ped['id'] ?></strong></td>
                            <td><?= htmlspecialchars($ped['proveedor_nombre'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php
                                $estadoClass = match($ped['estado']) {
                                    'borrador'  => 'badge-borrador',
                                    'enviado'   => 'badge-enviado',
                                    'recibido'  => 'badge-recibido',
                                    'cancelado' => 'badge-cancelado',
                                    default     => 'badge-borrador',
                                };
                                ?>
                                <span class="badge-estado <?= $estadoClass ?>"><?= htmlspecialchars(ucfirst($ped['estado']), ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td><?= (int)$ped['total_lineas'] ?></td>
                            <td><?= htmlspecialchars(substr($ped['created_at'] ?? '', 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $ped['enviado_at']   ? htmlspecialchars(substr($ped['enviado_at'],  0, 10), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                            <td><?= $ped['recibido_at']  ? htmlspecialchars(substr($ped['recibido_at'], 0, 10), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                            <td class="td-actions" style="white-space:nowrap;">
                                <?php if ($esAdmin && $ped['estado'] === 'borrador'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token"   value="<?= generateCsrfToken('cambiar_estado_pedido') ?>">
                                    <input type="hidden" name="accion"       value="cambiar_estado">
                                    <input type="hidden" name="pedido_id"    value="<?= (int)$ped['id'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="enviado">
                                    <button type="submit" class="btn btn-sm btn-primary" title="Marcar como enviado"
                                            onclick="return confirm('¿Marcar pedido #<?= (int)$ped['id'] ?> como enviado?')">
                                        Enviado
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token"   value="<?= generateCsrfToken('cambiar_estado_pedido') ?>">
                                    <input type="hidden" name="accion"       value="cambiar_estado">
                                    <input type="hidden" name="pedido_id"    value="<?= (int)$ped['id'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="cancelado">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Cancelar pedido"
                                            onclick="return confirm('¿Cancelar el pedido #<?= (int)$ped['id'] ?>?')">
                                        Cancelar
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($esAdmin && $ped['estado'] === 'enviado'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token"   value="<?= generateCsrfToken('cambiar_estado_pedido') ?>">
                                    <input type="hidden" name="accion"       value="cambiar_estado">
                                    <input type="hidden" name="pedido_id"    value="<?= (int)$ped['id'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="recibido">
                                    <button type="submit" class="btn btn-sm btn-success" title="Marcar como recibido"
                                            onclick="return confirm('¿Marcar pedido #<?= (int)$ped['id'] ?> como recibido? Esto generará movimientos de entrada en el inventario.')">
                                        Recibido
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token"   value="<?= generateCsrfToken('cambiar_estado_pedido') ?>">
                                    <input type="hidden" name="accion"       value="cambiar_estado">
                                    <input type="hidden" name="pedido_id"    value="<?= (int)$ped['id'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="cancelado">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Cancelar pedido"
                                            onclick="return confirm('¿Cancelar el pedido #<?= (int)$ped['id'] ?>?')">
                                        Cancelar
                                    </button>
                                </form>
                                <?php endif; ?>
                                <a href="pedidos.php?pdf=<?= (int)$ped['id'] ?>" class="btn btn-sm btn-ghost" title="Descargar PDF" target="_blank">PDF</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<script src="js/app.js"></script>
</body>
</html>
