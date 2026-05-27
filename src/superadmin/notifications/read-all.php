<?php
/**
 * Endpoint JSON: marca todas las notificaciones como leídas.
 *
 * POST /superadmin/notifications/read-all.php
 *
 * @package  Es21Plus\Superadmin\Notifications
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/../../core/NotificationService.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

SuperadminMiddleware::require();

NotificationService::markAllRead();

echo json_encode(['success' => true, 'message' => 'Todas las notificaciones marcadas como leídas.']);
