<?php
/**
 * Guarda información general del negocio.
 *
 * POST JSON: { name, phone, address, contact_email }
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

if (empty($_SESSION['business_id']) || empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

$businessId = (int)$_SESSION['business_id'];

try {
    $data  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $csrfToken = $data['csrf_token'] ?? '';
    if (!validateCsrfToken('perfil_empresa', $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
        exit;
    }

    $pdo   = Database::getInstance();

    $name    = trim($data['name'] ?? '');
    $phone   = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $email   = trim($data['contact_email'] ?? '');

    if (!$name) throw new AppException('El nombre es obligatorio.', 400);
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new AppException('El email de contacto no es válido.', 400);
    }

    $pdo->prepare(
        'UPDATE businesses SET name = ?, phone = ?, address = ?, contact_email = ? WHERE id = ?'
    )->execute([$name, $phone ?: null, $address ?: null, $email ?: null, $businessId]);

    $_SESSION['business_name'] = $name;

    echo json_encode(['success' => true]);
} catch (AppException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
