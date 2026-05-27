<?php
/**
 * API REST para gestión de modelos de moto y sus compatibilidades.
 *
 * Verbos:
 *   GET           → listado de modelos de moto
 *   GET    ?id=X  → obtener uno (404 si no existe)
 *   POST          → crear (400 si validación falla) — solo admin
 *   DELETE ?id=X  → eliminar (404 si no existe, 409 si tiene compatibilidades) — solo admin
 *
 * @package  Es21Plus\Api
 * @author   Carlitos6712
 * @version  1.0.0
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/ModeloMoto.php';
require_once __DIR__ . '/../includes/auth_check.php';

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
 * Verifica que el usuario esté autenticado y tenga el rol requerido.
 *
 * @param string $rolRequerido Rol mínimo requerido ('admin' para escritura).
 * @return void
 * @author Carlitos6712
 */
function requireAuth(string $rolRequerido = 'any'): void
{
    if (empty($_SESSION['usuario_id'])) {
        jsonResponse(false, null, 'No autenticado.', 401);
    }
    if ($rolRequerido === 'admin' && !isAdmin()) {
        jsonResponse(false, null, 'Acceso denegado. Se requiere rol de administrador.', 403);
    }
}

try {
    $modelo = new ModeloMoto();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            requireAuth();
            handleGet($modelo);
            break;
        case 'POST':
            requireAuth('admin');
            handlePost($modelo);
            break;
        case 'DELETE':
            requireAuth('admin');
            handleDelete($modelo);
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
 * ?id=X → obtener modelo por ID (404 si no existe)
 * default → listado de modelos
 *
 * @param ModeloMoto $modelo Instancia del modelo.
 * @return void
 * @author Carlitos6712
 */
function handleGet(ModeloMoto $modelo): void
{
    if (isset($_GET['id'])) {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new AppException('El parámetro id debe ser un entero válido.', 400);
        }
        jsonResponse(true, $modelo->obtenerPorId($id), 'Modelo de moto encontrado.');
    }
    jsonResponse(true, $modelo->listar(), 'Listado de modelos de moto.');
}

/**
 * Gestiona las peticiones POST (crear modelo de moto).
 * Solo accesible para administradores.
 *
 * @param ModeloMoto $modelo Instancia del modelo.
 * @return void
 * @author Carlitos6712
 */
function handlePost(ModeloMoto $modelo): void
{
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $marca     = trim($body['marca']      ?? '');
    $modeloStr = trim($body['modelo']     ?? '');
    $anioDes   = isset($body['anio_desde']) ? (int)$body['anio_desde'] : 0;
    $anioHasta = isset($body['anio_hasta']) && $body['anio_hasta'] !== null && $body['anio_hasta'] !== ''
        ? (int)$body['anio_hasta']
        : null;

    if ($marca === '' || $modeloStr === '' || $anioDes === 0) {
        jsonResponse(false, ['errors' => [
            'marca'      => $marca === '' ? 'La marca es obligatoria.' : null,
            'modelo'     => $modeloStr === '' ? 'El modelo es obligatorio.' : null,
            'anio_desde' => $anioDes === 0 ? 'El año de inicio es obligatorio.' : null,
        ]], 'Errores de validación.', 422);
    }

    $id = $modelo->crear($marca, $modeloStr, $anioDes, $anioHasta);
    jsonResponse(true, ['id' => $id], 'Modelo de moto creado correctamente.', 201);
}

/**
 * Gestiona las peticiones DELETE.
 * Devuelve 404 si el modelo no existe.
 * Devuelve 409 si tiene productos compatibles (lanzado por el modelo).
 * Solo accesible para administradores.
 *
 * @param ModeloMoto $modelo Instancia del modelo.
 * @return void
 * @author Carlitos6712
 */
function handleDelete(ModeloMoto $modelo): void
{
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrfToken = $body['csrf_token'] ?? '';
    if (!validateCsrfToken('gestionar_modelos', $csrfToken)) {
        jsonResponse(false, null, 'Token CSRF inválido.', 403);
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new AppException('Se requiere el parámetro id.', 400);
    }
    $modelo->obtenerPorId($id);
    $ok = $modelo->eliminar($id);
    jsonResponse($ok, null, $ok ? 'Modelo de moto eliminado.' : 'No se pudo eliminar.');
}
