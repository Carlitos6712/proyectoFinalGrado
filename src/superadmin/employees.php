<?php
/**
 * Gestión de empleados — panel superadmin.
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
require_once __DIR__ . '/controllers/EmployeeController.php';

SuperadminMiddleware::require();

$ctrl       = new EmployeeController();
$action     = $_GET['action'] ?? 'list';
$id         = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : 0;
$base       = './';
$cssBase    = '../';
$activeMenu = 'employees';

$mensajesSinLeer = 0;
try {
    $pdo = Database::getInstance();
    $mensajesSinLeer = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
} catch (\Throwable) {}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['_action'] ?? $action;

    if (!validateCsrfToken('sa_employees', $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF inválido.';
        header('Location: employees.php');
        exit;
    }

    try {
        switch ($postAction) {
            case 'store':
                $ctrl->store($_POST);
                $_SESSION['flash_success'] = 'Empleado creado.';
                header('Location: employees.php');
                exit;

            case 'update':
                $ctrl->update($id, $_POST);
                $_SESSION['flash_success'] = 'Empleado actualizado.';
                header("Location: employees.php");
                exit;

            case 'toggle':
                $nuevo = $ctrl->toggle($id);
                $_SESSION['flash_success'] = $nuevo ? 'Empleado activado.' : 'Empleado desactivado.';
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'employees.php'));
                exit;

            case 'reset-password':
                $ctrl->resetPassword($id);
                $_SESSION['flash_success'] = 'Contraseña reseteada y enviada por correo.';
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'employees.php'));
                exit;
        }
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    }
}

$csrfToken = generateCsrfToken('sa_employees');
$negocios  = $ctrl->negociosActivos();

switch ($action) {
    case 'create':
        $pageTitle = 'Nuevo empleado';
        ob_start();
        include __DIR__ . '/views/employees/create.php';
        $content = ob_get_clean();
        break;

    case 'edit':
        try {
            $employee  = $ctrl->find($id);
            $pageTitle = 'Editar – ' . htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8');
            ob_start();
            include __DIR__ . '/views/employees/edit.php';
            $content = ob_get_clean();
        } catch (AppException $e) {
            $flashError = $e->getMessage();
            header('Location: employees.php');
            exit;
        }
        break;

    default:
        $employees = $ctrl->index($businessId);
        $pageTitle  = 'Empleados';
        ob_start();
        include __DIR__ . '/views/employees/index.php';
        $content = ob_get_clean();
}

require __DIR__ . '/views/layout.php';
