<?php
/**
 * Unit + integration tests for Producto model (CRUD, search, stock).
 *
 * Covers:
 *  - listar() returns array excluding soft-deleted
 *  - obtener() returns correct row
 *  - obtener() throws on missing / deleted product
 *  - crear() inserts and returns new ID
 *  - actualizar() modifies fields
 *  - buscar() filters by name and categoria_id
 *  - filtrarStockBajo() returns only products at or below stock_minimo
 *  - actualizarStock() adds and subtracts correctly
 *  - actualizarStock() throws AppException when stock would go negative
 *
 * @package  Es21Plus\Tests
 * @author   Carlos Vico
 * @author   miguelrechefdez
 */

use PHPUnit\Framework\TestCase;

class ProductoTest extends TestCase
{
    private Producto $model;
    private PDO      $pdo;

    /** IDs created during each test — cleaned up in tearDown. */
    private array $createdIds = [];

    protected function setUp(): void
    {
        $this->model = new Producto();
        $this->pdo   = Database::getInstance();
    }

    protected function tearDown(): void
    {
        if (!$this->createdIds) return;
        $ids = implode(',', array_map('intval', $this->createdIds));
        $this->pdo->exec("DELETE FROM movimientos WHERE producto_id IN ({$ids})");
        $this->pdo->exec("DELETE FROM productos   WHERE id          IN ({$ids}) AND nombre LIKE 'TEST_%'");
        $this->createdIds = [];
    }

    // ── Helpers ───────────────────────────────────────────────

    /** Insert a disposable product and track its ID for cleanup. */
    private function insertProduct(array $overrides = []): int
    {
        $defaults = [
            'nombre'      => 'TEST_producto',
            'descripcion' => 'Descripción de prueba',
            'precio'      => 15.99,
            'stock'       => 10,
            'stock_minimo'=> 5,
            'codigo_ref'  => null,
            'categoria_id'=> null,
        ];
        $d = array_merge($defaults, $overrides);

        $this->pdo->prepare(
            "INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, codigo_ref, categoria_id)
             VALUES (:nombre, :descripcion, :precio, :stock, :stock_minimo, :codigo_ref, :categoria_id)"
        )->execute($d);

        $id = (int) $this->pdo->lastInsertId();
        $this->createdIds[] = $id;
        return $id;
    }

    // ── listar ────────────────────────────────────────────────

    /** @test */
    public function listar_returns_array(): void
    {
        $result = $this->model->listar();
        $this->assertIsArray($result);
    }

    /** @test */
    public function listar_excludes_soft_deleted_products(): void
    {
        $id = $this->insertProduct(['nombre' => 'TEST_soft_del']);
        $this->pdo->prepare("UPDATE productos SET deleted_at = NOW() WHERE id = :id")
                  ->execute([':id' => $id]);

        $ids = array_column($this->model->listar(), 'id');
        $this->assertNotContains($id, $ids);
    }

    /** @test */
    public function listar_includes_active_products(): void
    {
        $id = $this->insertProduct(['nombre' => 'TEST_activo']);

        $ids = array_column($this->model->listar(), 'id');
        $this->assertContains($id, $ids);
    }

    // ── obtener ───────────────────────────────────────────────

    /** @test */
    public function obtener_returns_correct_product(): void
    {
        $id = $this->insertProduct(['nombre' => 'TEST_obtener', 'precio' => 42.00]);

        $row = $this->model->obtener($id);

        $this->assertSame($id, (int) $row['id']);
        $this->assertSame('TEST_obtener', $row['nombre']);
        $this->assertEquals(42.00, (float) $row['precio']);
    }

    /** @test */
    public function obtener_throws_for_nonexistent_id(): void
    {
        $this->expectException(AppException::class);
        $this->model->obtener(999999);
    }

    /** @test */
    public function obtener_throws_for_soft_deleted_product(): void
    {
        $id = $this->insertProduct(['nombre' => 'TEST_del_obtener']);
        $this->pdo->prepare("UPDATE productos SET deleted_at = NOW() WHERE id = :id")
                  ->execute([':id' => $id]);

        $this->expectException(AppException::class);
        $this->model->obtener($id);
    }

