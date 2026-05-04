<?php
/**
 * API REST para gestión de movimientos de stock.
 *
 * Verbos:
 *   GET ?grafico[&dias=N]           → estadísticas agrupadas por día
 *   GET ?producto_id=X&resumen      → resumen de stock del producto
 *   GET ?producto_id=X[&page=&limit=] → historial paginado del producto
 *   GET [?limite=N]                 → últimos N movimientos globales
 *   POST                            → registrar movimiento
 *                                     (422 si salida deja stock negativo,
 *                                      404 si producto no existe)
 *
 * @package  Es21Plus\Api
 * @author   Carlitos6712
 * @version  1.0.0
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Producto.php';
require_once __DIR__ . '/../includes/Movimiento.php';

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
    $modelo = new Movimiento();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':  handleGet($modelo);  break;
        case 'POST': handlePost($modelo); break;
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
 * Soporta ?grafico, ?resumen, listado paginado por producto_id
 * y últimos movimientos globales.
 *
 * @param Movimiento $modelo Instancia del modelo.
 * @return void
 */
function handleGet(Movimiento $modelo): void
{
    if (isset($_GET['grafico'])) {
        $dias = filter_input(INPUT_GET, 'dias', FILTER_VALIDATE_INT) ?: 7;
        jsonResponse(true, $modelo->estadisticasPorDia($dias), 'Estadísticas por día.');
    }

    $productoId = filter_input(INPUT_GET, 'producto_id', FILTER_VALIDATE_INT);

    if ($productoId && isset($_GET['resumen'])) {
        jsonResponse(true, $modelo->resumenStock($productoId), 'Resumen de stock.');
    }

    if ($productoId) {
        $page  = max(1, (int)(filter_input(INPUT_GET, 'page',  FILTER_VALIDATE_INT) ?: 1));
        $limit = max(1, min(100, (int)(filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 20)));
        $total = $modelo->contarPorProducto($productoId);
        $items = $modelo->listarPorProductoPaginado($productoId, $page, $limit);
        jsonResponse(true, [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil($total / max(1, $limit)),
        ], 'Movimientos del producto.');
    }

    $limite = filter_input(INPUT_GET, 'limite', FILTER_VALIDATE_INT) ?: 10;
    jsonResponse(true, $modelo->ultimosMovimientos($limite), 'Últimos movimientos.');
}

/**
 * Gestiona las peticiones POST (registrar movimiento).
 *
 * Verifica que el producto existe (404 si no).
 * Para salidas, valida que no deje stock negativo antes de persistir (422).
 *
 * @param Movimiento $modelo Instancia del modelo.
 * @return void
 */
function handlePost(Movimiento $modelo): void
{
    $body          = json_decode(file_get_contents('php://input'), true) ?? [];
    $productoId    = (int)($body['producto_id']  ?? 0);
    $tipo          = trim($body['tipo']          ?? '');
    $cantidad      = (int)($body['cantidad']      ?? 0);
    $observaciones = trim($body['observaciones'] ?? '');

    if (!$productoId) {
        throw new AppException('producto_id es obligatorio.', 400);
    }

    $productoModel = new Producto();
    $producto      = $productoModel->obtener($productoId);

    if ($tipo === 'salida' && (int)$producto['stock'] < $cantidad) {
        jsonResponse(
            false,
            ['stock_actual' => (int)$producto['stock']],
            "Stock insuficiente. Stock actual: {$producto['stock']}.",
            422
        );
    }

    $id = $modelo->registrar($productoId, $tipo, $cantidad, $observaciones);
    jsonResponse(true, ['id' => $id], 'Movimiento registrado correctamente.', 201);
}
