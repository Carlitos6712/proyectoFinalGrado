<?php
/**
 * Generador de PDFs usando FPDF (ya instalado vía Composer).
 *
 * Dos métodos públicos:
 *   - downloadReport(): informe genérico en formato tabla (orientación landscape).
 *   - downloadInvoice(): factura formal con datos del negocio.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
 */
class PdfGenerator
{
    /**
     * Convierte texto UTF-8 a ISO-8859-1 para compatibilidad con FPDF.
     *
     * @param  string $text
     * @return string
     */
    private static function enc(string $text): string
    {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Genera y descarga un PDF de informe genérico en tabla.
     *
     * @param string              $title    Título del informe.
     * @param array<string>       $headers  Cabeceras de columna.
     * @param array<array<mixed>> $rows     Filas de datos.
     * @param string              $filename Nombre del archivo sin extensión.
     * @throws \RuntimeException Si FPDF no está disponible.
     * @return void
     */
    public static function downloadReport(
        string $title,
        array  $headers,
        array  $rows,
        string $filename
    ): void {
        self::loadFpdf();

        $pdf = new \FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, self::enc($title), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, self::enc('Generado: ' . date('d/m/Y H:i')), 0, 1, 'R');
        $pdf->Ln(3);

        $pageWidth = 277;
        $count     = count($headers);
        $colWidth  = $count > 0 ? $pageWidth / $count : $pageWidth;

        // Header row
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(99, 102, 241);
        $pdf->SetTextColor(255, 255, 255);
        foreach ($headers as $h) {
            $pdf->Cell($colWidth, 7, self::enc($h), 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Data rows
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        $fill = false;
        foreach ($rows as $row) {
            $pdf->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 252 : 255);
            foreach (array_values($row) as $cell) {
                $pdf->Cell($colWidth, 6, self::enc((string)$cell), 1, 0, 'L', true);
            }
            $pdf->Ln();
            $fill = !$fill;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $pdf->Output('D', $filename . '.pdf');
        exit;
    }

    /**
     * Genera y descarga un PDF de factura formal.
     *
     * @param array<string,mixed> $invoice  Fila de la tabla invoices.
     * @param array<string,mixed> $business Fila de la tabla businesses.
     * @throws \RuntimeException Si FPDF no está disponible.
     * @return void
     */
    public static function downloadInvoice(array $invoice, array $business): void
    {
        self::loadFpdf();

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();

        // Marca / título
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetTextColor(99, 102, 241);
        $pdf->Cell(0, 12, 'es21plus', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, self::enc('FACTURA'), 0, 1, 'R');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, self::enc('N.° ' . $invoice['invoice_number']), 0, 1, 'R');
        $pdf->Cell(0, 5, self::enc('Fecha: ' . date('d/m/Y', strtotime($invoice['created_at']))), 0, 1, 'R');

        $pdf->Ln(4);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);

        // Datos del cliente
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, self::enc('Facturar a:'), 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, self::enc($business['name'] ?? ''), 0, 1);
        $pdf->Cell(0, 5, self::enc($business['email'] ?? ''), 0, 1);

        $pdf->Ln(8);

        // Tabla de ítems
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(99, 102, 241);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(110, 8, self::enc('Descripción'), 1, 0, 'L', true);
        $pdf->Cell(40,  8, self::enc('Periodo'),     1, 0, 'C', true);
        $pdf->Cell(40,  8, self::enc('Importe'),     1, 1, 'R', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(248, 250, 252);

        $plan    = ucfirst($business['plan'] ?? '');
        $periodo = '';
        if (!empty($invoice['period_start']) && !empty($invoice['period_end'])) {
            $periodo = date('d/m/Y', strtotime($invoice['period_start']))
                . ' - '
                . date('d/m/Y', strtotime($invoice['period_end']));
        }
        $pdf->Cell(110, 7, self::enc("Plan {$plan} - Suscripción"), 1, 0, 'L', true);
        $pdf->Cell(40,  7, self::enc($periodo), 1, 0, 'C', true);
        $pdf->Cell(40,  7, self::enc(number_format((float)$invoice['amount'], 2, ',', '.') . ' €'), 1, 1, 'R', true);

        // Total
        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(150, 8, self::enc('Total:'), 0, 0, 'R');
        $pdf->Cell(40,  8, self::enc(number_format((float)$invoice['amount'], 2, ',', '.') . ' €'), 0, 1, 'R');

        // Estado de la factura
        $pdf->Ln(4);
        $statusLabel = match($invoice['status'] ?? '') {
            'paid'      => 'PAGADA',
            'cancelled' => 'CANCELADA',
            default     => 'PENDIENTE',
        };
        $pdf->SetFont('Arial', 'B', 11);
        match($invoice['status'] ?? '') {
            'paid'      => $pdf->SetTextColor(21, 128, 61),
            'cancelled' => $pdf->SetTextColor(185, 28, 28),
            default     => $pdf->SetTextColor(146, 64, 14),
        };
        $pdf->Cell(0, 10, self::enc($statusLabel), 0, 1, 'R');

        if (!empty($invoice['notes'])) {
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->Ln(4);
            $pdf->Cell(0, 5, self::enc('Notas: ' . $invoice['notes']), 0, 1);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $invoice['invoice_number'] . '.pdf"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $pdf->Output('D', $invoice['invoice_number'] . '.pdf');
        exit;
    }

    private static function loadFpdf(): void
    {
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (!file_exists($autoload)) {
            throw new \RuntimeException('vendor/autoload.php no encontrado. Ejecuta: composer install');
        }
        require_once $autoload;
    }
}
