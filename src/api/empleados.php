<?php
/**
 * API REST para gestión de empleados de empresa.
 *
 * Todas las operaciones están aisladas al business_id de la sesión activa.
 * Acceso restringido a administradores de empresa.
 *
 * GET    /api/empleados.php           → listar empleados del negocio
 * POST   /api/empleados.php           → crear empleado
 * PUT    /api/empleados.php?id=X      → actualizar empleado
 * PATCH  /api/empleados.php?id=X&accion=toggle          → activar/desactivar
 * PATCH  /api/empleados.php?id=X&accion=reset-password  → resetear contraseña
 *
 * @package  Es21Plus\Api
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../middleware/RoleMiddleware.php';

header('Content-Type: application/json; charset=UTF-8');

/**
 * Emite JSON estándar y termina la ejecución.
 *
 * @param bool   $success
 * @param mixed  $data
 * @param string $message
 * @param int    $status
 */
function jsonOut(bool $success, mixed $data, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
    exit;
}

RoleMiddleware::requireAdmin();

$businessId = Session::getBusinessId();
if (!$businessId) {
    jsonOut(false, null, 'Sin contexto de empresa.', 403);
}

$pdo    = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$accion = $_GET['accion'] ?? '';

/**
 * Verifica que el empleado pertenece al negocio activo.
 *
 * @throws AppException Si no existe o no pertenece al negocio.
 */
function fetchEmployee(PDO $pdo, int $id, int $businessId): array
{
    $stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ? AND business_id = ?');
    $stmt->execute([$id, $businessId]);
    $row = $stmt->fetch();
    if (!$row) throw new AppException('Empleado no encontrado.', 404);
    return $row;
}

/**
 * Genera contraseña aleatoria de 12 caracteres.
 */
function generarPassword(): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$';
    $pwd   = '';
    for ($i = 0; $i < 12; $i++) {
        $pwd .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pwd;
}

try {
    switch ($method) {

        // ── GET: listar empleados ─────────────────────────────────────────────
        case 'GET':
            $stmt = $pdo->prepare(
                'SELECT id, name, email, role, is_active, last_login, created_at
                 FROM employees WHERE business_id = ? ORDER BY name ASC'
            );
            $stmt->execute([$businessId]);
            jsonOut(true, $stmt->fetchAll(), 'OK');

        // ── POST: crear empleado ──────────────────────────────────────────────
        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            $csrfToken = $body['csrf_token'] ?? '';
            if (!validateCsrfToken('gestionar_empleados', $csrfToken)) {
                jsonOut(false, null, 'Token CSRF inválido.', 403);
            }

            $name  = trim($body['name']  ?? '');
            $email = trim($body['email'] ?? '');
            $role  = $body['role']  ?? 'employee';
            $pwd   = $body['password'] ?? '';

            if (!$name)  throw new AppException('El nombre es obligatorio.', 422);
            if (!$email) throw new AppException('El email es obligatorio.', 422);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppException('Email inválido.', 422);
            if (!in_array($role, ['admin', 'employee'], true)) throw new AppException('Rol inválido.', 422);
            if (strlen($pwd) < 8) throw new AppException('La contraseña debe tener mínimo 8 caracteres.', 422);

            // Email único dentro de la empresa
            $dup = $pdo->prepare('SELECT id FROM employees WHERE email = ? AND business_id = ?');
            $dup->execute([$email, $businessId]);
            if ($dup->fetch()) throw new AppException('Ya existe un empleado con ese email.', 409);

            $stmt = $pdo->prepare(
                'INSERT INTO employees (business_id, name, email, password, role)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $businessId, $name, $email,
                password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]),
                $role,
            ]);
            jsonOut(true, ['id' => (int)$pdo->lastInsertId()], 'Empleado creado correctamente.', 201);

        // ── PUT: actualizar empleado ──────────────────────────────────────────
        case 'PUT':
            if (!$id) throw new AppException('ID requerido.', 400);
            fetchEmployee($pdo, $id, $businessId);

            $body  = json_decode(file_get_contents('php://input'), true) ?? [];

            $csrfToken = $body['csrf_token'] ?? '';
            if (!validateCsrfToken('gestionar_empleados', $csrfToken)) {
                jsonOut(false, null, 'Token CSRF inválido.', 403);
            }

            $name  = trim($body['name']  ?? '');
            $email = trim($body['email'] ?? '');
            $role  = $body['role'] ?? 'employee';

            if (!$name)  throw new AppException('El nombre es obligatorio.', 422);
            if (!$email) throw new AppException('El email es obligatorio.', 422);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppException('Email inválido.', 422);
            if (!in_array($role, ['admin', 'employee'], true)) throw new AppException('Rol inválido.', 422);

            // Email único dentro de la empresa (excluyendo este mismo)
            $dup = $pdo->prepare('SELECT id FROM employees WHERE email = ? AND business_id = ? AND id != ?');
            $dup->execute([$email, $businessId, $id]);
            if ($dup->fetch()) throw new AppException('Ya existe un empleado con ese email.', 409);

            $pdo->prepare('UPDATE employees SET name = ?, email = ?, role = ? WHERE id = ? AND business_id = ?')
                ->execute([$name, $email, $role, $id, $businessId]);

            jsonOut(true, null, 'Empleado actualizado correctamente.');

        // ── PATCH: toggle activo / reset password ─────────────────────────────
        case 'PATCH':
            if (!$id) throw new AppException('ID requerido.', 400);

            $body      = json_decode(file_get_contents('php://input'), true) ?? [];
            $csrfToken = $body['csrf_token'] ?? '';
            if (!validateCsrfToken('gestionar_empleados', $csrfToken)) {
                jsonOut(false, null, 'Token CSRF inválido.', 403);
            }

            fetchEmployee($pdo, $id, $businessId);

            if ($accion === 'toggle') {
                $stmt = $pdo->prepare('SELECT is_active FROM employees WHERE id = ?');
                $stmt->execute([$id]);
                $current = (int)$stmt->fetchColumn();
                $nuevo   = $current === 1 ? 0 : 1;

                // No desactivar al único admin activo
                if ($current === 1) {
                    $cntAdmin = $pdo->prepare(
                        'SELECT COUNT(*) FROM employees WHERE business_id = ? AND role = ? AND is_active = 1'
                    );
                    $cntAdmin->execute([$businessId, 'admin']);
                    $stmt2 = $pdo->prepare('SELECT role FROM employees WHERE id = ?');
                    $stmt2->execute([$id]);
                    $rol = $stmt2->fetchColumn();
                    if ($rol === 'admin' && (int)$cntAdmin->fetchColumn() <= 1) {
                        throw new AppException('No se puede desactivar al único administrador activo.', 409);
                    }
                }

                $pdo->prepare('UPDATE employees SET is_active = ? WHERE id = ?')->execute([$nuevo, $id]);
                $msg = $nuevo ? 'Empleado activado.' : 'Empleado desactivado.';
                jsonOut(true, ['is_active' => (bool)$nuevo], $msg);
            }

            if ($accion === 'reset-password') {
                $plain = generarPassword();
                $pdo->prepare('UPDATE employees SET password = ? WHERE id = ?')
                    ->execute([password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
                jsonOut(true, ['password' => $plain], 'Contraseña reseteada correctamente.');
            }

            throw new AppException('Acción no reconocida.', 400);

        default:
            throw new AppException('Método no permitido.', 405);
    }
} catch (AppException $e) {
    jsonOut(false, null, $e->getMessage(), $e->getCode() ?: 400);
} catch (\Throwable $e) {
    jsonOut(false, null, 'Error interno del servidor.', 500);
}
