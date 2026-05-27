<?php
/**
 * Endpoint JSON: lista las últimas 10 notificaciones no leídas.
 *
 * GET /superadmin/notifications/list.php
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

SuperadminMiddleware::require();

echo json_encode([
    'success' => true,
    'data'    => NotificationService::getUnread(10),
]);
