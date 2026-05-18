<?php
/**
 * Listado de tickets de soporte del negocio.
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
$tickets    = $ctrl->listForBusiness($businessId);

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$statusColors = ['open'=>'badge-unread','in_progress'=>'badge-basic','resolved'=>'badge-active','closed'=>'badge-free'];
$statusLabels = ['open'=>'Abierto','in_progress'=>'En proceso','resolved'=>'Resuelto','closed'=>'Cerrado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Tickets – es21plus</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body class="layout">

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
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Ayuda</span>
            <a href="mis-tickets.php" class="nav-item active">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                <span class="nav-label">Soporte</span>
            </a>
        </div>
    </nav>
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
                <a href="../index.php" class="breadcrumb-item">Inicio</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active">Mis Tickets</span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php require_once __DIR__ . '/../includes/topbar_user.php'; ?>
        </div>
    </header>

    <main class="content-wrapper">
        <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
            <div>
                <h1 class="page-title">Mis Tickets de Soporte</h1>
                <p class="page-subtitle">Consulta y gestiona tus solicitudes de ayuda.</p>
            </div>
            <a href="crear-ticket.php" class="btn btn-primary">+ Nuevo ticket</a>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" style="margin-bottom:1rem;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error" style="margin-bottom:1rem;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (empty($tickets)): ?>
        <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:3rem;text-align:center;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="margin:0 auto 1rem;display:block;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <p style="color:var(--text-muted);margin-bottom:1.25rem;">Aún no tienes tickets. ¿Necesitas ayuda?</p>
            <a href="crear-ticket.php" class="btn btn-primary">Abrir primer ticket</a>
        </div>
        <?php else: ?>
        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Asunto</th>
                        <th>Estado</th>
                        <th>Mensajes</th>
                        <th>Actualizado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><code style="font-size:.78rem;"><?= htmlspecialchars($t['ticket_number'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><?= htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="<?= $statusColors[$t['status']] ?? 'badge-free' ?>"><?= $statusLabels[$t['status']] ?? $t['status'] ?></span></td>
                        <td style="color:var(--text-muted);"><?= (int)$t['msg_count'] ?></td>
                        <td style="color:var(--text-muted);font-size:.78rem;"><?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?></td>
                        <td><a href="ticket.php?id=<?= $t['id'] ?>" class="btn btn-secondary" style="padding:.25rem .6rem;font-size:.78rem;">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
const menuToggle   = document.getElementById('menuToggle');
const sidebar      = document.getElementById('sidebar');
const sidebarClose = document.getElementById('sidebarClose');
const overlay      = document.getElementById('sidebarOverlay');
function toggleSidebar(){ sidebar.classList.toggle('open'); overlay.classList.toggle('active'); }
menuToggle?.addEventListener('click', toggleSidebar);
sidebarClose?.addEventListener('click', toggleSidebar);
overlay?.addEventListener('click', toggleSidebar);
</script>
</body>
</html>
