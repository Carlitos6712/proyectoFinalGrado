<?php
/**
 * Onboarding paso 2: Inserta los primeros productos del negocio.
 *
 * POST — application/x-www-form-urlencoded o JSON
 * Campos: products[] = [{name, categoria_id, stock}, ...]  (hasta 5)
 *
 * @package  Es21Plus\Api\Onboarding
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';

if (empty($_SESSION['business_id']) || empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

$businessId = (int)$_SESSION['business_id'];

try {
    $pdo = Database::getInstance();

    // Aceptar datos como JSON o form
    $input    = json_decode(file_get_contents('php://input'), true);
    $products = $input['products'] ?? $_POST['products'] ?? [];

    if (!is_array($products)) {
        throw new AppException('Formato de productos inválido.', 400);
    }

    $products = array_slice($products, 0, 5); // Máximo 5

    $stmt = $pdo->prepare(
        'INSERT INTO productos (nombre, categoria_id, stock, stock_minimo, business_id, precio)
         VALUES (?, ?, ?, 5, ?, 0.00)'
    );

    foreach ($products as $p) {
        $pNombre  = trim($p['name'] ?? '');
        $pCat     = (int)($p['categoria_id'] ?? 0);
        $pStock   = max(0, (int)($p['stock'] ?? 0));

        if (!$pNombre) continue; // Saltar filas vacías

        $stmt->execute([$pNombre, $pCat ?: null, $pStock, $businessId]);
    }

    echo json_encode(['success' => true]);
} catch (AppException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
