<?php
/**
 * API REST para gestión de productos del inventario.
 *
 * Verbos:
 *   GET    ?id=X               → obtener uno (404 si no existe)
 *   GET    ?page=&limit=&...   → listado paginado con filtros combinables
 *   GET    ?export=csv         → exportar CSV (respeta filtros activos)
 *   GET    ?export=pdf         → exportar PDF con encabezado y resumen stock bajo
 *   POST                       → crear (422 si validación falla)
 *   PUT                        → actualizar (422 validación, 404 si no existe)
 *   DELETE ?id=X               → soft-delete (404 si no existe)
 *
 * Filtros: q, categoria_id, precio_min, precio_max,
 *          stock_min, stock_max, stock_bajo, orden
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
    // ── Exportaciones (CSV / PDF) ─────────────────────────────────────────────
    if (isset($_GET['export'])) {
        $filtros = extraerFiltros();
        $total   = $modelo->contarFiltrados(...$filtros);
        $items   = $total > 0
            ? $modelo->listarPaginado(1, $total, ...$filtros)
            : [];

        match ($_GET['export']) {
            'csv'   => exportarCsv($items),
            'pdf'   => exportarPdf($items, $modelo->contarFiltrados(null, null, null, null, null, null, true)),
            default => jsonResponse(false, null, 'Tipo de exportación no soportado.', 400),
        };
    }

    // ── Obtener producto por ID ───────────────────────────────────────────────
    if (isset($_GET['id'])) {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new AppException('El parámetro id debe ser un entero válido.', 400);
        }
        jsonResponse(true, $modelo->obtener($id), 'Producto encontrado.');
    }

    // ── Listado paginado con filtros ──────────────────────────────────────────
    $page   = max(1, (int)(filter_input(INPUT_GET, 'page',  FILTER_VALIDATE_INT) ?: 1));
    $limit  = max(1, min(100, (int)(filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 20)));

    $filtros = extraerFiltros();
    $total   = $modelo->contarFiltrados(...$filtros);
    $items   = $modelo->listarPaginado($page, $limit, ...$filtros);

    jsonResponse(true, [
        'items'       => $items,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => (int) ceil($total / max(1, $limit)),
    ], 'Listado de productos.');
}

/**
 * Extrae y valida los parámetros de filtrado comunes del GET.
 *
 * @return array{0:string|null,1:int|null,2:float|null,3:float|null,4:int|null,5:int|null,6:bool|null}
 */
function extraerFiltros(): array
{
    $q             = trim($_GET['q'] ?? '') ?: null;
    $categoriaId   = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT)   ?: null;
    $precioMin     = filter_input(INPUT_GET, 'precio_min',   FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $precioMax     = filter_input(INPUT_GET, 'precio_max',   FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $stockMin      = filter_input(INPUT_GET, 'stock_min',    FILTER_VALIDATE_INT,   FILTER_NULL_ON_FAILURE);
    $stockMax      = filter_input(INPUT_GET, 'stock_max',    FILTER_VALIDATE_INT,   FILTER_NULL_ON_FAILURE);
    $soloStockBajo = (isset($_GET['stock_bajo']) && $_GET['stock_bajo'] === '1') ? true : null;
    return [$q, $categoriaId, $precioMin, $precioMax, $stockMin, $stockMax, $soloStockBajo];
}

/**
 * Genera y envía el CSV de productos con BOM UTF-8.
 *
 * @param array<int, array<string, mixed>> $items Productos a exportar.
 * @return void
 */
function exportarCsv(array $items): void
{
    $fecha = date('Ymd_His');
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"productos_{$fecha}.csv\"");
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    // BOM UTF-8 para compatibilidad con Excel
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Referencia', 'Nombre', 'Categoría', 'Precio (€)', 'Stock', 'Stock Mínimo', 'Estado'], ';');

    foreach ($items as $p) {
        $esBajo = (int)$p['stock'] <= (int)($p['stock_minimo'] ?? 5);
        fputcsv($out, [
            $p['codigo_ref']       ?? '',
            $p['nombre']           ?? '',
            $p['categoria_nombre'] ?? 'Sin categoría',
            number_format((float)$p['precio'], 2, ',', '.'),
            (int)$p['stock'],
            (int)($p['stock_minimo'] ?? 5),
            $esBajo ? 'Stock bajo' : 'Disponible',
        ], ';');
    }

    fclose($out);
    exit;
}

/**
 * Genera y envía el PDF de productos mediante FPDF.
 *
 * @param array<int, array<string, mixed>> $items         Productos a exportar.
 * @param int                              $stockBajoTotal Total de productos con stock bajo.
 * @return void
 */
function exportarPdf(array $items, int $stockBajoTotal): void
{
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoload)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(503);
        echo json_encode(['success' => false, 'data' => null,
            'message' => 'Librería PDF no instalada. Ejecuta: composer update'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    require_once $autoload;

    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->SetMargins(12, 12, 12);
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    // ── Encabezado ────────────────────────────────────────────────────────────
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'es21plus - Inventario de Productos', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Generado el: ' . date('d/m/Y H:i'), 0, 1, 'C');
    $pdf->Cell(0, 6, 'Total de productos: ' . count($items) . ' | Stock bajo: ' . $stockBajoTotal, 0, 1, 'C');
    $pdf->Ln(4);

    // ── Cabecera de tabla ─────────────────────────────────────────────────────
    $pdf->SetFillColor(99, 102, 241);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 9);
    $cols = [['Ref.',       22], ['Nombre',    70], ['Categoría', 45],
             ['Precio (€)', 28], ['Stock',     20], ['Mín.',      18], ['Estado',    40]];
    foreach ($cols as [$label, $w]) {
        $pdf->Cell($w, 8, $label, 0, 0, 'C', true);
    }
    $pdf->Ln();

    // ── Filas ─────────────────────────────────────────────────────────────────
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 8);
    $fill = false;
    foreach ($items as $p) {
        $esBajo = (int)$p['stock'] <= (int)($p['stock_minimo'] ?? 5);
        if ($esBajo) {
            $pdf->SetFillColor(254, 243, 199);
        } else {
            $pdf->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 252 : 255);
        }
        $pdf->Cell(22, 7, substr($p['codigo_ref'] ?? '-', 0, 12),         0, 0, 'C', true);
        $pdf->Cell(70, 7, substr($p['nombre'] ?? '', 0, 35),              0, 0, 'L', true);
        $pdf->Cell(45, 7, substr($p['categoria_nombre'] ?? 'Sin cat.', 0, 20), 0, 0, 'L', true);
        $pdf->Cell(28, 7, number_format((float)$p['precio'], 2, ',', '.'), 0, 0, 'R', true);
        $pdf->Cell(20, 7, (string)(int)$p['stock'],                        0, 0, 'C', true);
        $pdf->Cell(18, 7, (string)(int)($p['stock_minimo'] ?? 5),          0, 0, 'C', true);
        $pdf->Cell(40, 7, $esBajo ? 'Stock bajo' : 'Disponible',           0, 1, 'C', true);
        $fill = !$fill;
    }

    // ── Resumen stock bajo ────────────────────────────────────────────────────
    if ($stockBajoTotal > 0) {
        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(254, 226, 226);
        $pdf->Cell(0, 8, "⚠ Productos con stock bajo: {$stockBajoTotal}", 0, 1, 'L', true);
    }

    $fecha = date('Ymd_His');
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=\"productos_{$fecha}.pdf\"");
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $pdf->Output('S');
    exit;
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
