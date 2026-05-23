<?php
/**
 * Tests unitarios para ImportadorProductos.
 *
 * Usa SQLite en memoria: autocontenido, sin depender de la DB MySQL
 * de Docker ni de ningún servicio externo.
 *
 * Cubre: inserción válida, modo actualización, detección de ref duplicada,
 *        errores de validación por fila, rollback al superar 50% de errores,
 *        registro en auditoría y validación de tamaño de archivo.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProductoImportTest extends TestCase
{
    private PDO                $pdo;
    private ImportadorProductos $model;

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->crearSchema();

        $auditoria   = new Auditoria($this->pdo);
        $this->model = new ImportadorProductos($this->pdo, $auditoria);
    }

    // ── Schema SQLite en memoria ──────────────────────────────────────────────

    /**
     * Crea las tablas mínimas necesarias en SQLite in-memory.
     *
     * @author Carlitos6712
     */
    private function crearSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE auditoria (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                tabla            TEXT    NOT NULL,
                registro_id      INTEGER NOT NULL,
                accion           TEXT    NOT NULL,
                datos_anteriores TEXT    NULL,
                datos_nuevos     TEXT    NULL,
                usuario          TEXT    DEFAULT 'admin',
                ip               TEXT,
                fecha            TEXT    DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE categorias (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT    NOT NULL
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE marcas (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT    NOT NULL
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE productos (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre       TEXT    NOT NULL,
                descripcion  TEXT,
                precio       REAL    DEFAULT 0,
                stock        INTEGER DEFAULT 0,
                stock_minimo INTEGER DEFAULT 5,
                codigo_ref   TEXT,
                categoria_id INTEGER,
                marca_id     INTEGER,
                proveedor    TEXT,
                ubicacion    TEXT,
                deleted_at   TEXT    DEFAULT NULL,
                created_at   TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT    DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Crea un archivo CSV temporal con el contenido dado y devuelve su ruta.
     *
     * @param string $contenido Contenido del CSV.
     * @return string Ruta al archivo temporal.
     * @author Carlitos6712
     */
    private function crearCsvTemporal(string $contenido): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'es21_import_');
        file_put_contents($tmp, $contenido);
        return $tmp;
    }

    /**
     * Inserta un producto con un código_ref dado y devuelve su ID.
     *
     * @param string $ref Código de referencia.
     * @param string $nombre Nombre del producto.
     * @return int ID del producto insertado.
     * @author Carlitos6712
     */
    private function insertarProductoConRef(string $ref, string $nombre = 'Producto Existente'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO productos (nombre, codigo_ref, precio, stock, stock_minimo)
             VALUES (:nombre, :ref, 10.00, 5, 2)"
        );
        $stmt->execute([':nombre' => $nombre, ':ref' => $ref]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    #[Test]
    public function it_imports_valid_rows_correctly(): void
    {
        $csv  = "Ref,Nombre,Categoria,Marca,Precio,Stock,Stock Minimo,Descripcion,Proveedor,Ubicacion\n";
        $csv .= "REF001,Producto Test,,, 9.99,10,2,Descripcion,,\n";
        $tmp  = $this->crearCsvTemporal($csv);

        $resultado = $this->model->procesar($tmp, false);

        $this->assertSame(1, $resultado['insertados']);
        $this->assertSame(0, $resultado['actualizados']);
        $this->assertEmpty($resultado['errores']);

        @unlink($tmp);
    }

    #[Test]
    public function it_updates_existing_product_in_update_mode(): void
    {
        $this->insertarProductoConRef('REF_UPD', 'Nombre Original');

        $csv  = "Ref,Nombre,Categoria,Marca,Precio,Stock,Stock Minimo,Descripcion,Proveedor,Ubicacion\n";
        $csv .= "REF_UPD,Nombre Actualizado,,,15.00,20,3,,,\n";
        $tmp  = $this->crearCsvTemporal($csv);

        $resultado = $this->model->procesar($tmp, true);

        $this->assertSame(1, $resultado['actualizados']);
        $this->assertSame(0, $resultado['insertados']);
        $this->assertEmpty($resultado['errores']);

        // Verificar que el nombre fue actualizado
        $row = $this->pdo->query("SELECT nombre FROM productos WHERE codigo_ref = 'REF_UPD'")->fetch();
        $this->assertSame('Nombre Actualizado', $row['nombre']);

        @unlink($tmp);
    }

    #[Test]
    public function it_does_not_duplicate_in_insert_mode_with_existing_ref(): void
    {
        $this->insertarProductoConRef('REF_DUP');

        // 2 filas: 1 duplicada (error) + 1 válida → 1/2 = 50% → NO supera el umbral
        $csv  = "Ref,Nombre,Categoria,Marca,Precio,Stock,Stock Minimo,Descripcion,Proveedor,Ubicacion\n";
        $csv .= "REF_DUP,Producto Duplicado,,,5.00,1,1,,,\n";
        $csv .= "REF_NEW,Producto Nuevo,,,7.00,2,1,,,\n";
        $tmp  = $this->crearCsvTemporal($csv);

        $resultado = $this->model->procesar($tmp, false);

        // Debe generar error de línea en la duplicada, pero insertar la nueva
        $this->assertSame(1, $resultado['insertados']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertStringContainsString('ya existe', $resultado['errores'][0]['motivo']);

        // Verificar que sigue habiendo solo 1 producto con REF_DUP
        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM productos WHERE codigo_ref = 'REF_DUP'")->fetchColumn();
        $this->assertSame(1, $count);

        @unlink($tmp);
    }

    #[Test]
    public function it_records_row_error_when_nombre_is_empty(): void
    {
        // 2 filas: 1 con nombre vacío (error) + 1 válida → 1/2 = 50% → NO supera el umbral
        $csv  = "Ref,Nombre,Categoria,Marca,Precio,Stock,Stock Minimo,Descripcion,Proveedor,Ubicacion\n";
        $csv .= "REF999,,,,9.99,10,2,,,\n";
        $csv .= "REF000,Producto Valido,,,9.99,10,2,,,\n";
        $tmp  = $this->crearCsvTemporal($csv);

        $resultado = $this->model->procesar($tmp, false);

        $this->assertCount(1, $resultado['errores']);
        $this->assertArrayHasKey('linea',  $resultado['errores'][0]);
        $this->assertArrayHasKey('motivo', $resultado['errores'][0]);
        $this->assertSame(2, $resultado['errores'][0]['linea']);
        $this->assertSame(1, $resultado['insertados']); // la fila válida se insertó

        @unlink($tmp);
    }

    #[Test]
    public function it_records_row_error_when_precio_is_negative(): void
    {
        // 2 filas: 1 con precio negativo (error) + 1 válida → 1/2 = 50% → NO supera el umbral
        $csv  = "Ref,Nombre,Categoria,Marca,Precio,Stock,Stock Minimo,Descripcion,Proveedor,Ubicacion\n";
        $csv .= "REF001,Producto Malo,,,-5,10,2,,,\n";
        $csv .= "REF002,Producto Valido,,,5.00,10,2,,,\n";
        $tmp  = $this->crearCsvTemporal($csv);

        $resultado = $this->model->procesar($tmp, false);

        $this->assertCount(1, $resultado['errores']);
        $this->assertStringContainsString('precio', strtolower($resultado['errores'][0]['motivo']));
        $this->assertSame(1, $resultado['insertados']); // la fila válida se insertó

        @unlink($tmp);
    }

    #[Test]
    public function it_rollbacks_when_more_than_50_percent_fail(): void
    {
        // 3 buenas + 4 malas = 7 filas, 4/7 ≈ 57% > 50%
        $csv  = "Ref,Nombre,Categoria,Marca,Precio,Stock,Stock Minimo,Descripcion,Proveedor,Ubicacion\n";
        $csv .= "R01,Bueno Uno,,,10.00,5,2,,,\n";
        $csv .= "R02,Bueno Dos,,,20.00,3,1,,,\n";
        $csv .= "R03,Bueno Tres,,,15.00,8,2,,,\n";
        $csv .= "R04,,,,-5,0,0,,,\n";   // precio negativo + nombre vacío
        $csv .= "R05,,,,-1,0,0,,,\n";   // precio negativo + nombre vacío
        $csv .= "R06,,,,-2,0,0,,,\n";   // precio negativo + nombre vacío
        $csv .= "R07,,,,-3,0,0,,,\n";   // precio negativo + nombre vacío
        $tmp  = $this->crearCsvTemporal($csv);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(422);

        try {
            $this->model->procesar($tmp, false);
        } finally {
            // Verificar que el rollback fue efectivo: ningún producto insertado
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
            $this->assertSame(0, $count, 'El rollback debe dejar la tabla vacía');
            @unlink($tmp);
        }
    }

    #[Test]
    public function it_registers_audit_after_successful_import(): void
    {
        $csv  = "Ref,Nombre,Categoria,Marca,Precio,Stock,Stock Minimo,Descripcion,Proveedor,Ubicacion\n";
        $csv .= "RAUD1,Producto Auditado,,,12.00,5,2,,,\n";
        $tmp  = $this->crearCsvTemporal($csv);

        $this->model->procesar($tmp, false);

        $row = $this->pdo->query(
            "SELECT * FROM auditoria WHERE accion = 'importacion_csv' LIMIT 1"
        )->fetch();

        $this->assertNotFalse($row, 'Debe existir un registro de auditoría con accion=importacion_csv');
        $this->assertSame('productos', $row['tabla']);

        @unlink($tmp);
    }

    #[Test]
    public function it_rejects_file_larger_than_5mb(): void
    {
        $file = [
            'error'    => UPLOAD_ERR_OK,
            'size'     => 6 * 1024 * 1024,
            'name'     => 'test.csv',
            'tmp_name' => '',
        ];

        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->validarArchivo($file);
    }
}
