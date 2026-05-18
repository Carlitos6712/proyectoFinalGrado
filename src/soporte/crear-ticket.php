<?php
/**
 * Formulario de creación de ticket de soporte — panel de empresa.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../superadmin/controllers/TicketController.php';

$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ctrl       = new TicketController();
    $businessId = (int)($_SESSION['business_id'] ?? 0);
    $employeeId = (int)($_SESSION['user_id']     ?? 0);

    try {
        $ticketId = $ctrl->createTicket(
            trim($_POST['subject']  ?? ''),
            trim($_POST['message']  ?? ''),
            $businessId,
            $employeeId
        );
        $_SESSION['flash_success'] = 'Ticket creado correctamente. Recibirás una respuesta pronto.';
        header('Location: ticket.php?id=' . $ticketId);
        exit;
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Ticket – es21plus</title>
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
                <span class="breadcrumb-item active">Nuevo Ticket</span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php require_once __DIR__ . '/../includes/topbar_user.php'; ?>
        </div>
    </header>

    <main class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Nuevo Ticket de Soporte</h1>
            <p class="page-subtitle">Describe tu problema y te responderemos lo antes posible.</p>
        </div>

        <?php if ($flashError): ?>
            <div class="alert alert-error" style="margin-bottom:1rem;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div style="max-width:640px;">
            <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.75rem;">
                <form method="POST">
                    <div class="form-field" style="margin-bottom:1rem;">
                        <label class="field-label">Asunto <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="subject" class="field-input" required maxlength="255"
                            placeholder="Describe brevemente el problema…"
                            value="<?= htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-field" style="margin-bottom:1.5rem;">
                        <label class="field-label">Descripción detallada <span style="color:#dc2626;">*</span></label>
                        <textarea name="message" class="field-input" rows="6" required
                            placeholder="Explica el problema con el mayor detalle posible: qué ocurrió, cuándo, qué esperabas que pasara…"
                            style="resize:vertical;"><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div style="display:flex;gap:.75rem;align-items:center;">
                        <button type="submit" class="btn btn-primary">Enviar ticket</button>
                        <a href="mis-tickets.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
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
