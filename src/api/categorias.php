<?php
/**
 * API REST para gestión de categorías del inventario.
 *
 * @package  Es21Plus\Api
 * @author   Carlitos6712
 * @author   miguelrechefdez
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
        case 'GET':
            handleGet($modelo);
            break;
        case 'POST':
            handlePost($modelo);
            break;
        case 'PUT':
            handlePut($modelo);
            break;
        case 'DELETE':
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
 * @param Categoria $modelo Instancia del modelo.
 * @return void
 */
function handleGet(Categoria $modelo): void
{
    if (isset($_GET['id'])) {
        $id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $data = $modelo->obtenerPorId($id);
        jsonResponse(true, $data, 'Categoría encontrada.');
    }
    jsonResponse(true, $modelo->listar(), 'Listado de categorías.');
}

/**
 * Gestiona las peticiones POST (crear categoría).
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
        throw new AppException('El nombre de la categoría es obligatorio.', 400);
    }
    $id = $modelo->crear($nombre, $descripcion);
    jsonResponse(true, ['id' => $id], 'Categoría creada correctamente.', 201);
}

/**
 * Gestiona las peticiones PUT (actualizar categoría).
 *
 * @param Categoria $modelo Instancia del modelo.
 * @return void
 */
function handlePut(Categoria $modelo): void
{
    $body        = json_decode(file_get_contents('php://input'), true) ?? [];
    $id          = (int) ($body['id']          ?? 0);
    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');

    if (!$id || $nombre === '') {
        throw new AppException('id y nombre son obligatorios.', 400);
    }
    $ok = $modelo->actualizar($id, $nombre, $descripcion);
    jsonResponse($ok, null, $ok ? 'Categoría actualizada.' : 'No se pudo actualizar.');
}

/**
 * Gestiona las peticiones DELETE.
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
    $ok = $modelo->eliminar($id);
    jsonResponse($ok, null, $ok ? 'Categoría eliminada.' : 'No se pudo eliminar.');
}
