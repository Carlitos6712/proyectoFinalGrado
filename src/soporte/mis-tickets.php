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

$statusColors = [
    'open'        => 'status-pill status-pill-warning',
    'in_progress' => 'status-pill',
    'resolved'    => 'status-pill status-pill-success',
    'closed'      => 'status-pill status-pill-inactive',
];
$statusLabels = ['open' => 'Abierto', 'in_progress' => 'En proceso', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
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

<?php $sidebarBasePath = '../'; require_once __DIR__ . '/../includes/_sidebar.php'; ?>

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
                <span class="breadcrumb-item active">Mis Tickets</span>
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
                <h1 class="page-title">Mis Tickets de Soporte</h1>
                <p class="page-subtitle">Consulta y gestiona tus solicitudes de ayuda.</p>
            </div>
            <div class="page-actions">
                <a href="crear-ticket.php" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo ticket
                </a>
            </div>
        </div>

        <?php if (empty($tickets)): ?>
        <div class="card" style="text-align:center;padding:3rem 2rem;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-muted);margin:0 auto 1rem;display:block;">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <p style="color:var(--text-muted);margin-bottom:1.25rem;">Aún no tienes tickets. ¿Necesitas ayuda?</p>
            <a href="crear-ticket.php" class="btn-primary">Abrir primer ticket</a>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body" style="padding:0;">
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
                            <td><code style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($t['ticket_number'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td style="font-weight:500;"><?= htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="<?= $statusColors[$t['status']] ?? 'status-pill' ?>"><?= $statusLabels[$t['status']] ?? $t['status'] ?></span></td>
                            <td style="color:var(--text-muted);text-align:center;"><?= (int)$t['msg_count'] ?></td>
                            <td style="color:var(--text-muted);font-size:.82rem;"><?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?></td>
                            <td>
                                <a href="ticket.php?id=<?= $t['id'] ?>" class="btn-ghost" style="padding:.25rem .65rem;font-size:.8rem;">Ver →</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="../js/app.js"></script>
</body>
</html>
