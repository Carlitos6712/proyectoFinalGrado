<?php
/**
 * Gestión de anuncios globales — panel superadmin.
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/controllers/AnnouncementController.php';

SuperadminMiddleware::require();

$ctrl       = new AnnouncementController();
$action     = $_GET['action'] ?? 'list';
$id         = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$base       = './';
$cssBase    = '../';
$activeMenu = 'announcements';

$pdo = Database::getInstance();
$mensajesSinLeer = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken('sa_announcements', $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: announcements.php');
        exit;
    }
    $postAction = $_POST['_action'] ?? '';
    try {
        if ($postAction === 'store') {
            $ctrl->store($_POST);
            $_SESSION['flash_success'] = 'Anuncio creado.';
            header('Location: announcements.php');
            exit;
        }
        if ($postAction === 'toggle') {
            $nuevo = $ctrl->toggle($id);
            $_SESSION['flash_success'] = $nuevo ? 'Anuncio activado.' : 'Anuncio desactivado.';
            header('Location: announcements.php');
            exit;
        }
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    }
}

$csrfToken = generateCsrfToken('sa_announcements');

switch ($action) {
    case 'create':
        $pageTitle = 'Nuevo anuncio';
        ob_start();
        include __DIR__ . '/views/announcements/create.php';
        $content = ob_get_clean();
        break;

    default:
        $announcements = $ctrl->index();
        $pageTitle      = 'Anuncios globales';
        ob_start();
        include __DIR__ . '/views/announcements/index.php';
        $content = ob_get_clean();
}

require __DIR__ . '/views/layout.php';
