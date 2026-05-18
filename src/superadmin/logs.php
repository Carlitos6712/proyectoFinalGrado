<?php
/**
 * Registro de actividad global — panel superadmin.
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/controllers/LogController.php';

SuperadminMiddleware::require();

$ctrl       = new LogController();
$base       = './';
$cssBase    = '../';
$activeMenu = 'logs';

$pdo = Database::getInstance();
$mensajesSinLeer = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$filters = [
    'business_id' => (int)($_GET['business_id'] ?? 0),
    'empleado'    => trim($_GET['empleado'] ?? ''),
    'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
    'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
];
$page   = max(1, (int)($_GET['page'] ?? 1));
$result = $ctrl->index($filters, $page);
$negocios = $ctrl->negocios();

$pageTitle = 'Registro de actividad';

ob_start();
include __DIR__ . '/views/logs/index.php';
$content = ob_get_clean();

require __DIR__ . '/views/layout.php';
