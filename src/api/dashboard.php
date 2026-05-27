<?php
/**
 * API REST para datos analíticos del dashboard.
 *
 * Sirve bloques de datos independientes para el dashboard analítico.
 * Requiere sesión de usuario autenticado.
 *
 * Parámetros GET:
 *   ?seccion=top_ventas      → top productos más vendidos
 *   ?seccion=valor_categorias → valor del inventario por categoría
 *   ?seccion=rotacion        → rotación de stock
 *   ?seccion=stock_muerto    → productos sin movimiento
 *   (sin ?seccion)           → devuelve todos los bloques
 *
 * Parámetros adicionales:
 *   ?dias=N    → período de análisis en días (default: 30; stock_muerto: 90)
 *   ?limit=N   → top ventas: número de productos (default 10)
 *
 * @package  Es21Plus\Api
 * @author   Carlitos6712
 * @version  1.0.0
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auditoria.php';
require_once __DIR__ . '/../includes/AlertaStock.php';
require_once __DIR__ . '/../includes/Producto.php';
require_once __DIR__ . '/../includes/Movimiento.php';
require_once __DIR__ . '/../core/Session.php';

Session::start();

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'No autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

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

try {
    $productoModel  = new Producto();
    $movimientoModel = new Movimiento();

    $seccion = trim($_GET['seccion'] ?? '');
    $dias    = max(1, (int)($_GET['dias']  ?? 30));
    $limit   = max(1, min(50, (int)($_GET['limit'] ?? 10)));
    $umbral  = max(1, (int)($_GET['umbral'] ?? 90));

    if ($seccion !== '') {
        $data = obtenerSeccion($seccion, $productoModel, $movimientoModel, $dias, $limit, $umbral);
        jsonResponse(true, $data);
    }

    // Sin ?seccion → devolver todos los bloques
    $data = [
        'top_ventas'       => obtenerSeccion('top_ventas',       $productoModel, $movimientoModel, $dias, $limit, $umbral),
        'valor_categorias' => obtenerSeccion('valor_categorias',  $productoModel, $movimientoModel, $dias, $limit, $umbral),
        'rotacion'         => obtenerSeccion('rotacion',          $productoModel, $movimientoModel, $dias, $limit, $umbral),
        'stock_muerto'     => obtenerSeccion('stock_muerto',      $productoModel, $movimientoModel, $dias, $limit, $umbral),
    ];
    jsonResponse(true, $data);

} catch (AppException $e) {
    jsonResponse(false, null, $e->getMessage(), $e->getCode() ?: 400);
} catch (\Throwable $e) {
    error_log('api/dashboard.php: ' . $e->getMessage());
    jsonResponse(false, null, 'Error interno del servidor.', 500);
}

/**
 * Obtiene los datos de una sección específica del dashboard.
 *
 * @param string    $seccion         Identificador de la sección.
 * @param Producto  $productoModel   Instancia del modelo Producto.
 * @param Movimiento $movimientoModel Instancia del modelo Movimiento.
 * @param int       $dias            Período de análisis en días.
 * @param int       $limit           Límite para top ventas.
 * @param int       $umbral          Umbral de inactividad para stock muerto.
 * @throws AppException Si la sección no existe.
 * @return array<mixed>
 * @author Carlitos6712
 */
function obtenerSeccion(
    string $seccion,
    Producto $productoModel,
    Movimiento $movimientoModel,
    int $dias,
    int $limit,
    int $umbral
): array {
    return match ($seccion) {
        'top_ventas'       => $movimientoModel->topProductosVendidos($limit, $dias),
        'valor_categorias' => $productoModel->valorInventarioPorCategoria(),
        'rotacion'         => $productoModel->rotacionStock($dias),
        'stock_muerto'     => $movimientoModel->productosSinMovimiento($umbral),
        default            => throw new AppException("Sección '{$seccion}' no reconocida.", 400),
    };
}
