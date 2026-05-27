<?php
/**
 * Tests unitarios para el modelo Producto.
 *
 * Usa SQLite en memoria: autocontenido, sin depender de la DB MySQL
 * de Docker ni de ningún servicio externo.
 *
 * Cubre: crear, obtener, actualizar, soft-delete, buscar,
 *        actualizarStock, filtrarStockBajo, contarActivos, valorInventario.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProductoTest extends TestCase
{
    private PDO      $pdo;
    private Producto $model;

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->crearSchema();

        // Auditoria con el mismo PDO (SQLite), sin efectos secundarios
        $auditoria   = new Auditoria($this->pdo);
        $this->model = new Producto($this->pdo, $auditoria);
    }

    // ── Schema SQLite en memoria ──────────────────────────────────────────────

    /**
     * Crea las tablas necesarias en SQLite in-memory.
     *
     * @author Carlitos6712
     */
    private function crearSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE auditoria (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                tabla            TEXT    NOT NULL,
                registro_id      INTEGER NOT NULL,
                accion           TEXT    NOT NULL,
                datos_anteriores TEXT    NULL,
                datos_nuevos     TEXT    NULL,
                usuario          TEXT    DEFAULT 'admin',
                ip               TEXT,
                fecha            TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE marcas (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre     TEXT    NOT NULL,
                created_at TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE categorias (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre      TEXT    NOT NULL,
                descripcion TEXT,
                created_at  TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE productos (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre           TEXT    NOT NULL,
                descripcion      TEXT,
                descripcion_larga TEXT,
                precio           REAL    DEFAULT 0,
                stock            INTEGER DEFAULT 0,
                stock_minimo     INTEGER DEFAULT 5,
                codigo_ref       TEXT,
                categoria_id     INTEGER,
                marca_id         INTEGER,
                imagen           TEXT,
                codigo_barras    TEXT,
                url_proveedor    TEXT,
                proveedor        TEXT,
                ubicacion        TEXT,
                peso             INTEGER,
                capacidad        INTEGER,
                longitud         INTEGER,
                anchura          INTEGER,
                diametro         REAL,
                alertas_email    INTEGER DEFAULT 1,
                proveedor_id     INTEGER NULL,
                deleted_at       TEXT    DEFAULT NULL,
                created_at       TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at       TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE movimientos (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                producto_id  INTEGER NOT NULL,
                tipo         TEXT    NOT NULL,
                cantidad     INTEGER NOT NULL,
                observaciones TEXT,
                usuario      TEXT    DEFAULT 'admin',
                fecha        TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Crea un producto con valores mínimos y devuelve su ID.
     *
     * @param array<string, mixed> $overrides
     * @return int
     */
    private function crearProducto(array $overrides = []): int
    {
        $defaults = [
            'nombre'      => 'Freno Disco',
            'descripcion' => 'Descripción de prueba',
            'precio'      => 25.00,
            'categoriaId' => null,
            'stock'       => 10,
            'stockMinimo' => 5,
        ];
        $d = array_merge($defaults, $overrides);

        return $this->model->crear(
            $d['nombre'],
            $d['descripcion'],
            $d['precio'],
            $d['categoriaId'],
            $d['stock'],
            $d['stockMinimo']
        );
    }

    // ── Tests: crear ─────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_product_and_returns_its_id(): void
    {
        $id = $this->crearProducto();

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    #[Test]
    public function it_persists_product_data_correctly_after_create(): void
    {
        $id  = $this->crearProducto(['nombre' => 'Cadena 520', 'precio' => 18.50, 'stock' => 7]);
        $row = $this->model->obtener($id);

        $this->assertSame('Cadena 520', $row['nombre']);
        $this->assertSame(18.50, (float) $row['precio']);
        $this->assertSame(7,     (int)   $row['stock']);
    }

    // ── Tests: obtener ────────────────────────────────────────────────────────

    #[Test]
    public function it_throws_not_found_exception_for_nonexistent_product(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);

        $this->model->obtener(99999);
    }

    #[Test]
    public function it_does_not_return_soft_deleted_products_from_obtener(): void
    {
        $id = $this->crearProducto();
        $this->pdo->exec("UPDATE productos SET deleted_at = CURRENT_TIMESTAMP WHERE id = {$id}");

        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);

        $this->model->obtener($id);
    }

    // ── Tests: listar ────────────────────────────────────────────────────────

    #[Test]
    public function it_lists_only_active_products(): void
    {
        $idActivo   = $this->crearProducto(['nombre' => 'Activo']);
        $idEliminado = $this->crearProducto(['nombre' => 'Eliminado']);
        $this->pdo->exec("UPDATE productos SET deleted_at = CURRENT_TIMESTAMP WHERE id = {$idEliminado}");

        $ids = array_column($this->model->listar(), 'id');

        $this->assertContains($idActivo,    $ids);
        $this->assertNotContains($idEliminado, $ids);
    }

    // ── Tests: actualizar ─────────────────────────────────────────────────────

    #[Test]
    public function it_updates_product_fields_correctly(): void
    {
        $id = $this->crearProducto(['nombre' => 'Antiguo', 'precio' => 10.00]);

        $result = $this->model->actualizar(
            $id, 'Nuevo Nombre', 'Nueva desc', 99.99, null
        );

        $this->assertTrue($result);
        $row = $this->model->obtener($id);
        $this->assertSame('Nuevo Nombre', $row['nombre']);
        $this->assertSame(99.99, (float) $row['precio']);
    }

    #[Test]
    public function it_throws_exception_when_updating_with_zero_price(): void
    {
        $id = $this->crearProducto();

        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->actualizar($id, 'Nombre', 'Desc', 0.0, null);
    }

    #[Test]
    public function it_throws_exception_when_updating_with_negative_stock_minimo(): void
    {
        $id = $this->crearProducto();

        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->actualizar($id, 'Nombre', 'Desc', 10.0, null, -1);
    }

    // ── Tests: soft-delete ────────────────────────────────────────────────────

    #[Test]
    public function it_soft_deletes_a_product_without_movements(): void
    {
        $id = $this->crearProducto();

        $result = $this->model->eliminar($id);

        $this->assertTrue($result);
        $row = $this->pdo->query("SELECT deleted_at FROM productos WHERE id = {$id}")->fetch();
        $this->assertNotNull($row['deleted_at'], 'deleted_at debe quedar seteado tras el soft-delete');
    }

    #[Test]
    public function it_throws_exception_when_deleting_product_with_movements(): void
    {
        $id = $this->crearProducto();
        $this->pdo->exec(
            "INSERT INTO movimientos (producto_id, tipo, cantidad) VALUES ({$id}, 'entrada', 1)"
        );

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        $this->model->eliminar($id);
    }

    // ── Tests: buscar ─────────────────────────────────────────────────────────

    #[Test]
    public function it_finds_products_matching_search_term(): void
    {
        $idFreno = $this->crearProducto(['nombre' => 'Freno Hidraulico']);
        $idCadena = $this->crearProducto(['nombre' => 'Cadena de Transmision']);

        $results = $this->model->buscar('Freno');
        $ids = array_column($results, 'id');

        $this->assertContains($idFreno,    $ids);
        $this->assertNotContains($idCadena, $ids);
    }

    #[Test]
    public function it_finds_products_by_codigo_ref(): void
    {
        $id = $this->model->crear('Aceite Motor', '', 8.99, null, 5, 2, 'REF-ACEITE-10W40');

        $results = $this->model->buscar('REF-ACEITE-10W40');
        $ids = array_column($results, 'id');

        $this->assertContains($id, $ids);
    }

    #[Test]
    public function it_returns_empty_array_for_no_matching_search(): void
    {
        $results = $this->model->buscar('termino_que_jamas_existira_xyz999');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ── Tests: actualizarStock ────────────────────────────────────────────────

    #[Test]
    public function it_increases_stock_correctly_on_positive_delta(): void
    {
        $id = $this->crearProducto(['stock' => 10]);

        $this->model->actualizarStock($id, 5);

        $row = $this->model->obtener($id);
        $this->assertSame(15, (int) $row['stock']);
    }

    #[Test]
    public function it_decreases_stock_correctly_on_negative_delta(): void
    {
        $id = $this->crearProducto(['stock' => 10]);

        $this->model->actualizarStock($id, -3);

        $row = $this->model->obtener($id);
        $this->assertSame(7, (int) $row['stock']);
    }

    #[Test]
    public function it_throws_exception_when_stock_would_go_negative(): void
    {
        $id = $this->crearProducto(['stock' => 2]);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->actualizarStock($id, -5);
    }

    // ── Tests: filtrarStockBajo ───────────────────────────────────────────────

    #[Test]
    public function it_returns_products_with_stock_at_or_below_minimum(): void
    {
        $idBajo = $this->crearProducto(['nombre' => 'Bajo',     'stock' => 2,  'stockMinimo' => 5]);
        $idOk   = $this->crearProducto(['nombre' => 'Suficiente','stock' => 10, 'stockMinimo' => 5]);

        $resultados = $this->model->filtrarStockBajo();
        $ids = array_column($resultados, 'id');

        $this->assertContains($idBajo, $ids);
        $this->assertNotContains($idOk, $ids);
    }

    #[Test]
    public function it_includes_products_with_stock_exactly_at_minimum(): void
    {
        $id = $this->crearProducto(['stock' => 5, 'stockMinimo' => 5]);

        $ids = array_column($this->model->filtrarStockBajo(), 'id');

        $this->assertContains($id, $ids, 'stock == stock_minimo debe incluirse en stock bajo');
    }

    // ── Tests: contarActivos / valorInventario ────────────────────────────────

    #[Test]
    public function it_counts_only_active_products(): void
    {
        $this->crearProducto(['nombre' => 'Activo1']);
        $this->crearProducto(['nombre' => 'Activo2']);
        $idDel = $this->crearProducto(['nombre' => 'Eliminado']);
        $this->pdo->exec("UPDATE productos SET deleted_at = CURRENT_TIMESTAMP WHERE id = {$idDel}");

        $total = $this->model->contarActivos();

        $this->assertSame(2, $total);
    }

    #[Test]
    public function it_calculates_inventory_value_correctly(): void
    {
        $this->crearProducto(['precio' => 10.00, 'stock' => 3]);  // 30.00
        $this->crearProducto(['precio' => 20.00, 'stock' => 2]);  // 40.00
        // Total esperado: 70.00

        $valor = $this->model->valorInventario();

        $this->assertEqualsWithDelta(70.00, $valor, 0.01);
    }
}
