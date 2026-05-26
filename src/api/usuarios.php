<?php
/**
 * API REST para gestión de usuarios.
 *
 * Endpoints:
 *   GET    /api/usuarios.php          → listar todos los usuarios
 *   GET    /api/usuarios.php?id=X     → obtener un usuario por ID
 *   POST   /api/usuarios.php          → crear nuevo usuario
 *   PUT    /api/usuarios.php?id=X     → actualizar datos de usuario
 *   PATCH  /api/usuarios.php?id=X&accion=toggle → activar/desactivar usuario
 *
 * Acceso restringido: solo usuarios con rol 'admin'.
 * Nunca se expone password_hash en ninguna respuesta.
 *
 * @package  Es21Plus\Api
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Usuario.php';
require_once __DIR__ . '/../includes/csrf.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Emite una respuesta JSON estándar y termina la ejecución.
 *
 * @param bool   $success
 * @param mixed  $data
 * @param string $message
 * @param int    $status  Código HTTP.
 * @return never
 * @author Carlitos6712
 */
function jsonResponse(bool $success, mixed $data, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verifica que el usuario esté autenticado y tenga rol admin o superadmin.
 *
 * @throws never — llama a jsonResponse() directamente.
 * @return void
 * @author Carlitos6712
 */
function requireAdmin(): void
{
    // Sesión local (login directo)
    $isLocalUser    = !empty($_SESSION['usuario_id']);
    // Sesión multi-tenant (login de empresa)
    $isBusinessUser = !empty($_SESSION['user_id']) && !empty($_SESSION['business_id']);

    if (!$isLocalUser && !$isBusinessUser) {
        jsonResponse(false, null, 'No autenticado.', 401);
    }

    $rol = $_SESSION['user_role'] ?? $_SESSION['rol'] ?? '';
    if (!in_array($rol, ['admin', 'superadmin'], true)) {
        jsonResponse(false, null, 'Acceso denegado: se requiere rol admin.', 403);
    }
}

// ── Control de acceso ─────────────────────────────────────────────────────────

requireAdmin();

$model  = new Usuario();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$accion = $_GET['accion'] ?? '';

// ── Router ────────────────────────────────────────────────────────────────────

try {
    switch ($method) {
        // ── GET: listar o detalle ─────────────────────────────────────────────
        case 'GET':
            if ($id !== null) {
                $usuario = $model->obtenerPorId($id);
                jsonResponse(true, $usuario, 'Usuario obtenido.');
            }
            $usuarios = $model->listar();
            jsonResponse(true, $usuarios, 'Usuarios listados.');

        // ── POST: crear usuario ───────────────────────────────────────────────
        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $csrfToken = $body['csrf_token'] ?? '';
            if (!validateCsrfToken('gestionar_usuarios', $csrfToken)) {
                jsonResponse(false, null, 'Token CSRF inválido.', 403);
            }

            $username        = trim($body['username']        ?? '');
            $password        = $body['password']             ?? '';
            $nombreCompleto  = trim($body['nombre_completo'] ?? '');
            $email           = trim($body['email']           ?? '');
            $rol             = $body['rol']                  ?? 'employee';

            if ($username === '' || $password === '' || $nombreCompleto === '') {
                jsonResponse(false, null, 'username, password y nombre_completo son obligatorios.', 400);
            }

            $nuevoId = $model->crear($username, $password, $nombreCompleto, $email, $rol);
            $nuevo   = $model->obtenerPorId($nuevoId);
            jsonResponse(true, $nuevo, 'Usuario creado correctamente.', 201);

        // ── PUT: actualizar datos (sin contraseña) ────────────────────────────
        case 'PUT':
            if ($id === null) {
                jsonResponse(false, null, 'Parámetro id requerido.', 400);
            }

            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $csrfToken = $body['csrf_token'] ?? '';
            if (!validateCsrfToken('gestionar_usuarios', $csrfToken)) {
                jsonResponse(false, null, 'Token CSRF inválido.', 403);
            }

            $username       = trim($body['username']        ?? '');
            $nombreCompleto = trim($body['nombre_completo'] ?? '');
            $email          = trim($body['email']           ?? '');
            $rol            = $body['rol']                  ?? 'employee';

            if ($username === '' || $nombreCompleto === '') {
                jsonResponse(false, null, 'username y nombre_completo son obligatorios.', 400);
            }

            $model->actualizar($id, $username, $nombreCompleto, $email, $rol);
            $actualizado = $model->obtenerPorId($id);
            jsonResponse(true, $actualizado, 'Usuario actualizado correctamente.');

        // ── PATCH: acciones sobre usuario ────────────────────────────────────
        case 'PATCH':
            if ($id === null) {
                jsonResponse(false, null, 'Parámetro id requerido.', 400);
            }

            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $csrfToken = $body['csrf_token'] ?? '';
            if (!validateCsrfToken('gestionar_usuarios', $csrfToken)) {
                jsonResponse(false, null, 'Token CSRF inválido.', 403);
            }

            if ($accion === 'toggle') {
                // Bloquear auto-desactivación
                if ($id === (int)($_SESSION['usuario_id'] ?? 0)) {
                    jsonResponse(false, null, 'No puedes desactivarte a ti mismo.', 403);
                }
                $nuevoEstado = $model->toggleActivo($id);
                $estadoTexto = $nuevoEstado ? 'activado' : 'desactivado';
                jsonResponse(true, ['activo' => $nuevoEstado], "Usuario {$estadoTexto} correctamente.");
            }

            if ($accion === 'reset-password') {
                $nuevaPassword = $model->resetPassword($id);
                jsonResponse(true, ['password' => $nuevaPassword], 'Contraseña reseteada. Muéstrala al usuario y pídele que la cambie.');
            }

            jsonResponse(false, null, "Acción desconocida: '{$accion}'.", 400);

        // ── Método no permitido ───────────────────────────────────────────────
        default:
            jsonResponse(false, null, 'Método HTTP no permitido.', 405);
    }
} catch (AppException $e) {
    jsonResponse(false, null, $e->getMessage(), $e->getCode() ?: 500);
} catch (\Throwable $e) {
    jsonResponse(false, null, 'Error interno del servidor.', 500);
}
