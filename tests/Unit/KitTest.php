<?php
/**
 * Tests unitarios del modelo Kit con SQLite en memoria.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers Kit
 */
class KitTest extends TestCase
{
    private PDO     $pdo;
    private Kit     $modelo;
    private Auditoria $auditoria;

    /** Crea la base de datos SQLite en memoria con las tablas necesarias. */
    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS auditoria (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                tabla      TEXT    NOT NULL,
                registro_id INTEGER NOT NULL,
                accion     TEXT    NOT NULL,
                datos_anteriores TEXT,
                datos_nuevos     TEXT,
                usuario    TEXT DEFAULT 'test',
                ip         TEXT,
                fecha      TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS productos (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre     TEXT    NOT NULL,
                codigo_ref TEXT,
                stock      INTEGER DEFAULT 0,
                deleted_at TEXT
            );
            CREATE TABLE IF NOT EXISTS kits (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre      TEXT    NOT NULL,
                descripcion TEXT,
                activo      INTEGER DEFAULT 1,
                created_at  TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT    DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS kits_lineas (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                kit_id      INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                cantidad    INTEGER NOT NULL DEFAULT 1,
                UNIQUE (kit_id, producto_id),
                FOREIGN KEY (kit_id)      REFERENCES kits(id)     ON DELETE CASCADE,
                FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
            );
        ");

        // Productos de prueba
        $this->pdo->exec("
            INSERT INTO productos (nombre, codigo_ref, stock) VALUES
              ('Filtro de aceite', 'REF-001', 20),
              ('Aceite motor 1L',  'REF-002', 10),
              ('Pastillas delanteras', 'REF-003', 5);
        ");

        $this->auditoria = new Auditoria($this->pdo);
        $this->modelo    = new Kit($this->pdo, $this->auditoria);
    }

    /** Listar retorna array vacío cuando no hay kits. */
    public function testListarVacioInicialmente(): void
    {
        $this->assertSame([], $this->modelo->listar());
    }

    /** Crear kit sin líneas devuelve ID > 0. */
    public function testCrearKitSinLineas(): void
    {
        $id = $this->modelo->crear(['nombre' => 'Kit de prueba']);
        $this->assertGreaterThan(0, $id);
    }

    /** Crear kit con nombre vacío lanza AppException 422. */
    public function testCrearKitNombreVacioLanzaExcepcion(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(422);
        $this->modelo->crear(['nombre' => '  ']);
    }

    /** Crear kit con líneas sincroniza correctamente. */
    public function testCrearKitConLineas(): void
    {
        $lineas = [
            ['producto_id' => 1, 'cantidad' => 2],
            ['producto_id' => 2, 'cantidad' => 1],
        ];
        $id = $this->modelo->crear(['nombre' => 'Kit Revisión'], $lineas);

        $this->assertSame(2, $this->modelo->contarLineas($id));
    }

    /** Obtener kit existente devuelve datos correctos. */
    public function testObtenerKitExistente(): void
    {
        $id  = $this->modelo->crear(['nombre' => 'Kit Frenos', 'descripcion' => 'Desc.']);
        $kit = $this->modelo->obtener($id);

        $this->assertSame('Kit Frenos', $kit['nombre']);
        $this->assertSame('Desc.',      $kit['descripcion']);
        $this->assertSame(1,            (int)$kit['activo']);
    }

    /** Obtener kit inexistente lanza AppException 404. */
    public function testObtenerKitInexistenteLanzaExcepcion(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);
        $this->modelo->obtener(9999);
    }

    /** getLineas devuelve las líneas con datos del producto. */
    public function testGetLineas(): void
    {
        $id = $this->modelo->crear(
            ['nombre' => 'Kit Test'],
            [['producto_id' => 1, 'cantidad' => 3]]
        );
        $lineas = $this->modelo->getLineas($id);

        $this->assertCount(1, $lineas);
        $this->assertSame(1, (int)$lineas[0]['producto_id']);
        $this->assertSame(3, (int)$lineas[0]['cantidad']);
        $this->assertSame('Filtro de aceite', $lineas[0]['producto_nombre']);
    }

    /** Actualizar modifica nombre, descripcion y re-sincroniza líneas. */
    public function testActualizarKit(): void
    {
        $id = $this->modelo->crear(
            ['nombre' => 'Kit Original'],
            [['producto_id' => 1, 'cantidad' => 1]]
        );

        $this->modelo->actualizar(
            $id,
            ['nombre' => 'Kit Actualizado', 'descripcion' => 'Nueva desc.'],
            [['producto_id' => 2, 'cantidad' => 5]]
        );

        $kit    = $this->modelo->obtener($id);
        $lineas = $this->modelo->getLineas($id);

        $this->assertSame('Kit Actualizado', $kit['nombre']);
        $this->assertCount(1, $lineas);
        $this->assertSame(2, (int)$lineas[0]['producto_id']);
        $this->assertSame(5, (int)$lineas[0]['cantidad']);
    }

    /** syncLineas con linea de cantidad 0 lanza AppException 422. */
    public function testSyncLineasCantidadCeroLanzaExcepcion(): void
    {
        $id = $this->modelo->crear(['nombre' => 'Kit Cantidad Cero']);
        $this->expectException(AppException::class);
        $this->expectExceptionCode(422);
        $this->modelo->syncLineas($id, [['producto_id' => 1, 'cantidad' => 0]]);
    }

    /** toggleActivo desactiva un kit activo y lo devuelve como false. */
    public function testToggleActivoDesactiva(): void
    {
        $id    = $this->modelo->crear(['nombre' => 'Kit Toggle']);
        $nuevo = $this->modelo->toggleActivo($id);

        $this->assertFalse($nuevo);
        $kit = $this->modelo->obtener($id);
        $this->assertSame(0, (int)$kit['activo']);
    }

    /** toggleActivo vuelve a activar un kit inactivo. */
    public function testToggleActivoReactiva(): void
    {
        $id = $this->modelo->crear(['nombre' => 'Kit Toggle2', 'activo' => 0]);
        // Primero está inactivo; toggle lo activa
        $nuevo = $this->modelo->toggleActivo($id);
        $this->assertTrue($nuevo);
    }

    /** listar(false) incluye kits inactivos. */
    public function testListarTodosIncluyeInactivos(): void
    {
        $this->modelo->crear(['nombre' => 'Kit Activo',   'activo' => 1]);
        $this->modelo->crear(['nombre' => 'Kit Inactivo', 'activo' => 0]);

        $todos   = $this->modelo->listar(false);
        $activos = $this->modelo->listar(true);

        $this->assertCount(2, $todos);
        $this->assertCount(1, $activos);
    }

    /** contarLineas devuelve 0 para kit sin líneas. */
    public function testContarLineasCero(): void
    {
        $id = $this->modelo->crear(['nombre' => 'Kit Vacío']);
        $this->assertSame(0, $this->modelo->contarLineas($id));
    }

    /** syncLineas vacía líneas cuando se pasa array vacío. */
    public function testSyncLineasVaciaLineas(): void
    {
        $id = $this->modelo->crear(
            ['nombre' => 'Kit Con Lineas'],
            [['producto_id' => 1, 'cantidad' => 2]]
        );
        $this->assertSame(1, $this->modelo->contarLineas($id));

        $this->modelo->syncLineas($id, []);
        $this->assertSame(0, $this->modelo->contarLineas($id));
    }
}
