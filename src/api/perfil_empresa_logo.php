<?php
/**
 * Sube o elimina el logo del negocio.
 *
 * POST multipart: logo (file)  → sube
 * POST JSON: { action: 'delete' }  → elimina
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
require_once __DIR__ . '/../core/Settings.php';

if (empty($_SESSION['business_id']) || empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

$businessId = (int)$_SESSION['business_id'];

try {
    $pdo = Database::getInstance();

    // ── Leer body una sola vez y validar CSRF ───────────────────
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $jsonBody    = [];
    if (str_contains($contentType, 'application/json')) {
        $jsonBody = json_decode(file_get_contents('php://input'), true) ?? [];
    }
    // FormData envía el token en $_POST; JSON lo envía en el body
    $csrfToken = $_POST['csrf_token'] ?? $jsonBody['csrf_token'] ?? '';
    if (!validateCsrfToken('perfil_empresa', $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
        exit;
    }

    // ── Eliminar logo ───────────────────────────────────────────
    $isDelete = ($jsonBody['action'] ?? '') === 'delete';

    if ($isDelete) {
        $row = $pdo->prepare('SELECT logo_path FROM businesses WHERE id = ?');
        $row->execute([$businessId]);
        $logoPath = $row->fetchColumn();

        if ($logoPath) {
            $absPath = __DIR__ . '/../' . $logoPath;
            if (file_exists($absPath)) @unlink($absPath);
        }

        $pdo->prepare('UPDATE businesses SET logo_path = NULL WHERE id = ?')->execute([$businessId]);
        $_SESSION['business_logo'] = null;

        echo json_encode(['success' => true]);
        exit;
    }

    // ── Subir logo ──────────────────────────────────────────────
    if (empty($_FILES['logo']['tmp_name']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        throw new AppException('No se recibió ningún archivo válido.', 400);
    }

    $maxMb   = (int)Settings::get('max_file_upload_mb', '5');
    $maxSize = $maxMb * 1024 * 1024;

    if ($_FILES['logo']['size'] > $maxSize) {
        throw new AppException("El archivo no puede superar {$maxMb} MB.", 400);
    }

    $mime    = mime_content_type($_FILES['logo']['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        throw new AppException('Solo se permiten imágenes JPG, PNG o WebP.', 400);
    }

    $ext  = match ($mime) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
    $dir  = __DIR__ . '/../uploads/logos/' . $businessId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Eliminar logo anterior si existe
    $prev = $pdo->prepare('SELECT logo_path FROM businesses WHERE id = ?');
    $prev->execute([$businessId]);
    $prevPath = $prev->fetchColumn();
    if ($prevPath && file_exists(__DIR__ . '/../' . $prevPath)) {
        @unlink(__DIR__ . '/../' . $prevPath);
    }

    $filename = 'logo_' . $businessId . '_' . time() . '.' . $ext;
    $dest     = $dir . '/' . $filename;

    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
        throw new AppException('Error al guardar el archivo.', 500);
    }

    $relativePath = 'uploads/logos/' . $businessId . '/' . $filename;
    $pdo->prepare('UPDATE businesses SET logo_path = ? WHERE id = ?')->execute([$relativePath, $businessId]);
    $_SESSION['business_logo'] = $relativePath;

    echo json_encode(['success' => true, 'logo_path' => $relativePath]);
} catch (AppException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
