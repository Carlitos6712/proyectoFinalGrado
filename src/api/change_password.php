<?php
/**
 * API: cambio de contraseña del usuario autenticado.
 *
 * POST /api/change_password.php
 * Body JSON: { password_actual, password_nueva }
 *
 * @package  Es21Plus\Api
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/Usuario.php';
require_once __DIR__ . '/../includes/Auditoria.php';

/**
 * @param bool   $success
 * @param mixed  $data
 * @param string $message
 * @param int    $status
 * @return never
 */
function jsonResp(bool $success, mixed $data, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Control de acceso — soporta sesiones locales y multi-tenant ───────────────
$userId         = (int) ($_SESSION['usuario_id'] ?? 0);
$businessUserId = (int) ($_SESSION['user_id']    ?? 0);
$isAuth         = $userId > 0 || ($businessUserId > 0 && !empty($_SESSION['business_id']));
if (!$isAuth) {
    jsonResp(false, null, 'No autenticado.', 401);
}
$resolvedId = $userId > 0 ? $userId : $businessUserId;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResp(false, null, 'Método no permitido.', 405);
}

try {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $csrfToken = $body['csrf_token'] ?? '';
    if (!validateCsrfToken('cambiar_password_topbar', $csrfToken)) {
        jsonResp(false, null, 'Token CSRF inválido.', 403);
    }

    $passwordActual = $body['password_actual'] ?? '';
    $passwordNueva  = $body['password_nueva']  ?? '';

    if ($passwordActual === '' || $passwordNueva === '') {
        jsonResp(false, null, 'password_actual y password_nueva son obligatorios.', 400);
    }

    $model = new Usuario();
    $model->cambiarPassword(
        $resolvedId,
        $passwordActual,
        $passwordNueva,
        Auditoria::instancia()
    );

    jsonResp(true, null, 'Contraseña cambiada correctamente.');

} catch (AppException $e) {
    jsonResp(false, null, $e->getMessage(), $e->getCode() ?: 400);
} catch (\Throwable $e) {
    jsonResp(false, null, 'Error interno del servidor.', 500);
}
