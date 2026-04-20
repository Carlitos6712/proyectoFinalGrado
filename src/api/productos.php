<?php
/**
 * API REST para gestión de productos del inventario.
 *
 * @package  Es21Plus\Api
 * @author   Carlos Vico
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
require_once __DIR__ . '/../includes/Producto.php';

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
    $modelo = new Producto();
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
 * @param Producto $modelo Instancia del modelo.
 * @return void
 */
function handleGet(Producto $modelo): void
{
    if (isset($_GET['stock_bajo'])) {
        jsonResponse(true, $modelo->filtrarStockBajo(), 'Productos con stock bajo.');
    }
    if (isset($_GET['buscar'])) {
        $categoriaId = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT) ?: null;
        $data = $modelo->buscar($_GET['buscar'], $categoriaId);
        jsonResponse(true, $data, 'Resultados de búsqueda.');
    }
    if (isset($_GET['id'])) {
        $id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $data = $modelo->obtener($id);
        jsonResponse(true, $data, 'Producto encontrado.');
    }
    jsonResponse(true, $modelo->listar(), 'Listado de productos.');
}

/**
 * Gestiona las peticiones POST (crear producto).
 *
 * @param Producto $modelo Instancia del modelo.
 * @return void
 */
function handlePost(Producto $modelo): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');
    $precio      = (float)  ($body['precio']      ?? 0);
    $categoriaId = isset($body['categoria_id']) ? (int)$body['categoria_id'] : null;
    $stock       = (int)    ($body['stock']        ?? 0);
    $stockMinimo = (int)    ($body['stock_minimo'] ?? 5);
    $codigoRef   = trim($body['codigo_ref'] ?? '') ?: null;

    if ($nombre === '') {
        throw new AppException('El nombre del producto es obligatorio.', 400);
    }

    $id = $modelo->crear($nombre, $descripcion, $precio, $categoriaId, $stock, $stockMinimo, $codigoRef);
    jsonResponse(true, ['id' => $id], 'Producto creado correctamente.', 201);
}

/**
 * Gestiona las peticiones PUT (actualizar producto).
 *
 * @param Producto $modelo Instancia del modelo.
 * @return void
 */
function handlePut(Producto $modelo): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id          = (int)    ($body['id']           ?? 0);
    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');
    $precio      = (float)  ($body['precio']      ?? 0);
    $categoriaId = isset($body['categoria_id']) ? (int)$body['categoria_id'] : null;
    $stockMinimo = (int)    ($body['stock_minimo'] ?? 5);
    $codigoRef   = trim($body['codigo_ref'] ?? '') ?: null;

    if (!$id || $nombre === '') {
        throw new AppException('id y nombre son obligatorios.', 400);
    }

    $ok = $modelo->actualizar($id, $nombre, $descripcion, $precio, $categoriaId, $stockMinimo, $codigoRef);
    jsonResponse($ok, null, $ok ? 'Producto actualizado.' : 'No se pudo actualizar.');
}

/**
 * Gestiona las peticiones DELETE (soft-delete).
 *
 * @param Producto $modelo Instancia del modelo.
 * @return void
 */
function handleDelete(Producto $modelo): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new AppException('Se requiere el parámetro id.', 400);
    }
    $ok = $modelo->eliminar($id);
    jsonResponse($ok, null, $ok ? 'Producto eliminado.' : 'Producto no encontrado.', $ok ? 200 : 404);
}
