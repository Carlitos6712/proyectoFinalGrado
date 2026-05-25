<?php
/**
 * Vista de hilo de ticket — panel de empresa.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../superadmin/controllers/TicketController.php';

$ctrl       = new TicketController();
$businessId = (int)($_SESSION['business_id'] ?? 0);
$employeeId = (int)($_SESSION['user_id']     ?? 0);
$id         = (int)($_GET['id'] ?? 0);

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'reply') {
    try {
        $ctrl->replyFromBusiness($id, $businessId, $employeeId, trim($_POST['message'] ?? ''));
        $flashSuccess = 'Respuesta enviada.';
        header('Location: ticket.php?id=' . $id);
        exit;
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    }
}

try {
    $data = $ctrl->getTicketForBusiness($id, $businessId);
} catch (AppException $e) {
    http_response_code(404);
    die('Ticket no encontrado o no tienes acceso.');
}

$ticket   = $data['ticket'];
$messages = $data['messages'];

$statusLabels = ['open' => 'Abierto', 'in_progress' => 'En proceso', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
$statusColors = [
    'open'        => 'status-pill status-pill-warning',
    'in_progress' => 'status-pill',
    'resolved'    => 'status-pill status-pill-success',
    'closed'      => 'status-pill status-pill-inactive',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?> – es21plus</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        .msg-bubble {
            border-radius: var(--radius-lg);
            padding: .875rem 1.1rem;
            font-size: .875rem;
            line-height: 1.6;
        }
        .msg-bubble-sa   { background: #f0f1ff; border: 1px solid #c7d2fe; border-radius: 0 var(--radius-lg) var(--radius-lg) var(--radius-lg); }
        .msg-bubble-user { background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: var(--radius-lg) var(--radius-lg) 0 var(--radius-lg); }
        .msg-meta { font-size: .72rem; color: var(--text-muted); margin-bottom: .35rem; }
        .msg-avatar-sa   { background: #6366f1; color: #fff; }
        .msg-avatar-user { background: #e0e7ff; color: #4338ca; }
        .msg-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; flex-shrink: 0;
        }
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
            <a href="../index.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="../productos.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span>
                <span class="nav-label">Productos</span>
            </a>
            <a href="../categorias.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                <span class="nav-label">Categorías</span>
            </a>
            <a href="../marcas.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></span>
                <span class="nav-label">Marcas</span>
            </a>
            <?php if (isAdmin()): ?>
            <a href="../modelos_moto.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="18" r="3"/><path d="M6 18H4a2 2 0 0 1-2-2v-5l2-5h13l2 5v7h-3M14 18H8"/></svg></span>
                <span class="nav-label">Modelos de Moto</span>
            </a>
            <a href="../proveedores.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></span>
                <span class="nav-label">Proveedores</span>
            </a>
            <a href="../pedidos.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
                <span class="nav-label">Pedidos</span>
            </a>
            <?php endif; ?>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Operaciones</span>
            <a href="../movimientos.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></span>
                <span class="nav-label">Movimientos</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Administración</span>
            <a href="../auditoria.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                <span class="nav-label">Auditoría</span>
            </a>
            <?php if (isAdmin()): ?>
            <a href="../usuarios.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="nav-label">Usuarios</span>
            </a>
            <?php endif; ?>
            <?php if ((($_SESSION['user_role'] ?? '') === 'admin')): ?>
            <a href="../perfil_empresa.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
                <span class="nav-label">Perfil de empresa</span>
            </a>
            <?php endif; ?>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Ayuda</span>
            <a href="mis-tickets.php" class="nav-item active">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                <span class="nav-label">Soporte</span>
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
                <a href="../index.php" class="breadcrumb-item">Inicio</a>
                <span class="breadcrumb-sep">›</span>
                <a href="mis-tickets.php" class="breadcrumb-item">Soporte</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active"><?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?></span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php require_once __DIR__ . '/../includes/topbar_user.php'; ?>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="content">

        <?php if ($flashSuccess): ?>
        <div class="alert-banner alert-banner-success" style="margin-bottom:1.25rem;">
            <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
        <div class="alert-banner alert-banner-error" style="margin-bottom:1.25rem;">
            <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title"><?= htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="page-subtitle">
                    <code style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?></code>
                    &nbsp;·&nbsp;
                    <span class="<?= $statusColors[$ticket['status']] ?? 'status-pill' ?>"><?= $statusLabels[$ticket['status']] ?? $ticket['status'] ?></span>
                    &nbsp;·&nbsp;
                    <span style="font-size:.82rem;color:var(--text-muted);">Abierto el <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></span>
                </p>
            </div>
            <div class="page-actions">
                <a href="mis-tickets.php" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Mis tickets
                </a>
            </div>
        </div>

        <!-- Hilo de mensajes -->
        <div style="max-width:740px;display:flex;flex-direction:column;gap:.875rem;margin-bottom:1.5rem;">
            <?php foreach ($messages as $msg): ?>
            <?php $isSA = $msg['sender_type'] === 'superadmin'; ?>
            <div style="display:flex;gap:.75rem;align-items:flex-start;<?= $isSA ? '' : 'flex-direction:row-reverse;' ?>">
                <div class="msg-avatar <?= $isSA ? 'msg-avatar-sa' : 'msg-avatar-user' ?>">
                    <?= $isSA ? 'SA' : mb_strtoupper(mb_substr($_SESSION['user_name'] ?? 'U', 0, 2)) ?>
                </div>
                <div style="max-width:78%;">
                    <p class="msg-meta">
                        <?= $isSA ? 'Soporte es21plus' : htmlspecialchars($_SESSION['user_name'] ?? 'Tú', ENT_QUOTES, 'UTF-8') ?>
                        · <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                    </p>
                    <div class="msg-bubble <?= $isSA ? 'msg-bubble-sa' : 'msg-bubble-user' ?>">
                        <?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Responder -->
        <?php if (!in_array($ticket['status'], ['closed'], true)): ?>
        <div class="card card-form" style="max-width:740px;">
            <div class="card-header">
                <h3 class="card-title" style="font-size:.95rem;">Responder</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="_action" value="reply">
                    <div class="form-field" style="margin-bottom:.875rem;">
                        <textarea name="message" class="field-input" rows="4" required
                                  placeholder="Escribe tu respuesta…" style="resize:vertical;"></textarea>
                    </div>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn-primary">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Enviar respuesta
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card" style="max-width:740px;text-align:center;padding:1.5rem;">
            <p style="color:var(--text-muted);font-size:.875rem;">
                Este ticket está cerrado. Si necesitas más ayuda,
                <a href="crear-ticket.php" style="color:var(--accent);">abre un nuevo ticket</a>.
            </p>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="../js/app.js"></script>
</body>
</html>
