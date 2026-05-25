<?php
/**
 * Endpoint JSON: envía un email de prueba con la config SMTP actual.
 *
 * POST /superadmin/settings/test-email.php
 *
 * @package  Es21Plus\Superadmin\Settings
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/../../core/Settings.php';
require_once __DIR__ . '/../../core/Mailer.php';
require_once __DIR__ . '/../controllers/SettingsController.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

SuperadminMiddleware::require();

try {
    $ctrl = new SettingsController();
    $ctrl->testEmail();
    echo json_encode(['success' => true, 'message' => 'Email de prueba enviado correctamente.']);
} catch (AppException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error SMTP: ' . $e->getMessage()]);
}
