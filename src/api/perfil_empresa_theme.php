<?php
/**
 * Actualiza el color de marca del negocio.
 *
 * POST JSON: { theme_color: '#rrggbb' }
 *
 * @package  Es21Plus\Api
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AppException.php';

if (empty($_SESSION['business_id']) || empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

$businessId = (int)$_SESSION['business_id'];

try {
    $data  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $color = trim($data['theme_color'] ?? '');

    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        throw new AppException('Color inválido. Usa formato #rrggbb.', 400);
    }

    $pdo = Database::getInstance();
    $pdo->prepare('UPDATE businesses SET theme_color = ? WHERE id = ?')->execute([$color, $businessId]);

    $_SESSION['business_theme_color'] = $color;

    echo json_encode(['success' => true, 'theme_color' => $color]);
} catch (AppException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
