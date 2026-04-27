<?php
/**
 * Unit + integration tests for Categoria model.
 *
 * Covers:
 *  - listar() returns array with total_productos count
 *  - obtenerPorId() returns correct category
 *  - obtenerPorId() throws 404 for nonexistent ID
 *  - crear() inserts and returns new ID
 *  - actualizar() modifies name and descripcion
 *  - eliminar() hard-deletes a category with no active products
 *  - eliminar() throws 409 when category has active products
 *
 * @package  Es21Plus\Tests
 * @author   Carlos Vico
 * @author   miguelrechefdez
 */

use PHPUnit\Framework\TestCase;

class CategoriaTest extends TestCase
{
    private Categoria $model;
    private PDO       $pdo;

    /** IDs created during each test — cleaned up in tearDown. */
    private array $catIds     = [];
    private array $prodIds    = [];

    protected function setUp(): void
    {
        $this->model = new Categoria();
        $this->pdo   = Database::getInstance();
    }

    protected function tearDown(): void
    {
        foreach ($this->prodIds as $pid) {
            $this->pdo->prepare("DELETE FROM movimientos WHERE producto_id = :id")->execute([':id' => $pid]);
            $this->pdo->prepare("DELETE FROM productos WHERE id = :id")->execute([':id' => $pid]);
        }
        foreach ($this->catIds as $cid) {
            $this->pdo->prepare("DELETE FROM categorias WHERE id = :id AND nombre LIKE 'TEST_%'")->execute([':id' => $cid]);
        }
        $this->catIds  = [];
        $this->prodIds = [];
    }

    // ── Helpers ───────────────────────────────────────────────

    private function createCategoria(string $nombre = 'TEST_cat', string $desc = ''): int
    {
        $id = $this->model->crear($nombre, $desc);
        $this->catIds[] = $id;
        return $id;
    }

    private function attachProducto(int $catId): int
    {
        $this->pdo->prepare(
            "INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, categoria_id)
             VALUES ('TEST_cat_prod', 'prod para test cat', 5.00, 1, 1, :cat_id)"
        )->execute([':cat_id' => $catId]);
        $id = (int) $this->pdo->lastInsertId();
        $this->prodIds[] = $id;
        return $id;
    }

    // ── listar ────────────────────────────────────────────────

    /** @test */
    public function listar_returns_array(): void
    {
        $this->assertIsArray($this->model->listar());
    }

    /** @test */
    public function listar_includes_total_productos_key(): void
    {
        $cats = $this->model->listar();
        $this->assertNotEmpty($cats);
        $this->assertArrayHasKey('total_productos', $cats[0]);
    }

    /** @test */
    public function listar_total_productos_counts_only_active(): void
    {
        $catId = $this->createCategoria('TEST_listar_count');
        $prodId = $this->attachProducto($catId);

        $cats  = $this->model->listar();
        $found = array_filter($cats, fn($c) => (int)$c['id'] === $catId);
        $cat   = array_values($found)[0];

        $this->assertSame(1, (int) $cat['total_productos']);

        // Soft-delete the product — count should drop
        $this->pdo->prepare("UPDATE productos SET deleted_at = NOW() WHERE id = :id")
                  ->execute([':id' => $prodId]);

        $cats2  = $this->model->listar();
        $found2 = array_filter($cats2, fn($c) => (int)$c['id'] === $catId);
        $cat2   = array_values($found2)[0];
        $this->assertSame(0, (int) $cat2['total_productos']);
    }

    // ── obtenerPorId ──────────────────────────────────────────

    /** @test */
    public function obtener_por_id_returns_correct_category(): void
    {
        $id = $this->createCategoria('TEST_obtener_cat');

        $row = $this->model->obtenerPorId($id);

        $this->assertSame($id, (int) $row['id']);
        $this->assertSame('TEST_obtener_cat', $row['nombre']);
    }

    /** @test */
    public function obtener_por_id_throws_for_nonexistent_id(): void
    {
        $this->expectException(AppException::class);
        $this->model->obtenerPorId(999999);
    }

    // ── crear ─────────────────────────────────────────────────

    /** @test */
    public function crear_returns_positive_integer_id(): void
    {
        $id = $this->createCategoria('TEST_crear_cat');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    /** @test */
    public function crear_persists_nombre_and_descripcion(): void
    {
        $id = $this->createCategoria('TEST_persist_cat', 'mi descripción');

        $row = $this->model->obtenerPorId($id);
        $this->assertSame('TEST_persist_cat', $row['nombre']);
        $this->assertSame('mi descripción', $row['descripcion']);
    }

    // ── actualizar ────────────────────────────────────────────

    /** @test */
    public function actualizar_modifies_nombre_and_descripcion(): void
    {
        $id = $this->createCategoria('TEST_act_before');

        $this->model->actualizar($id, 'TEST_act_after', 'nueva desc');

        $row = $this->model->obtenerPorId($id);
        $this->assertSame('TEST_act_after', $row['nombre']);
        $this->assertSame('nueva desc', $row['descripcion']);
    }

    // ── eliminar ──────────────────────────────────────────────

    /** @test */
    public function eliminar_removes_category_with_no_products(): void
    {
        $id = $this->createCategoria('TEST_eliminar_cat');

        $result = $this->model->eliminar($id);

        $this->assertTrue($result);
        $this->expectException(AppException::class);
        $this->model->obtenerPorId($id);
    }

    /** @test */
    public function eliminar_throws_when_category_has_active_products(): void
    {
        $catId = $this->createCategoria('TEST_eliminar_con_prod');
        $this->attachProducto($catId);

        $this->expectException(AppException::class);
        $this->model->eliminar($catId);
    }
}
