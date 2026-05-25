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
            <?php if ($esAdmin): ?>
            <a href="modelos_moto.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'modelos_moto.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="18" r="3"/><path d="M6 18H4a2 2 0 0 1-2-2v-5l2-5h13l2 5v7h-3M14 18H8"/></svg></span>
                <span class="nav-label">Modelos de Moto</span>
            </a>
            <a href="proveedores.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'proveedores.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></span>
                <span class="nav-label">Proveedores</span>
            </a>
            <a href="pedidos.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
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
            <?php if ($esAdmin): ?>
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
                                    <input type="hidden" name="accion"       value="cambiar_estado">
                                    <input type="hidden" name="pedido_id"    value="<?= (int)$ped['id'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="enviado">
                                    <button type="submit" class="btn btn-sm btn-primary" title="Marcar como enviado"
                                            onclick="return confirm('¿Marcar pedido #<?= (int)$ped['id'] ?> como enviado?')">
                                        Enviado
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
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
                                    <input type="hidden" name="accion"       value="cambiar_estado">
                                    <input type="hidden" name="pedido_id"    value="<?= (int)$ped['id'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="recibido">
                                    <button type="submit" class="btn btn-sm btn-success" title="Marcar como recibido"
                                            onclick="return confirm('¿Marcar pedido #<?= (int)$ped['id'] ?> como recibido? Esto generará movimientos de entrada en el inventario.')">
                                        Recibido
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
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
