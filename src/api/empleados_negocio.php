<?php
/**
 * Devuelve los empleados activos de un negocio (sin datos sensibles).
 *
 * Usado en el formulario de login para mostrar el selector de empleado.
 * No requiere autenticación — solo expone nombre e ID.
 *
 * GET /api/empleados_negocio.php?business=<nombre_o_slug>
 *
 * @package  Es21Plus\Api
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/Database.php';

header('Content-Type: application/json; charset=UTF-8');

$business = trim($_GET['business'] ?? '');

if ($business === '') {
    echo json_encode(['success' => false, 'data' => [], 'message' => 'Empresa requerida.']);
    exit;
}

try {
    $pdo  = Database::getInstance();

    $stmt = $pdo->prepare(
        "SELECT id FROM businesses
         WHERE (name = ? OR slug = ?) AND is_active = 1 LIMIT 1"
    );
    $stmt->execute([$business, $business]);
    $biz = $stmt->fetch();

    if (!$biz) {
        echo json_encode(['success' => false, 'data' => [], 'message' => 'Empresa no encontrada.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id, name, email, role FROM employees
         WHERE business_id = ? AND is_active = 1 ORDER BY name ASC"
    );
    $stmt->execute([$biz['id']]);
    $empleados = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $empleados, 'message' => 'OK']);
} catch (\Throwable) {
    echo json_encode(['success' => false, 'data' => [], 'message' => 'Error interno.']);
}
