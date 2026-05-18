<?php
/**
 * Endpoint de exportación de reportes (CSV / PDF).
 *
 * GET /superadmin/reports/export.php?type=X&format=csv|pdf&...filtros
 *
 * @package  Es21Plus\Superadmin\Reports
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/../controllers/ReportsController.php';
require_once __DIR__ . '/../../core/CsvExporter.php';
require_once __DIR__ . '/../../core/PdfGenerator.php';

SuperadminMiddleware::require();

$type   = $_GET['type']   ?? '';
$format = $_GET['format'] ?? 'csv';

$validTypes   = ['businesses', 'billing', 'activity', 'tickets'];
$validFormats = ['csv', 'pdf'];

if (!in_array($type, $validTypes, true)) {
    http_response_code(400);
    die('Tipo de reporte inválido.');
}
if (!in_array($format, $validFormats, true)) {
    http_response_code(400);
    die('Formato inválido.');
}

// Los reportes de actividad solo exportan CSV
if ($type === 'activity' && $format === 'pdf') {
    http_response_code(400);
    die('El reporte de actividad solo está disponible en CSV.');
}

$ctrl    = new ReportsController();
$filters = array_intersect_key($_GET, array_flip([
    'status', 'plan', 'business_id', 'fecha_desde', 'fecha_hasta', 'priority'
]));

try {
    $data = match($type) {
        'businesses' => $ctrl->businesses($filters),
        'billing'    => $ctrl->billing($filters),
        'activity'   => $ctrl->activity($filters),
        'tickets'    => $ctrl->tickets($filters),
    };
} catch (\Throwable $e) {
    http_response_code(500);
    die('Error al generar el reporte: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$typeLabels = [
    'businesses' => 'negocios',
    'billing'    => 'facturacion',
    'activity'   => 'actividad',
    'tickets'    => 'tickets',
];
$filename = $typeLabels[$type] . '_' . date('Ymd_Hi');
$title    = match($type) {
    'businesses' => 'Reporte de Negocios — es21plus',
    'billing'    => 'Reporte de Facturación — es21plus',
    'activity'   => 'Reporte de Actividad — es21plus',
    'tickets'    => 'Reporte de Tickets — es21plus',
};

if ($format === 'csv') {
    CsvExporter::download($data['headers'], $data['rows'], $filename);
} else {
    PdfGenerator::downloadReport($title, $data['headers'], $data['rows'], $filename);
}
