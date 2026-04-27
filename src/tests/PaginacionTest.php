<?php
/**
 * Integration tests for pagination methods (Issue #4).
 *
 * Covers:
 *  - Producto::listarPaginado() returns correct page slice
 *  - Producto::listarPaginado() respects porPagina limit
 *  - Producto::listarPaginado() filters by termino
 *  - Producto::listarPaginado() filters by categoriaId
 *  - Producto::contarFiltrados() counts correctly with and without filters
 *  - Movimiento::listarPorProductoPaginado() returns correct page slice
 *  - Movimiento::listarPorProductoPaginado() respects porPagina limit
 *  - Movimiento::contarPorProducto() returns correct count
 *
 * @package  Es21Plus\Tests
 * @author   Carlos Vico
 * @author   miguelrechefdez
 */

use PHPUnit\Framework\TestCase;

class PaginacionTest extends TestCase
{
    private Producto   $productoModel;
    private Movimiento $movimientoModel;
    private PDO        $pdo;

    private array $productoIds = [];

    protected function setUp(): void
    {
        $this->productoModel   = new Producto();
        $this->movimientoModel = new Movimiento();
        $this->pdo             = Database::getInstance();
    }

    protected function tearDown(): void
    {
        if (!$this->productoIds) return;
        $ids = implode(',', array_map('intval', $this->productoIds));
        $this->pdo->exec("DELETE FROM movimientos WHERE producto_id IN ({$ids})");
        $this->pdo->exec("DELETE FROM productos   WHERE id          IN ({$ids}) AND nombre LIKE 'TEST_%'");
        $this->productoIds = [];
    }

    // ── Helpers ───────────────────────────────────────────────

    private function makeProduct(string $nombre = 'TEST_pag', ?int $catId = null): int
    {
        $this->pdo->prepare(
            "INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, categoria_id)
             VALUES (:nombre, 'pag test', 1.00, 10, 1, :cat_id)"
        )->execute([':nombre' => $nombre, ':cat_id' => $catId]);
        $id = (int) $this->pdo->lastInsertId();
        $this->productoIds[] = $id;
        return $id;
    }

    // ── Producto::listarPaginado ──────────────────────────────

    /** @test */
    public function listar_paginado_page1_returns_first_slice(): void
    {
        // Create 5 products so we can paginate with porPagina=2
        for ($i = 1; $i <= 5; $i++) {
            $this->makeProduct("TEST_pag_slice_{$i}");
        }

        $page1 = $this->productoModel->listarPaginado(1, 2);
        $this->assertCount(2, $page1);
    }

    /** @test */
    public function listar_paginado_page2_returns_different_rows(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->makeProduct("TEST_pag_diff_{$i}");
        }

        $page1 = $this->productoModel->listarPaginado(1, 2);
        $page2 = $this->productoModel->listarPaginado(2, 2);

        $ids1 = array_column($page1, 'id');
        $ids2 = array_column($page2, 'id');

