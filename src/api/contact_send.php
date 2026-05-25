<?php
/**
 * Endpoint público: recibir mensaje de contacto.
 *
 * POST /api/contact_send.php
 * Body JSON: { sender_name, sender_email, subject, message }
 *
 * @package  Es21Plus\Api
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../core/Mailer.php';
require_once __DIR__ . '/../superadmin/controllers/ContactController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Método no permitido.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$businessId = isset($_SESSION['business_id']) ? (int)$_SESSION['business_id'] : null;

try {
    $ctrl = new ContactController();
    $ctrl->recibir($body, $businessId);
    echo json_encode(['success' => true, 'data' => null, 'message' => 'Mensaje enviado correctamente.']);
} catch (AppException $e) {
    http_response_code($e->getCode() ?: 422);
    echo json_encode(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Error interno.']);
}
