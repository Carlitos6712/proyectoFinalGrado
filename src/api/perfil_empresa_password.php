<?php
/**
 * Cambia la contraseña del empleado autenticado.
 *
 * POST JSON: { current_password, new_password }
 *
 * @package  Es21Plus\Api
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/csrf.php';

if (empty($_SESSION['business_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

$employeeId = (int)$_SESSION['user_id'];

try {
    $data    = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $csrfToken = $data['csrf_token'] ?? '';
    if (!validateCsrfToken('perfil_empresa', $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
        exit;
    }

    $current = $data['current_password'] ?? '';
    $new     = $data['new_password']     ?? '';

    if (!$current || !$new) {
        throw new AppException('Todos los campos son obligatorios.', 400);
    }
    if (strlen($new) < 8) {
        throw new AppException('La nueva contraseña debe tener al menos 8 caracteres.', 400);
    }

    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare('SELECT password FROM employees WHERE id = ?');
    $stmt->execute([$employeeId]);
    $row  = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password'])) {
        throw new AppException('Contraseña actual incorrecta.', 403);
    }

    $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare('UPDATE employees SET password = ? WHERE id = ?')->execute([$hash, $employeeId]);

    echo json_encode(['success' => true]);
} catch (AppException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
