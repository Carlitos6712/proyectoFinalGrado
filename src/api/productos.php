<?php
/**
 * API REST para gestión de productos del inventario.
 *
 * Verbos:
 *   GET    ?id=X               → obtener uno (404 si no existe)
 *   GET    ?stock_bajo         → listado de stock bajo
 *   GET    ?page=&limit=&...   → listado paginado con filtros combinables
 *   POST                       → crear (422 si validación falla)
 *   PUT                        → actualizar (422 validación, 404 si no existe)
 *   DELETE ?id=X               → soft-delete (404 si no existe)
 *
 * Filtros del listado (anticipan contrato Fase 4):
 *   q, categoria_id, precio_min, precio_max,
 *   stock_min, stock_max, stock_bajo, orden
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
 * ?id=X       → obtener un producto por ID (404 si no existe)
 * ?stock_bajo → listado de productos con stock bajo
 * default     → listado paginado con filtros combinables:
 *               page, limit, q, categoria_id, precio_min, precio_max,
 *               stock_min, stock_max, orden
 *
 * @param Producto $modelo Instancia del modelo.
 * @return void
 */
function handleGet(Producto $modelo): void
{
    if (isset($_GET['stock_bajo'])) {
        jsonResponse(true, $modelo->filtrarStockBajo(), 'Productos con stock bajo.');
    }

    if (isset($_GET['id'])) {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new AppException('El parámetro id debe ser un entero válido.', 400);
        }
        jsonResponse(true, $modelo->obtener($id), 'Producto encontrado.');
    }

    $page   = max(1, (int)(filter_input(INPUT_GET, 'page',  FILTER_VALIDATE_INT) ?: 1));
    $limit  = max(1, min(100, (int)(filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 20)));

    $q           = trim($_GET['q'] ?? '');
    $categoriaId = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT) ?: null;
    $precioMin   = isset($_GET['precio_min']) && $_GET['precio_min'] !== '' ? (float)$_GET['precio_min'] : null;
    $precioMax   = isset($_GET['precio_max']) && $_GET['precio_max'] !== '' ? (float)$_GET['precio_max'] : null;
    $stockMin    = isset($_GET['stock_min'])  && $_GET['stock_min']  !== '' ? (int)$_GET['stock_min']    : null;
    $stockMax    = isset($_GET['stock_max'])  && $_GET['stock_max']  !== '' ? (int)$_GET['stock_max']    : null;
    $orden       = $_GET['orden'] ?? 'nombre_asc';

    $total = $modelo->contarFiltrados($q ?: null, $categoriaId, $precioMin, $precioMax, $stockMin, $stockMax);
    $items = $modelo->listarPaginado($page, $limit, $q ?: null, $categoriaId, $precioMin, $precioMax, $stockMin, $stockMax, $orden);

    jsonResponse(true, [
        'items'       => $items,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => (int) ceil($total / max(1, $limit)),
    ], 'Listado de productos.');
}

/**
 * Gestiona las peticiones POST (crear producto).
 * Devuelve 422 con detalle de campos si la validación falla.
 *
 * @param Producto $modelo Instancia del modelo.
 * @return void
 */
function handlePost(Producto $modelo): void
{
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = [];

    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');
    $precio      = isset($body['precio'])       ? (float)$body['precio']       : null;
    $categoriaId = isset($body['categoria_id']) ? (int)$body['categoria_id']   : null;
    $stock       = (int)($body['stock']        ?? 0);
    $stockMinimo = (int)($body['stock_minimo'] ?? 5);
    $codigoRef   = trim($body['codigo_ref']   ?? '') ?: null;

    if ($nombre === '') {
        $errors['nombre'] = 'El nombre del producto es obligatorio.';
    }
    if ($precio === null || $precio <= 0) {
        $errors['precio'] = 'El precio debe ser mayor que cero.';
    }
    if ($stock < 0) {
        $errors['stock'] = 'El stock no puede ser negativo.';
    }
    if (!empty($errors)) {
        jsonResponse(false, ['errors' => $errors], 'Errores de validación.', 422);
    }

    $id = $modelo->crear($nombre, $descripcion, $precio, $categoriaId, $stock, $stockMinimo, $codigoRef);
    jsonResponse(true, ['id' => $id], 'Producto creado correctamente.', 201);
}

/**
 * Gestiona las peticiones PUT (actualizar producto).
 * Devuelve 422 si la validación falla, 404 si el producto no existe.
 *
 * @param Producto $modelo Instancia del modelo.
 * @return void
 */
function handlePut(Producto $modelo): void
{
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = [];

    $id          = (int)($body['id']           ?? 0);
    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');
    $precio      = isset($body['precio'])       ? (float)$body['precio']       : null;
    $categoriaId = isset($body['categoria_id']) ? (int)$body['categoria_id']   : null;
    $stockMinimo = (int)($body['stock_minimo'] ?? 5);
    $codigoRef   = trim($body['codigo_ref']   ?? '') ?: null;

    if (!$id) {
        $errors['id'] = 'El campo id es obligatorio.';
    }
    if ($nombre === '') {
        $errors['nombre'] = 'El nombre del producto es obligatorio.';
    }
    if ($precio === null || $precio <= 0) {
        $errors['precio'] = 'El precio debe ser mayor que cero.';
    }
    if (!empty($errors)) {
        jsonResponse(false, ['errors' => $errors], 'Errores de validación.', 422);
    }

    $modelo->obtener($id);
    $ok = $modelo->actualizar($id, $nombre, $descripcion, $precio, $categoriaId, $stockMinimo, $codigoRef);
    $data = $ok ? $modelo->obtener($id) : null;
    jsonResponse($ok, $data, $ok ? 'Producto actualizado.' : 'No se pudo actualizar.');
}

/**
 * Gestiona las peticiones DELETE (soft-delete).
 * Devuelve 404 si el producto no existe o ya está eliminado.
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
    $modelo->obtener($id);
    $ok = $modelo->eliminar($id);
    jsonResponse($ok, null, $ok ? 'Producto eliminado.' : 'No se pudo eliminar.');
}
