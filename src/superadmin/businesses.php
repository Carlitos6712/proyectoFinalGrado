<?php
/**
 * Gestión de negocios — panel superadmin.
 *
 * Acciones GET: list (default), create, show, edit
 * Acciones POST: store, update, toggle
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
require_once __DIR__ . '/controllers/BusinessController.php';

SuperadminMiddleware::require();

$ctrl       = new BusinessController();
$action     = $_GET['action'] ?? 'list';
$id         = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$base       = './';
$cssBase    = '../';
$activeMenu = 'businesses';

$mensajesSinLeer = 0;
try {
    $pdo = Database::getInstance();
    $mensajesSinLeer = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
} catch (\Throwable) {}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── POST dispatcher ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['_action'] ?? $action;

    if (!validateCsrfToken('sa_businesses', $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: businesses.php');
        exit;
    }

    try {
        switch ($postAction) {
            case 'store':
                $newId = $ctrl->store($_POST);
                $_SESSION['flash_success'] = 'Negocio creado correctamente.';
                header("Location: businesses.php?action=show&id={$newId}");
                exit;

            case 'update':
                $ctrl->update($id, $_POST);
                $_SESSION['flash_success'] = 'Negocio actualizado.';
                header("Location: businesses.php?action=show&id={$id}");
                exit;

            case 'toggle':
                $nuevo = $ctrl->toggle($id);
                $_SESSION['flash_success'] = $nuevo ? 'Negocio activado.' : 'Negocio desactivado.';
                header('Location: businesses.php');
                exit;
        }
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    }
}

// ── GET views ────────────────────────────────────────────────
$csrfToken = generateCsrfToken('sa_businesses');

switch ($action) {
    case 'create':
        $pageTitle  = 'Nuevo negocio';
        ob_start();
        include __DIR__ . '/views/businesses/create.php';
        $content = ob_get_clean();
        break;

    case 'show':
        try {
            $business  = $ctrl->show($id);
            $pageTitle = htmlspecialchars($business['name'], ENT_QUOTES, 'UTF-8');
            ob_start();
            include __DIR__ . '/views/businesses/show.php';
            $content = ob_get_clean();
        } catch (AppException $e) {
            $flashError = $e->getMessage();
            header('Location: businesses.php');
            exit;
        }
        break;

    case 'edit':
        try {
            $business  = $ctrl->show($id);
            $pageTitle = 'Editar – ' . htmlspecialchars($business['name'], ENT_QUOTES, 'UTF-8');
            ob_start();
            include __DIR__ . '/views/businesses/edit.php';
            $content = ob_get_clean();
        } catch (AppException $e) {
            $flashError = $e->getMessage();
            header('Location: businesses.php');
            exit;
        }
        break;

    default: // list
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $q        = trim($_GET['q'] ?? '');
        $result   = $ctrl->index($page, $q);
        $pageTitle = 'Negocios';
        ob_start();
        include __DIR__ . '/views/businesses/index.php';
        $content = ob_get_clean();
}

require __DIR__ . '/views/layout.php';
