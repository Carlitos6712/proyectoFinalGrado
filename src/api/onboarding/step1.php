<?php
/**
 * Onboarding paso 1: Guarda datos básicos de la empresa.
 *
 * POST — multipart/form-data
 * Campos: logo (file, opcional), name, phone, contact_email, address, description
 *
 * @package  Es21Plus\Api\Onboarding
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../core/Settings.php';

// Requiere sesión de empresa con rol admin
if (empty($_SESSION['business_id']) || empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

$businessId = (int)$_SESSION['business_id'];

try {
    $pdo     = Database::getInstance();
    $name         = trim($_POST['name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if (!$name) {
        throw new AppException('El nombre es obligatorio.', 400);
    }
    if ($contactEmail && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        throw new AppException('El email de contacto no es válido.', 400);
    }

    $logoPath = null;

    // Subida de logo (opcional)
    if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $maxMb   = (int)Settings::get('max_file_upload_mb', '5');
        $maxSize = $maxMb * 1024 * 1024;

        if ($_FILES['logo']['size'] > $maxSize) {
            throw new AppException("El logo no puede superar {$maxMb} MB.", 400);
        }

        $mime = mime_content_type($_FILES['logo']['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            throw new AppException('Solo se permiten imágenes JPG, PNG o WebP.', 400);
        }

        $ext     = match ($mime) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
        $dir     = __DIR__ . '/../../uploads/logos/' . $businessId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = 'logo_' . $businessId . '_' . time() . '.' . $ext;
        $dest     = $dir . '/' . $filename;

        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
            throw new AppException('Error al guardar el logo.', 500);
        }
        $logoPath = 'uploads/logos/' . $businessId . '/' . $filename;
    }

    $sql  = 'UPDATE businesses SET name = ?, phone = ?, contact_email = ?, address = ?, description = ?';
    $args = [$name, $phone ?: null, $contactEmail ?: null, $address ?: null, $description ?: null];

    if ($logoPath !== null) {
        $sql .= ', logo_path = ?';
        $args[] = $logoPath;
    }
    $sql .= ' WHERE id = ?';
    $args[] = $businessId;

    $pdo->prepare($sql)->execute($args);

    // Actualizar sesión con logo y nombre nuevos
    $_SESSION['business_name'] = $name;
    if ($logoPath !== null) {
        $_SESSION['business_logo'] = $logoPath;
    }

    echo json_encode(['success' => true]);
} catch (AppException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
