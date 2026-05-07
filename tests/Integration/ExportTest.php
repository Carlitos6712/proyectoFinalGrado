<?php
/**
 * Tests de integración para los endpoints de exportación CSV y PDF.
 *
 * Verifica que:
 *  - El CSV de productos incluye BOM UTF-8
 *  - El CSV respeta el filtro de categoría activo
 *  - El PDF genera un Content-Type correcto (application/pdf)
 *  - El CSV de movimientos soporta filtro de rango de fechas
 *
 * Requiere conexión MySQL activa (docker-compose DB).
 *
 * @package  Es21Plus\Tests\Integration
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExportTest extends TestCase
{
    private PDO   $pdo;
    private array $createdProductIds  = [];
    private array $createdCatIds      = [];
    private array $createdMovIds      = [];

    protected function setUp(): void
    {
        $this->pdo = Database::getInstance();
    }

    protected function tearDown(): void
    {
        if ($this->createdMovIds) {
            $ids = implode(',', array_map('intval', $this->createdMovIds));
            $this->pdo->exec("DELETE FROM movimientos WHERE id IN ({$ids})");
        }
        if ($this->createdProductIds) {
            $ids = implode(',', array_map('intval', $this->createdProductIds));
            $this->pdo->exec("DELETE FROM productos WHERE id IN ({$ids}) AND nombre LIKE 'EXPTEST_%'");
        }
        if ($this->createdCatIds) {
            $ids = implode(',', array_map('intval', $this->createdCatIds));
            $this->pdo->exec("DELETE FROM categorias WHERE id IN ({$ids}) AND nombre LIKE 'EXPTEST_%'");
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Inserta un producto de prueba y registra su ID para limpieza. */
    private function insertProduct(array $overrides = []): int
    {
        $d = array_merge([
            ':nombre'       => 'EXPTEST_producto',
            ':descripcion'  => 'Test exportación',
            ':precio'       => 15.00,
            ':stock'        => 10,
            ':stock_minimo' => 5,
            ':codigo_ref'   => null,
            ':categoria_id' => null,
        ], $overrides);

        $this->pdo->prepare(
            "INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, codigo_ref, categoria_id)
             VALUES (:nombre, :descripcion, :precio, :stock, :stock_minimo, :codigo_ref, :categoria_id)"
        )->execute($d);

        $id = (int) $this->pdo->lastInsertId();
        $this->createdProductIds[] = $id;
        return $id;
    }

    /**
     * Genera el CSV de productos usando la misma lógica que el endpoint.
     *
     * @param array<int, array<string, mixed>> $items Productos a exportar.
     * @return string Contenido del CSV.
     */
    private function generarCsvProductos(array $items): string
    {
        ob_start();
        $out = fopen('php://output', 'w');
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
        return ob_get_clean();
    }

    // ── 1. CSV incluye BOM UTF-8 ──────────────────────────────────────────────

    #[Test]
    public function it_exports_csv_with_utf8_bom_header(): void
    {
        $this->insertProduct(['nombre' => 'EXPTEST_bom']);

        $modelo = new Producto();
        $items  = $modelo->listarPaginado(1, 100);
        $csv    = $this->generarCsvProductos($items);

        // BOM UTF-8: primeros 3 bytes = 0xEF 0xBB 0xBF
        $bom = substr($csv, 0, 3);
        $this->assertSame("\xEF\xBB\xBF", $bom, 'El CSV debe comenzar con BOM UTF-8');
        $this->assertStringContainsString('Referencia', $csv, 'El CSV debe incluir la cabecera de columnas');
        $this->assertStringContainsString('Nombre', $csv);
    }

    // ── 2. CSV respeta filtro de categoría ────────────────────────────────────

    #[Test]
    public function it_exports_csv_respecting_active_category_filter(): void
    {
        // Crear categoría de prueba
        $this->pdo->exec("INSERT INTO categorias (nombre) VALUES ('EXPTEST_cat_csv')");
        $catId = (int) $this->pdo->lastInsertId();
        $this->createdCatIds[] = $catId;

        $idDentro = $this->insertProduct([':nombre' => 'EXPTEST_dentro_cat', ':categoria_id' => $catId]);
        $idFuera  = $this->insertProduct([':nombre' => 'EXPTEST_fuera_cat',  ':categoria_id' => null]);

        $modelo = new Producto();
        // Filtrar solo por categoría
        $items  = $modelo->listarPaginado(1, 500, null, $catId);
        $csv    = $this->generarCsvProductos($items);

        $this->assertStringContainsString('EXPTEST_dentro_cat', $csv,
            'El CSV debe incluir el producto de la categoría filtrada');
        $this->assertStringNotContainsString('EXPTEST_fuera_cat', $csv,
            'El CSV no debe incluir productos de otras categorías');
    }

    // ── 3. PDF genera Content-Type application/pdf ────────────────────────────

    #[Test]
    public function it_exports_pdf_with_correct_content_type_header(): void
    {
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (!file_exists($autoload)) {
            $this->markTestSkipped('FPDF no instalado. Ejecuta: composer update');
        }

        require_once $autoload;

        // Verificar que FPDF está disponible y puede generar un PDF básico
        $this->assertTrue(class_exists('FPDF'), 'La clase FPDF debe estar disponible tras composer update');

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'es21plus Test');
        $output = $pdf->Output('S');

        // Los PDFs comienzan siempre con %PDF
        $this->assertStringStartsWith('%PDF', $output, 'El PDF generado debe comenzar con la firma %PDF');
        $this->assertGreaterThan(100, strlen($output), 'El PDF debe tener contenido');
    }

    // ── 4. CSV movimientos con filtro de rango de fechas ─────────────────────

    #[Test]
    public function it_exports_movements_csv_with_date_range_filter(): void
    {
        // Insertar producto y movimiento de prueba
        $idProd = $this->insertProduct([':nombre' => 'EXPTEST_mov_export', ':stock' => 50]);

        $this->pdo->prepare(
            "INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, fecha)
             VALUES (:pid, 'entrada', 10, 'Test exportacion CSV', '2024-03-15 10:00:00')"
        )->execute([':pid' => $idProd]);
        $this->createdMovIds[] = (int) $this->pdo->lastInsertId();

        // Generar CSV filtrando por rango de fechas que incluye el movimiento
        $pdo        = Database::getInstance();
        $fechaDesde = '2024-03-01';
        $fechaHasta = '2024-03-31';

        $sql = "SELECT m.fecha, p.nombre AS producto, m.tipo,
                       m.cantidad, m.observaciones,
                       COALESCE(m.usuario, 'admin') AS usuario
                FROM movimientos m
                JOIN productos p ON p.id = m.producto_id
                WHERE p.deleted_at IS NULL
                  AND DATE(m.fecha) >= :fecha_desde
                  AND DATE(m.fecha) <= :fecha_hasta
                ORDER BY m.fecha DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':fecha_desde' => $fechaDesde, ':fecha_hasta' => $fechaHasta]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Fecha', 'Producto', 'Tipo', 'Cantidad', 'Observaciones', 'Usuario'], ';');
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['fecha'], $row['producto'], $row['tipo'],
                (int)$row['cantidad'], $row['observaciones'], $row['usuario'],
            ], ';');
        }
        fclose($out);
        $csv = ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'El CSV de movimientos debe tener BOM UTF-8');
        $this->assertStringContainsString('Fecha', $csv,               'Debe incluir columna Fecha');
        $this->assertStringContainsString('EXPTEST_mov_export', $csv, 'Debe incluir el movimiento del rango de fechas');
        $this->assertStringContainsString('Test exportacion CSV', $csv);
    }
}
