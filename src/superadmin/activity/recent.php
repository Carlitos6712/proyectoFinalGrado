<?php
/**
 * Endpoint JSON: últimas 15 actividades para el feed del dashboard.
 *
 * GET /superadmin/activity/recent.php
 *
 * @package  Es21Plus\Superadmin\Activity
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/../controllers/DashboardController.php';

header('Content-Type: application/json; charset=UTF-8');

SuperadminMiddleware::require();

$ctrl  = new DashboardController();
$items = $ctrl->actividadReciente();

// Añadir tiempo relativo
$now = time();
foreach ($items as &$item) {
    $diff = $now - strtotime($item['created_at']);
    if ($diff < 60) {
        $item['tiempo_relativo'] = 'hace un momento';
    } elseif ($diff < 3600) {
        $item['tiempo_relativo'] = 'hace ' . floor($diff / 60) . ' minuto(s)';
    } elseif ($diff < 86400) {
        $item['tiempo_relativo'] = 'hace ' . floor($diff / 3600) . ' hora(s)';
    } else {
        $item['tiempo_relativo'] = 'hace ' . floor($diff / 86400) . ' día(s)';
    }
}
unset($item);

echo json_encode(['success' => true, 'data' => $items]);
