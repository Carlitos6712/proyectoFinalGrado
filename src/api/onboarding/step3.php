<?php
/**
 * Onboarding paso 3: Invita empleados y marca onboarding como completado.
 *
 * POST — JSON o form
 * Campos: employees[] = [{email, role}, ...]  (hasta 3)
 *
 * @package  Es21Plus\Api\Onboarding
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../core/Mailer.php';

if (empty($_SESSION['business_id']) || empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

$businessId   = (int)$_SESSION['business_id'];
$businessName = htmlspecialchars($_SESSION['business_name'] ?? '', ENT_QUOTES, 'UTF-8');

try {
    $pdo = Database::getInstance();

    $input     = json_decode(file_get_contents('php://input'), true);
    $employees = $input['employees'] ?? $_POST['employees'] ?? [];

    if (!is_array($employees)) {
        throw new AppException('Formato de empleados inválido.', 400);
    }

    $employees = array_slice($employees, 0, 3);

    $stmtEmp = $pdo->prepare(
        'INSERT INTO employees (business_id, name, email, password, role, position, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );

    foreach ($employees as $emp) {
        $eEmail    = filter_var(trim($emp['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $eName     = trim($emp['name'] ?? '');
        $eRole     = in_array($emp['role'] ?? '', ['admin', 'employee'], true) ? $emp['role'] : 'employee';
        $ePosition = substr(trim($emp['position'] ?? ''), 0, 100);

        if (!$eEmail || !$eName) continue;

        // Evitar duplicar email dentro del mismo negocio
        $dupCheck = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE email = ? AND business_id = ?');
        $dupCheck->execute([$eEmail, $businessId]);
        if ((int)$dupCheck->fetchColumn() > 0) continue;

        // Generar contraseña temporal
        $tempPwd = bin2hex(random_bytes(6)); // 12 chars hex
        $stmtEmp->execute([
            $businessId,
            $eName,
            $eEmail,
            password_hash($tempPwd, PASSWORD_BCRYPT, ['cost' => 12]),
            $eRole,
            $ePosition ?: null,
        ]);

        // Enviar invitación
        try {
            $safePosition = $ePosition ? " ({$ePosition})" : '';
            $subject = "Invitación a {$businessName} en es21plus";
            $body    = "
<h2>Hola, " . htmlspecialchars($eName, ENT_QUOTES, 'UTF-8') . ".</h2>
<p>Has sido añadido como <strong>" . ($eRole === 'admin' ? 'Administrador' : 'Empleado') . "{$safePosition}</strong> en <strong>{$businessName}</strong>.</p>
<p>Accede con las siguientes credenciales:</p>
<ul>
  <li><strong>Empresa:</strong> {$businessName}</li>
  <li><strong>Email:</strong> " . htmlspecialchars($eEmail, ENT_QUOTES, 'UTF-8') . "</li>
  <li><strong>Contraseña temporal:</strong> {$tempPwd}</li>
</ul>
<p>Te recomendamos cambiar tu contraseña tras el primer acceso.</p>
<p style='margin-top:1rem;color:#64748b;font-size:.9rem;'>Equipo es21plus</p>";
            Mailer::send($eEmail, $subject, $body);
        } catch (\Throwable) {
            // Fallos de email son no bloqueantes
        }
    }

    // Marcar onboarding como completado (no bloquea si la columna aún no existe)
    try {
        $pdo->prepare('UPDATE businesses SET onboarding_completed = 1 WHERE id = ?')->execute([$businessId]);
        $_SESSION['business_onboarding_completed'] = 1;
    } catch (\Throwable $upd) {
        error_log('[step3] onboarding_completed UPDATE failed: ' . $upd->getMessage());
    }

    echo json_encode(['success' => true, 'redirect' => '../index.php?welcome=1']);
} catch (AppException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
