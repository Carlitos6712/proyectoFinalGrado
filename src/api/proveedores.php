<?php
/**
 * API REST para gestión de proveedores del inventario.
 *
 * Verbos:
 *   GET    ?id=X  → obtener uno (404 si no existe)
 *   GET           → listado (activos + inactivos)
 *   POST          → crear (solo admin, 400 si nombre vacío)
 *   PUT    ?id=X  → actualizar (solo admin, 400/404 si errores)
 *   PATCH  ?id=X&accion=toggle → desactivar/reactivar (solo admin)
 *   DELETE        → 405 (no permitido)
 *
 * @package  Es21Plus\Api
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/Auditoria.php';
require_once __DIR__ . '/../includes/Proveedor.php';

/**
 * Envía respuesta JSON estándar y finaliza la ejecución.
 *
 * @param bool   $success Indica éxito o fallo.
 * @param mixed  $data    Datos a retornar.
 * @param string $message Mensaje descriptivo.
 * @param int    $code    Código HTTP de respuesta.
 * @return void
 * @author Carlitos6712
 */
function jsonResponse(bool $success, mixed $data, string $message = '', int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verifica que el usuario sea admin; si no, responde 403.
 *
 * @return void
 * @author Carlitos6712
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        jsonResponse(false, null, 'Acceso restringido a administradores.', 403);
    }
}

// Verificar autenticación básica
if (empty($_SESSION['usuario_id']) && empty($_SESSION['user_id'])) {
    jsonResponse(false, null, 'No autenticado.', 401);
}

try {
    $modelo = new Proveedor();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':    handleGet($modelo);    break;
        case 'POST':   requireAdmin(); handlePost($modelo);   break;
        case 'PUT':    requireAdmin(); handlePut($modelo);    break;
        case 'PATCH':  requireAdmin(); handlePatch($modelo);  break;
        case 'DELETE':
            jsonResponse(false, null, 'La eliminación física no está permitida. Use PATCH para desactivar.', 405);
            break;
        default:
            jsonResponse(false, null, 'Método HTTP no permitido.', 405);
    }
} catch (AppException $e) {
    jsonResponse(false, null, $e->getMessage(), $e->getCode() ?: 400);
} catch (\Throwable $e) {
    jsonResponse(false, null, 'Error interno del servidor.', 500);
}

/**
 * Gestiona las peticiones GET.
 *
 * ?id=X → obtener proveedor por ID (404 si no existe)
 * default → listado activos + inactivos
 *
 * @param Proveedor $modelo Instancia del modelo.
 * @return void
 * @author Carlitos6712
 */
function handleGet(Proveedor $modelo): void
{
    if (isset($_GET['id'])) {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new AppException('El parámetro id debe ser un entero válido.', 400);
        }
        jsonResponse(true, $modelo->obtener($id), 'Proveedor encontrado.');
    }
    jsonResponse(true, $modelo->listar(false), 'Listado de proveedores.');
}

/**
 * Gestiona las peticiones POST (crear proveedor).
 *
 * @param Proveedor $modelo Instancia del modelo.
 * @return void
 * @author Carlitos6712
 */
function handlePost(Proveedor $modelo): void
{
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrfToken = $body['csrf_token'] ?? '';
    if (!validateCsrfToken('gestionar_proveedores', $csrfToken)) {
        jsonResponse(false, null, 'Token CSRF inválido.', 403);
    }
    if (trim($body['nombre'] ?? '') === '') {
        jsonResponse(false, ['errors' => ['nombre' => 'El nombre del proveedor es obligatorio.']], 'Errores de validación.', 400);
    }
    $id = $modelo->crear($body);
    jsonResponse(true, ['id' => $id], 'Proveedor creado correctamente.', 201);
}

/**
 * Gestiona las peticiones PUT (actualizar proveedor).
 *
 * @param Proveedor $modelo Instancia del modelo.
 * @return void
 * @author Carlitos6712
 */
function handlePut(Proveedor $modelo): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new AppException('Se requiere el parámetro id.', 400);
    }
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrfToken = $body['csrf_token'] ?? '';
    if (!validateCsrfToken('gestionar_proveedores', $csrfToken)) {
        jsonResponse(false, null, 'Token CSRF inválido.', 403);
    }
    $modelo->obtener($id);
    $ok = $modelo->actualizar($id, $body);
    jsonResponse($ok, null, $ok ? 'Proveedor actualizado.' : 'No se pudo actualizar.');
}

/**
 * Gestiona las peticiones PATCH (toggle activo/inactivo).
 *
 * ?id=X&accion=toggle → desactiva si activo, reactiva si inactivo.
 *
 * @param Proveedor $modelo Instancia del modelo.
 * @return void
 * @author Carlitos6712
 */
function handlePatch(Proveedor $modelo): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new AppException('Se requiere el parámetro id.', 400);
    }
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrfToken = $body['csrf_token'] ?? '';
    if (!validateCsrfToken('gestionar_proveedores', $csrfToken)) {
        jsonResponse(false, null, 'Token CSRF inválido.', 403);
    }
    $prov = $modelo->obtener($id);
    if ((int)$prov['activo'] === 1) {
        $modelo->desactivar($id);
        jsonResponse(true, null, 'Proveedor desactivado.');
    } else {
        // Reactivar: actualizar solo el campo activo
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("UPDATE proveedores SET activo = 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        jsonResponse(true, null, 'Proveedor reactivado.');
    }
}