    // ── crear ─────────────────────────────────────────────────

    /** @test */
    public function crear_returns_new_integer_id(): void
    {
        $id = $this->model->crear('TEST_crear', 'desc', 9.99, null, 5, 2, 'TST-99');
        $this->createdIds[] = $id;

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    /** @test */
    public function crear_persists_all_fields(): void
    {
        $id = $this->model->crear('TEST_campos', 'mi descripcion', 7.50, null, 3, 1, 'TST-88');
        $this->createdIds[] = $id;

        $row = $this->model->obtener($id);
        $this->assertSame('TEST_campos', $row['nombre']);
        $this->assertEquals(7.50, (float) $row['precio']);
        $this->assertSame(3, (int) $row['stock']);
        $this->assertSame(1, (int) $row['stock_minimo']);
        $this->assertSame('TST-88', $row['codigo_ref']);
    }

    // ── actualizar ────────────────────────────────────────────

    /** @test */
    public function actualizar_modifies_fields(): void
    {
        $id = $this->insertProduct(['nombre' => 'TEST_actualizar', 'precio' => 10.00]);

        $this->model->actualizar($id, 'TEST_actualizar_v2', 'nueva desc', 20.00, null, 3, null);

        $row = $this->model->obtener($id);
        $this->assertSame('TEST_actualizar_v2', $row['nombre']);
        $this->assertEquals(20.00, (float) $row['precio']);
    }

    // ── buscar ────────────────────────────────────────────────

    /** @test */
    public function buscar_finds_product_by_name(): void
    {
        $id = $this->insertProduct(['nombre' => 'TEST_buscar_unique_xyz']);

        $results = $this->model->buscar('buscar_unique_xyz');
        $ids     = array_column($results, 'id');

        $this->assertContains($id, $ids);
    }

    /** @test */
    public function buscar_returns_empty_for_no_match(): void
    {
        $results = $this->model->buscar('zzz_no_existe_jamas_99999');
        $this->assertEmpty($results);
    }

    // ── filtrarStockBajo ──────────────────────────────────────

    /** @test */
    public function filtrar_stock_bajo_includes_product_at_minimum(): void
    {
        $id = $this->insertProduct([
            'nombre'      => 'TEST_stock_min',
            'stock'       => 3,
            'stock_minimo'=> 5,
        ]);

        $ids = array_column($this->model->filtrarStockBajo(), 'id');
        $this->assertContains($id, $ids);
    }

    /** @test */
    public function filtrar_stock_bajo_excludes_product_above_minimum(): void
    {
        $id = $this->insertProduct([
            'nombre'      => 'TEST_stock_ok',
            'stock'       => 20,
            'stock_minimo'=> 5,
        ]);

        $ids = array_column($this->model->filtrarStockBajo(), 'id');
        $this->assertNotContains($id, $ids);
    }

    // ── actualizarStock ───────────────────────────────────────

    /** @test */
    public function actualizar_stock_increments_correctly(): void
    {
        $id = $this->insertProduct(['nombre' => 'TEST_stock_inc', 'stock' => 10]);

        $this->model->actualizarStock($id, 5);

        $row = $this->model->obtener($id);
        $this->assertSame(15, (int) $row['stock']);
    }

    /** @test */
    public function actualizar_stock_decrements_correctly(): void
    {
        $id = $this->insertProduct(['nombre' => 'TEST_stock_dec', 'stock' => 10]);

        $this->model->actualizarStock($id, -3);

        $row = $this->model->obtener($id);
        $this->assertSame(7, (int) $row['stock']);
    }

    /** @test */
    public function actualizar_stock_throws_when_result_would_be_negative(): void
    {
        $id = $this->insertProduct(['nombre' => 'TEST_stock_neg', 'stock' => 2]);

        $this->expectException(AppException::class);
        $this->model->actualizarStock($id, -5);
    }
}
