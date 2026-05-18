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

// Solo usuarios autenticados
if (empty($_SESSION['usuario_id'])) {
    jsonResp(false, null, 'No autenticado.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResp(false, null, 'Método no permitido.', 405);
}

try {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $passwordActual = $body['password_actual'] ?? '';
    $passwordNueva  = $body['password_nueva']  ?? '';

    if ($passwordActual === '' || $passwordNueva === '') {
        jsonResp(false, null, 'password_actual y password_nueva son obligatorios.', 400);
    }

    $model = new Usuario();
    $model->cambiarPassword(
        (int) $_SESSION['usuario_id'],
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
