<?php
/**
 * Bandeja de mensajes de contacto — panel superadmin.
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
require_once __DIR__ . '/controllers/ContactController.php';
require_once __DIR__ . '/../core/Mailer.php';

SuperadminMiddleware::require();

$ctrl       = new ContactController();
$action     = $_GET['action'] ?? 'list';
$id         = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$base       = './';
$cssBase    = '../';
$activeMenu = 'contact';

$pdo = Database::getInstance();
$mensajesSinLeer = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken('sa_contact', $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: contact.php');
        exit;
    }
    $postAction = $_POST['_action'] ?? '';
    try {
        if ($postAction === 'reply') {
            $ctrl->reply($id, trim($_POST['reply_body'] ?? ''));
            $_SESSION['flash_success'] = 'Respuesta enviada correctamente.';
            header("Location: contact.php?action=show&id={$id}");
            exit;
        }
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    } catch (\RuntimeException $e) {
        $flashError = 'Error al enviar correo: ' . $e->getMessage();
    }
}

$csrfToken = generateCsrfToken('sa_contact');

switch ($action) {
    case 'show':
        try {
            $message   = $ctrl->show($id);
            $pageTitle = 'Mensaje de ' . htmlspecialchars($message['sender_name'], ENT_QUOTES, 'UTF-8');
            ob_start();
            include __DIR__ . '/views/contact/show.php';
            $content = ob_get_clean();
        } catch (AppException $e) {
            $flashError = $e->getMessage();
            header('Location: contact.php');
            exit;
        }
        break;

    default:
        $messages  = $ctrl->index();
        $pageTitle  = 'Mensajes de contacto';
        ob_start();
        include __DIR__ . '/views/contact/index.php';
        $content = ob_get_clean();
}

require __DIR__ . '/views/layout.php';
