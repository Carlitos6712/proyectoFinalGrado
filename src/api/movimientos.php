<?php
/**
 * API REST para gestión de movimientos de stock.
 *
 * @package  Es21Plus\Api
 * @author   Carlos Vico
 * @author   miguelrechefdez
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
        case 'GET':
            handleGet($modelo);
            break;
        case 'POST':
            handlePost($modelo);
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
        jsonResponse(true, $modelo->listarPorProducto($productoId), 'Movimientos del producto.');
    }

    $limite = filter_input(INPUT_GET, 'limite', FILTER_VALIDATE_INT) ?: 10;
    jsonResponse(true, $modelo->ultimosMovimientos($limite), 'Últimos movimientos.');
}

/**
 * Gestiona las peticiones POST (registrar movimiento).
 *
 * @param Movimiento $modelo Instancia del modelo.
 * @return void
 */
function handlePost(Movimiento $modelo): void
{
    $body          = json_decode(file_get_contents('php://input'), true) ?? [];
    $productoId    = (int)    ($body['producto_id']  ?? 0);
    $tipo          = trim($body['tipo']          ?? '');
    $cantidad      = (int)    ($body['cantidad']      ?? 0);
    $observaciones = trim($body['observaciones'] ?? '');

    if (!$productoId) {
        throw new AppException('producto_id es obligatorio.', 400);
    }

    $id = $modelo->registrar($productoId, $tipo, $cantidad, $observaciones);
    jsonResponse(true, ['id' => $id], 'Movimiento registrado correctamente.', 201);
}
