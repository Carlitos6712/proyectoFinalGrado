<?php
/**
 * Tests unitarios para la validación y gestión de imágenes de producto.
 *
 * Verifica que:
 *  - Se rechaza archivos de más de 2 MB
 *  - Se rechaza tipos de archivo no permitidos
 *  - Se aceptan JPG, PNG y WebP
 *  - El nombre generado sigue el patrón producto_{id}_{timestamp}.webp
 *  - La imagen anterior se elimina al subir una nueva
 *  - Se devuelve la ruta del placeholder SVG cuando no hay imagen
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ImagenTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Crea un array que simula una entrada de $_FILES.
     *
     * @param int    $size  Tamaño en bytes.
     * @param string $mime  Tipo MIME.
     * @param string $tmpName Ruta al fichero temporal (puede no existir para tests de validación).
     * @return array<string, mixed>
     */
    private function fakefile(int $size, string $mime, string $tmpName = '/tmp/fake_test'): array
    {
        return [
            'error'    => UPLOAD_ERR_OK,
            'size'     => $size,
            'tmp_name' => $tmpName,
            'name'     => 'test.jpg',
            'type'     => $mime,
        ];
    }

    // ── 1. Rechaza archivos mayores de 2 MB ───────────────────────────────────

    #[Test]
    public function it_rejects_files_larger_than_2mb(): void
    {
        $file = $this->fakefile(3 * 1024 * 1024, 'image/jpeg'); // 3 MB

        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches('/2\s*MB/i');

        Producto::validarArchivoImagen($file);
    }

    // ── 2. Rechaza tipos de archivo no permitidos ─────────────────────────────

    #[Test]
    public function it_rejects_non_image_file_types(): void
    {
        // Crear un fichero temporal real para que mime_content_type lo lea
        $tmp = tempnam(sys_get_temp_dir(), 'es21_test_');
        file_put_contents($tmp, 'este no es una imagen');
        $file = $this->fakefile(100, 'application/pdf', $tmp);

        try {
            $this->expectException(AppException::class);
            Producto::validarArchivoImagen($file);
        } finally {
            @unlink($tmp);
        }
    }

    // ── 3. Acepta JPG, PNG y WebP ─────────────────────────────────────────────

    #[Test]
    public function it_accepts_jpg_png_and_webp_formats(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('Extensión GD no disponible.');
        }

        $formatos = [
            ['image/jpeg', fn($f) => imagejpeg(imagecreatetruecolor(10, 10), $f)],
            ['image/png',  fn($f) => imagepng(imagecreatetruecolor(10, 10), $f)],
            ['image/webp', fn($f) => imagewebp(imagecreatetruecolor(10, 10), $f)],
        ];

        foreach ($formatos as [$mime, $crear]) {
            $tmp = tempnam(sys_get_temp_dir(), 'es21_img_');
            $crear($tmp);
            $file = $this->fakefile(filesize($tmp), $mime, $tmp);

            try {
                // No debe lanzar excepción
                Producto::validarArchivoImagen($file);
                $this->assertTrue(true, "Formato {$mime} debe ser aceptado");
            } finally {
                @unlink($tmp);
            }
        }
    }

    // ── 4. Nombre sigue el patrón producto_{id}_{timestamp}.webp ─────────────

    #[Test]
    public function it_renames_file_with_product_id_and_timestamp_pattern(): void
    {
        $id        = 42;
        $timestamp = 1700000000;
        $nombre    = Producto::generarNombreImagen($id, $timestamp);

        $this->assertSame("producto_{$id}_{$timestamp}.webp", $nombre);
        $this->assertMatchesRegularExpression(
            '/^producto_\d+_\d+\.webp$/',
            $nombre,
            'El nombre debe seguir el patrón producto_{id}_{timestamp}.webp'
        );
    }

    // ── 5. La imagen anterior se elimina al subir una nueva ───────────────────

    #[Test]
    public function it_deletes_image_file_when_product_is_updated_with_new_image(): void
    {
        // Crear un fichero de imagen ficticio en el directorio de uploads
        $uploadDir = Producto::UPLOAD_DIR;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $nombreViejo = 'producto_9999_1111111111.webp';
        $rutaVieja   = $uploadDir . $nombreViejo;
        file_put_contents($rutaVieja, 'fake-image-content');

        // Insertar un producto de prueba con esa imagen en la BD
        $pdo = Database::getInstance();
        $pdo->prepare(
            "INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, imagen)
             VALUES ('TEST_imagen_del', 'test', 1.00, 1, 1, :imagen)"
        )->execute([':imagen' => $nombreViejo]);
        $testId = (int) $pdo->lastInsertId();

        try {
            $modelo = new Producto();
            $modelo->eliminarImagen($testId);

            $this->assertFileDoesNotExist($rutaVieja, 'El fichero de imagen anterior debe eliminarse');

            // Verificar que la columna imagen queda a NULL
            $row = $pdo->query("SELECT imagen FROM productos WHERE id = {$testId}")->fetch();
            $this->assertNull($row['imagen'] ?? 'no-null', 'La columna imagen debe quedar a NULL tras eliminar');
        } finally {
            @unlink($rutaVieja);
            $pdo->exec("DELETE FROM productos WHERE id = {$testId} AND nombre = 'TEST_imagen_del'");
        }
    }

    // ── 6. Placeholder SVG cuando no hay imagen ───────────────────────────────

    #[Test]
    public function it_returns_svg_placeholder_path_when_product_has_no_image(): void
    {
        $ruta = Producto::rutaImagen(null);
        $this->assertStringContainsString('placeholder', $ruta);
        $this->assertStringEndsWith('.svg', $ruta);
    }
}