        $this->assertEmpty(array_intersect($ids1, $ids2), 'Pages must not overlap');
    }

    /** @test */
    public function listar_paginado_filters_by_termino(): void
    {
        $id = $this->makeProduct('TEST_pag_filtro_unico_xyz');
        $this->makeProduct('TEST_pag_otro_cualquiera');

        $results = $this->productoModel->listarPaginado(1, 10, 'filtro_unico_xyz');
        $ids     = array_column($results, 'id');

        $this->assertContains($id, $ids);
        $this->assertCount(1, array_filter($ids, fn($i) => $i === $id));
    }

    /** @test */
    public function listar_paginado_filters_by_categoria_id(): void
    {
        // Use categoria 1 (Frenos) which always exists from schema seed
        $id1 = $this->makeProduct('TEST_pag_cat1', 1);
        $id2 = $this->makeProduct('TEST_pag_nocat', null);

        $results = $this->productoModel->listarPaginado(1, 50, null, 1);
        $ids     = array_column($results, 'id');

        $this->assertContains($id1, $ids);
        $this->assertNotContains($id2, $ids);
    }

    /** @test */
    public function listar_paginado_excludes_soft_deleted(): void
    {
        $id = $this->makeProduct('TEST_pag_deleted');
        $this->pdo->prepare("UPDATE productos SET deleted_at = NOW() WHERE id = :id")
                  ->execute([':id' => $id]);

        $results = $this->productoModel->listarPaginado(1, 100);
        $ids     = array_column($results, 'id');

        $this->assertNotContains($id, $ids);
    }

    // ── Producto::contarFiltrados ─────────────────────────────

    /** @test */
    public function contar_filtrados_returns_integer(): void
    {
        $count = $this->productoModel->contarFiltrados();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /** @test */
    public function contar_filtrados_increments_when_product_added(): void
    {
        $antes = $this->productoModel->contarFiltrados();
        $this->makeProduct('TEST_pag_contar');
        $this->assertSame($antes + 1, $this->productoModel->contarFiltrados());
    }

    /** @test */
    public function contar_filtrados_with_termino_matches_listar_count(): void
    {
        $this->makeProduct('TEST_pag_term_alpha');
        $this->makeProduct('TEST_pag_term_alpha');

        $count   = $this->productoModel->contarFiltrados('TEST_pag_term_alpha');
        $results = $this->productoModel->listarPaginado(1, 100, 'TEST_pag_term_alpha');

        $this->assertSame($count, count($results));
    }

    // ── Movimiento::contarPorProducto ─────────────────────────

    /** @test */
    public function contar_por_producto_returns_zero_for_no_movements(): void
    {
        $id = $this->makeProduct('TEST_pag_mov_zero');
        $this->assertSame(0, $this->movimientoModel->contarPorProducto($id));
    }

    /** @test */
    public function contar_por_producto_returns_correct_count(): void
    {
        $id = $this->makeProduct('TEST_pag_mov_count');
        $this->movimientoModel->registrar($id, 'entrada', 5, '');
        $this->movimientoModel->registrar($id, 'entrada', 3, '');
        $this->movimientoModel->registrar($id, 'salida',  2, '');
        $this->assertSame(3, $this->movimientoModel->contarPorProducto($id));
    }

    // ── Movimiento::listarPorProductoPaginado ─────────────────

    /** @test */
    public function listar_movimientos_paginado_returns_correct_slice(): void
    {
        $id = $this->makeProduct('TEST_pag_mov_slice');
        for ($i = 0; $i < 5; $i++) {
            $this->movimientoModel->registrar($id, 'entrada', 1, "mov {$i}");
        }

        $page1 = $this->movimientoModel->listarPorProductoPaginado($id, 1, 2);
        $this->assertCount(2, $page1);
    }

    /** @test */
    public function listar_movimientos_paginado_pages_dont_overlap(): void
    {
        $id = $this->makeProduct('TEST_pag_mov_overlap');
        for ($i = 0; $i < 4; $i++) {
            $this->movimientoModel->registrar($id, 'entrada', 1, "mov {$i}");
        }

        $p1ids = array_column($this->movimientoModel->listarPorProductoPaginado($id, 1, 2), 'id');
        $p2ids = array_column($this->movimientoModel->listarPorProductoPaginado($id, 2, 2), 'id');

        $this->assertEmpty(array_intersect($p1ids, $p2ids));
    }

    /** @test */
    public function listar_movimientos_paginado_most_recent_first(): void
    {
        $id = $this->makeProduct('TEST_pag_mov_order');

        // Insert with explicit timestamps to guarantee order regardless of test speed
        $this->pdo->prepare(
            "INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, fecha)
             VALUES (:id, 'entrada', 1, 'primero', '2025-01-01 10:00:00')"
        )->execute([':id' => $id]);
        $this->pdo->prepare(
            "INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, fecha)
             VALUES (:id, 'entrada', 1, 'segundo', '2025-01-02 10:00:00')"
        )->execute([':id' => $id]);

        $movs = $this->movimientoModel->listarPorProductoPaginado($id, 1, 10);
        $this->assertSame('segundo', $movs[0]['observaciones']);
    }
}
