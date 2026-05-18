<?php
/**
 * Exporta datos en formato CSV con BOM UTF-8 para compatibilidad con Excel.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
 */
class CsvExporter
{
    /**
     * Envía un CSV al navegador para descarga inmediata.
     *
     * BOM UTF-8 (\xEF\xBB\xBF) garantiza apertura correcta en Excel.
     * Separador punto y coma (;) para compatibilidad con Excel español.
     *
     * @param array<string>       $headers  Cabeceras de columna.
     * @param array<array<mixed>> $rows     Filas de datos.
     * @param string              $filename Nombre del archivo sin extensión .csv.
     * @return void
     */
    public static function download(array $headers, array $rows, string $filename): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, array_values($row), ';');
        }
        fclose($out);
        exit;
    }
}
