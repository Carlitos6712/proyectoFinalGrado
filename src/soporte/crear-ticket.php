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
                <a href="mis-tickets.php" class="breadcrumb-item">Soporte</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active">Nuevo Ticket</span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php require_once __DIR__ . '/../includes/topbar_user.php'; ?>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="content">

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Nuevo Ticket de Soporte</h1>
                <p class="page-subtitle">Describe tu problema y te responderemos lo antes posible.</p>
            </div>
            <div class="page-actions">
                <a href="mis-tickets.php" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Volver
                </a>
            </div>
        </div>

        <?php if ($flashError): ?>
        <div class="alert-banner alert-banner-error" style="margin-bottom:1.25rem;">
            <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <div class="card card-form" style="max-width:680px;">
            <div class="card-header">
                <h2 class="card-title">Detalles del ticket</h2>
                <p class="card-subtitle">Los campos marcados con <span style="color:var(--danger,#ef4444)">*</span> son obligatorios</p>
            </div>
            <div class="card-body">
                <form method="POST" class="form-grid-wrapper">
                    <div class="form-grid">
                        <div class="form-field form-field-full">
                            <label class="field-label" for="subject">
                                Asunto <span style="color:var(--danger,#ef4444)">*</span>
                            </label>
                            <input type="text" id="subject" name="subject" class="field-input" required maxlength="255"
                                   placeholder="Describe brevemente el problema…"
                                   value="<?= htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-field form-field-full">
                            <label class="field-label" for="message">
                                Descripción detallada <span style="color:var(--danger,#ef4444)">*</span>
                            </label>
                            <textarea id="message" name="message" class="field-input field-textarea" rows="7" required
                                      placeholder="Explica el problema con el mayor detalle posible: qué ocurrió, cuándo, qué esperabas que pasara…"
                                      style="resize:vertical;"><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="mis-tickets.php" class="btn-ghost">Cancelar</a>
                        <button type="submit" class="btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Enviar ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>
</div>

<script src="../js/app.js"></script>
</body>
</html>
