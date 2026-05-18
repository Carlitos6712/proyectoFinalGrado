<?php
/**
 * Descarga PDF de una factura.
 *
 * GET /superadmin/billing/pdf.php?id=X
 *
 * @package  Es21Plus\Superadmin\Billing
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/../controllers/BillingController.php';
require_once __DIR__ . '/../../core/PdfGenerator.php';

SuperadminMiddleware::require();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('ID inválido.');
}

try {
    $ctrl    = new BillingController();
    $row     = $ctrl->find($id);
    $invoice = [
        'invoice_number' => $row['invoice_number'],
        'amount'         => $row['amount'],
        'status'         => $row['status'],
        'period_start'   => $row['period_start'],
        'period_end'     => $row['period_end'],
        'notes'          => $row['notes'],
        'created_at'     => $row['created_at'],
    ];
    $business = [
        'name'  => $row['name']  ?? '',
        'email' => $row['email'] ?? '',
        'plan'  => $row['plan']  ?? '',
    ];
    PdfGenerator::downloadInvoice($invoice, $business);
} catch (AppException $e) {
    http_response_code(404);
    die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
} catch (\Throwable $e) {
    http_response_code(500);
    die('Error al generar PDF: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
