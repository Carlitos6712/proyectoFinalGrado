<?php
/**
 * Verifica disponibilidad de un slug de empresa.
 *
 * GET /api/check_slug.php?slug=mi-taller
 * Respuesta: { "available": true|false }
 *
 * @package  Es21Plus\Api
 * @author   Carlos Vico
 * @version  1.0.0
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/Database.php';

$slug = trim($_GET['slug'] ?? '');

if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 100) {
    echo json_encode(['available' => false]);
    exit;
}

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM businesses WHERE slug = ?');
    $stmt->execute([$slug]);
    $exists = (int)$stmt->fetchColumn() > 0;
    echo json_encode(['available' => !$exists]);
} catch (\Throwable) {
    echo json_encode(['available' => false]);
}
