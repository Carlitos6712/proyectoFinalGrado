<?php
/**
 * API REST para gestión de categorías del inventario.
 *
 * Verbos:
 *   GET    ?id=X  → obtener una (404 si no existe)
 *   GET           → listado con contador de productos activos por categoría
 *   POST          → crear (422 si validación falla)
 *   PUT           → actualizar (422 validación, 404 si no existe)
 *   DELETE ?id=X  → eliminar (404 si no existe, 409 si tiene productos activos)
 *
 * @package  Es21Plus\Api
 * @author   Carlitos6712
 * @version  1.0.0
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Categoria.php';

/**
 * Envía respuesta JSON estándar y finaliza la ejecución.
 *
 * @param bool   $success Indica éxito o fallo.
 * @param mixed  $data    Datos a retornar.
 * @param string $message Mensaje descriptivo.
 * @param int    $code    Código HTTP de respuesta.
 * @return void
 */
function jsonResponse(bool $success, mixed $data, string $message = '', int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $modelo = new Categoria();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':    handleGet($modelo);    break;
        case 'POST':   handlePost($modelo);   break;
        case 'PUT':    handlePut($modelo);    break;
        case 'DELETE': handleDelete($modelo); break;
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
 * ?id=X → obtener categoría por ID (404 si no existe)
 * default → listado con contador de productos activos
 *
 * @param Categoria $modelo Instancia del modelo.
 * @return void
 */
function handleGet(Categoria $modelo): void
{
    if (isset($_GET['id'])) {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new AppException('El parámetro id debe ser un entero válido.', 400);
        }
        jsonResponse(true, $modelo->obtenerPorId($id), 'Categoría encontrada.');
    }
    jsonResponse(true, $modelo->listar(), 'Listado de categorías.');
}

/**
 * Gestiona las peticiones POST (crear categoría).
 * Devuelve 422 con detalle de campos si la validación falla.
 *
 * @param Categoria $modelo Instancia del modelo.
 * @return void
 */
function handlePost(Categoria $modelo): void
{
    $body        = json_decode(file_get_contents('php://input'), true) ?? [];
    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');

    if ($nombre === '') {
        jsonResponse(false, ['errors' => ['nombre' => 'El nombre de la categoría es obligatorio.']], 'Errores de validación.', 422);
    }

    $id = $modelo->crear($nombre, $descripcion);
    jsonResponse(true, ['id' => $id], 'Categoría creada correctamente.', 201);
}

/**
 * Gestiona las peticiones PUT (actualizar categoría).
 * Devuelve 422 si la validación falla, 404 si la categoría no existe.
 *
 * @param Categoria $modelo Instancia del modelo.
 * @return void
 */
function handlePut(Categoria $modelo): void
{
    $body        = json_decode(file_get_contents('php://input'), true) ?? [];
    $id          = (int)($body['id']          ?? 0);
    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');

    if (!$id || $nombre === '') {
        $errors = [];
        if (!$id)        $errors['id']     = 'El campo id es obligatorio.';
        if ($nombre === '') $errors['nombre'] = 'El nombre de la categoría es obligatorio.';
        jsonResponse(false, ['errors' => $errors], 'Errores de validación.', 422);
    }

    $modelo->obtenerPorId($id);
    $ok = $modelo->actualizar($id, $nombre, $descripcion);
    jsonResponse($ok, null, $ok ? 'Categoría actualizada.' : 'No se pudo actualizar.');
}

/**
 * Gestiona las peticiones DELETE.
 * Devuelve 404 si la categoría no existe.
 * Devuelve 409 si tiene productos activos (lanzado por el modelo).
 *
 * @param Categoria $modelo Instancia del modelo.
 * @return void
 */
function handleDelete(Categoria $modelo): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new AppException('Se requiere el parámetro id.', 400);
    }
    $modelo->obtenerPorId($id);
    $ok = $modelo->eliminar($id);
    jsonResponse($ok, null, $ok ? 'Categoría eliminada.' : 'No se pudo eliminar.');
}
