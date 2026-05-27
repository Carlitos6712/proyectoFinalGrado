<?php
/**
 * API REST para gestión de kits / packs de productos.
 *
 * Verbos:
 *   GET                        → listar kits activos (con líneas si ?con_lineas=1)
 *   GET ?id=X                  → obtener kit + líneas
 *   GET ?id=X&lineas           → obtener solo líneas del kit
 *   POST                       → crear kit (body JSON: {nombre, descripcion, lineas:[{producto_id,cantidad}]})
 *   PUT  ?id=X                 → actualizar kit completo + líneas
 *   PATCH ?id=X&toggle         → activar/desactivar kit
 *
 * @package  Es21Plus\Api
 * @author   Carlitos6712
 * @version  1.0.0
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auditoria.php';
require_once __DIR__ . '/../includes/Kit.php';

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
    $modelo = new Kit();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':   handleGet($modelo);   break;
        case 'POST':  handlePost($modelo);  break;
        case 'PUT':   handlePut($modelo);   break;
        case 'PATCH': handlePatch($modelo); break;
        default:
            jsonResponse(false, null, 'Método HTTP no permitido.', 405);
    }
} catch (AppException $e) {
    jsonResponse(false, null, $e->getMessage(), $e->getCode() ?: 400);
} catch (\Throwable $e) {
    error_log('kits.php: ' . $e->getMessage());
    jsonResponse(false, null, 'Error interno del servidor.', 500);
}

/**
 * Maneja peticiones GET.
 *
 * @param Kit $modelo Instancia del modelo Kit.
 * @return void
 * @author Carlitos6712
 */
function handleGet(Kit $modelo): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        // Solo líneas
        if (isset($_GET['lineas'])) {
            jsonResponse(true, $modelo->getLineas($id));
        }
        // Kit completo + líneas
        $kit           = $modelo->obtener($id);
        $kit['lineas'] = $modelo->getLineas($id);
        jsonResponse(true, $kit);
    }

    // Listar (con lineas si se pide)
    $soloActivos = !isset($_GET['todos']);
    $kits        = $modelo->listar($soloActivos);

    if (isset($_GET['con_lineas'])) {
        foreach ($kits as &$k) {
            $k['lineas'] = $modelo->getLineas((int)$k['id']);
        }
        unset($k);
    }

    jsonResponse(true, $kits);
}

/**
 * Maneja peticiones POST (crear kit).
 *
 * @param Kit $modelo Instancia del modelo Kit.
 * @return void
 * @author Carlitos6712
 */
function handlePost(Kit $modelo): void
{
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $lineas = $body['lineas'] ?? [];
    $id     = $modelo->crear($body, $lineas);
    $kit    = $modelo->obtener($id);
    $kit['lineas'] = $modelo->getLineas($id);
    jsonResponse(true, $kit, 'Kit creado correctamente.', 201);
}

/**
 * Maneja peticiones PUT (actualizar kit).
 *
 * @param Kit $modelo Instancia del modelo Kit.
 * @return void
 * @author Carlitos6712
 */
function handlePut(Kit $modelo): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        jsonResponse(false, null, 'Parámetro id requerido.', 400);
    }
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $lineas = $body['lineas'] ?? [];
    $modelo->actualizar($id, $body, $lineas);
    $kit = $modelo->obtener($id);
    $kit['lineas'] = $modelo->getLineas($id);
    jsonResponse(true, $kit, 'Kit actualizado correctamente.');
}

/**
 * Maneja peticiones PATCH (toggle activo).
 *
 * @param Kit $modelo Instancia del modelo Kit.
 * @return void
 * @author Carlitos6712
 */
function handlePatch(Kit $modelo): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        jsonResponse(false, null, 'Parámetro id requerido.', 400);
    }
    if (!isset($_GET['toggle'])) {
        jsonResponse(false, null, 'Acción PATCH no reconocida.', 400);
    }
    $nuevoEstado = $modelo->toggleActivo($id);
    $estado      = $nuevoEstado ? 'activado' : 'desactivado';
    jsonResponse(true, ['activo' => $nuevoEstado], "Kit {$estado} correctamente.");
}
