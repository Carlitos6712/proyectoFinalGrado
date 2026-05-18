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

$statusLabels = ['open'=>'Abierto','in_progress'=>'En proceso','resolved'=>'Resuelto','closed'=>'Cerrado'];
$statusColors = ['open'=>'badge-unread','in_progress'=>'badge-basic','resolved'=>'badge-active','closed'=>'badge-free'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?> – es21plus</title>
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
                <a href="mis-tickets.php" class="breadcrumb-item">Soporte</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active"><?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?></span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php require_once __DIR__ . '/../includes/topbar_user.php'; ?>
        </div>
    </header>

    <main class="content-wrapper">

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" style="margin-bottom:1rem;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error" style="margin-bottom:1rem;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem;">
            <a href="mis-tickets.php" style="font-size:.84rem;color:var(--accent);text-decoration:none;">← Mis tickets</a>
            <code style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?></code>
            <span class="<?= $statusColors[$ticket['status']] ?? 'badge-free' ?>">
                <?= $statusLabels[$ticket['status']] ?? $ticket['status'] ?>
            </span>
        </div>

        <div style="max-width:720px;">
            <!-- Título -->
            <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.25rem 1.5rem;margin-bottom:1rem;">
                <h2 style="margin:0 0 .25rem;font-size:1.05rem;color:var(--text-primary);">
                    <?= htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8') ?>
                </h2>
                <p style="margin:0;font-size:.78rem;color:var(--text-muted);">
                    Abierto el <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                </p>
            </div>

            <!-- Mensajes -->
            <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1rem;">
                <?php foreach ($messages as $msg): ?>
                <?php $isSA = $msg['sender_type'] === 'superadmin'; ?>
                <div style="display:flex;gap:.75rem;<?= $isSA ? 'justify-content:flex-start;' : 'justify-content:flex-end;' ?>">
                    <?php if ($isSA): ?>
                    <div style="width:34px;height:34px;border-radius:50%;background:#6366f1;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#fff;flex-shrink:0;">SA</div>
                    <?php endif; ?>
                    <div style="max-width:78%;background:<?= $isSA ? '#f0f1ff' : '#f8fafc' ?>;border:1px solid <?= $isSA ? '#c7d2fe' : 'var(--border)' ?>;border-radius:<?= $isSA ? '0 1rem 1rem 1rem' : '1rem 1rem 0 1rem' ?>;padding:.75rem 1rem;">
                        <p style="margin:0 0 .4rem;font-size:.7rem;color:var(--text-muted);">
                            <?= $isSA ? 'Soporte es21plus' : htmlspecialchars($_SESSION['user_name'] ?? 'Tú', ENT_QUOTES, 'UTF-8') ?>
                            · <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                        </p>
                        <p style="margin:0;font-size:.85rem;white-space:pre-wrap;color:var(--text-primary);"><?= htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php if (!$isSA): ?>
                    <div style="width:34px;height:34px;border-radius:50%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#4338ca;flex-shrink:0;">
                        <?= mb_strtoupper(mb_substr($_SESSION['user_name'] ?? 'U', 0, 2)) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Responder (solo si no cerrado) -->
            <?php if (!in_array($ticket['status'], ['closed'], true)): ?>
            <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.25rem 1.5rem;">
                <form method="POST">
                    <input type="hidden" name="_action" value="reply">
                    <div class="form-field" style="margin-bottom:.75rem;">
                        <label class="field-label">Tu respuesta</label>
                        <textarea name="message" class="field-input" rows="4" required
                            placeholder="Escribe tu respuesta…" style="resize:vertical;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Enviar respuesta</button>
                </form>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted);font-size:.84rem;text-align:center;padding:1rem 0;">
                Este ticket está cerrado. Si necesitas más ayuda, abre un nuevo ticket.
            </p>
            <?php endif; ?>
        </div>
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
