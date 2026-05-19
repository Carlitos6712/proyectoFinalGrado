<?php
/**
 * Campañas de email masivo — panel superadmin.
 *
 * Acciones GET:  list (default), create
 * Acciones POST: store (guarda campaña, envía si es inmediata)
 * Acciones GET:  estimate (devuelve JSON con conteo de destinatarios)
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/../core/CampaignService.php';

SuperadminMiddleware::require();

$action     = $_GET['action'] ?? 'list';
$base       = './';
$cssBase    = '../';
$activeMenu = 'campaigns';

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pdo = Database::getInstance();

// ── Endpoint: estimación de destinatarios (JSON) ──────────────────────────
if ($action === 'estimate') {
    header('Content-Type: application/json; charset=utf-8');
    $plan   = $_GET['plan']   ?? 'all';
    $status = $_GET['status'] ?? 'active';
    $count  = CampaignService::estimateRecipients($plan, $status);
    echo json_encode(['count' => $count]);
    exit;
}

// ── POST: crear campaña ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subject    = trim($_POST['subject'] ?? '');
        $bodyHtml   = trim($_POST['body_html'] ?? '');
        $targetPlan = $_POST['target_plan']   ?? 'all';
        $targetStat = $_POST['target_status'] ?? 'active';
        $sendMode   = $_POST['send_mode']     ?? 'immediate'; // immediate | scheduled
        $scheduledAt = null;

        if (!$subject) throw new AppException('El asunto es obligatorio.', 400);
        if (!$bodyHtml) throw new AppException('El cuerpo de la campaña es obligatorio.', 400);
        if (!in_array($targetPlan, ['all','free','basic','pro'], true)) throw new AppException('Plan inválido.', 400);
        if (!in_array($targetStat, ['all','active','inactive'], true)) throw new AppException('Estado inválido.', 400);

        if ($sendMode === 'scheduled') {
            $scheduledAt = trim($_POST['scheduled_at'] ?? '');
            if (!$scheduledAt) throw new AppException('Especifica la fecha y hora de envío programado.', 400);
        }

        $status = ($sendMode === 'immediate') ? 'draft' : 'draft'; // siempre draft; lo cambia CampaignService::send

        $stmt = $pdo->prepare(
            'INSERT INTO email_campaigns (subject, body_html, target_plan, target_status, status, scheduled_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$subject, $bodyHtml, $targetPlan, $targetStat, $status, $scheduledAt ?: null]);
        $campaignId = (int)$pdo->lastInsertId();

        if ($sendMode === 'immediate') {
            CampaignService::send($campaignId);
            $_SESSION['flash_success'] = 'Campaña enviada correctamente.';
        } else {
            $_SESSION['flash_success'] = 'Campaña programada para ' . htmlspecialchars($scheduledAt, ENT_QUOTES, 'UTF-8') . '.';
        }

        header('Location: campaigns.php');
        exit;
    } catch (AppException $e) {
        $flashError = $e->getMessage();
        $action     = 'create'; // Volver al formulario
    } catch (\Throwable) {
        $flashError = 'Error interno al procesar la campaña.';
        $action     = 'create';
    }
}

// ── GET views ─────────────────────────────────────────────────────────────
$pageTitle = 'Campañas de email';

if ($action === 'create') {
    ob_start();
    include __DIR__ . '/views/campaigns/create.php';
    $content = ob_get_clean();
} else {
    // list
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;
    $offset  = ($page - 1) * $perPage;

    $total = (int)$pdo->query('SELECT COUNT(*) FROM email_campaigns')->fetchColumn();
    $stmt  = $pdo->prepare(
        'SELECT * FROM email_campaigns ORDER BY created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute([$perPage, $offset]);
    $campaigns = $stmt->fetchAll();
    $pages     = (int)ceil($total / $perPage);

    ob_start();
    include __DIR__ . '/views/campaigns/index.php';
    $content = ob_get_clean();
}

require __DIR__ . '/views/layout.php';
