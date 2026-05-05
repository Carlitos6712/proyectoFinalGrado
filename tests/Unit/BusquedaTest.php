<?php
/**
 * Tests unitarios para el query builder de búsqueda avanzada.
 *
 * Verifica que listarPaginado() y contarFiltrados() de Producto
 * construyen correctamente las cláusulas WHERE con prepared statements,
 * combinan filtros, respetan ordenación y excluyen soft-deleted.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BusquedaTest extends TestCase
{
    private Producto $model;
    private PDO      $pdo;

    /** IDs creados en cada test, limpiados en tearDown. */
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
        $this->pdo->exec("DELETE FROM productos   WHERE id          IN ({$ids}) AND nombre LIKE 'SRCH_%'");
        $this->createdIds = [];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Inserta un producto de prueba y registra su ID para limpieza. */
    private function insertProduct(array $overrides = []): int
    {
        $defaults = [
            'nombre'      => 'SRCH_producto',
            'descripcion' => 'Test búsqueda',
            'precio'      => 10.00,
            'stock'       => 20,
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

    // ── 1. Filtro por nombre con prepared statements ───────────────────────────

    #[Test]
    public function it_builds_query_with_name_filter_using_prepared_statements(): void
    {
        $id = $this->insertProduct(['nombre' => 'SRCH_filtroNombre_unico_xyz']);

        $results = $this->model->listarPaginado(1, 50, 'filtroNombre_unico_xyz');
        $ids     = array_column($results, 'id');

        $this->assertContains($id, $ids, 'listarPaginado debe encontrar el producto por nombre');
    }

    // ── 2. Combinación de filtro categoría + rango de precio ──────────────────

    #[Test]
    public function it_combines_categoria_and_precio_range_filters(): void
    {
        // Insertar categoría de prueba
        $this->pdo->exec("INSERT INTO categorias (nombre) VALUES ('SRCH_Cat_Test')");
        $catId = (int) $this->pdo->lastInsertId();

        $idDentro  = $this->insertProduct(['nombre' => 'SRCH_combo_dentro',  'precio' => 50.00, 'categoria_id' => $catId]);
        $idFuera1  = $this->insertProduct(['nombre' => 'SRCH_combo_precio',  'precio' => 200.00, 'categoria_id' => $catId]);
        $idFuera2  = $this->insertProduct(['nombre' => 'SRCH_combo_cat',     'precio' => 50.00, 'categoria_id' => null]);

        $results = $this->model->listarPaginado(1, 50, null, $catId, 10.0, 100.0);
        $ids     = array_column($results, 'id');

        $this->assertContains($idDentro, $ids, 'Producto dentro del rango y categoría debe aparecer');
        $this->assertNotContains($idFuera1, $ids, 'Producto fuera del rango de precio no debe aparecer');
        $this->assertNotContains($idFuera2, $ids, 'Producto de otra categoría no debe aparecer');

        $this->pdo->exec("DELETE FROM categorias WHERE id = {$catId}");
    }

    // ── 3. Filtro stock_bajo ──────────────────────────────────────────────────

    #[Test]
    public function it_applies_stock_bajo_filter_correctly(): void
    {
        $idBajo = $this->insertProduct(['nombre' => 'SRCH_bajo_stock',    'stock' => 2,  'stock_minimo' => 5]);
        $idOk   = $this->insertProduct(['nombre' => 'SRCH_stock_sufic',   'stock' => 20, 'stock_minimo' => 5]);

        $results = $this->model->listarPaginado(1, 100, null, null, null, null, null, null, 'nombre_asc', true);
        $ids     = array_column($results, 'id');

        $this->assertContains($idBajo, $ids,    'Producto con stock bajo debe aparecer con el filtro activo');
        $this->assertNotContains($idOk, $ids,   'Producto con stock suficiente no debe aparecer');
    }

    // ── 4. Ordenación precio ascendente ───────────────────────────────────────

    #[Test]
    public function it_orders_results_by_precio_ascending(): void
    {
        $idBarato  = $this->insertProduct(['nombre' => 'SRCH_ord_barato',  'precio' => 5.00]);
        $idCaro    = $this->insertProduct(['nombre' => 'SRCH_ord_caro',    'precio' => 500.00]);

        $results = $this->model->listarPaginado(1, 200, 'SRCH_ord', null, null, null, null, null, 'precio_asc');
        $ids     = array_column($results, 'id');

        $posBarato = array_search($idBarato, $ids, true);
        $posCaro   = array_search($idCaro,   $ids, true);

        $this->assertNotFalse($posBarato, 'Producto barato debe estar en resultados');
        $this->assertNotFalse($posCaro,   'Producto caro debe estar en resultados');
        $this->assertLessThan($posCaro, $posBarato, 'El producto más barato debe aparecer antes con precio_asc');
    }

    // ── 5. Ordenación precio descendente ─────────────────────────────────────

    #[Test]
    public function it_orders_results_by_precio_descending(): void
    {
        $idBarato = $this->insertProduct(['nombre' => 'SRCH_desc_barato', 'precio' => 1.00]);
        $idCaro   = $this->insertProduct(['nombre' => 'SRCH_desc_caro',   'precio' => 999.00]);

        $results = $this->model->listarPaginado(1, 200, 'SRCH_desc', null, null, null, null, null, 'precio_desc');
        $ids     = array_column($results, 'id');

        $posBarato = array_search($idBarato, $ids, true);
        $posCaro   = array_search($idCaro,   $ids, true);

        $this->assertNotFalse($posBarato);
        $this->assertNotFalse($posCaro);
        $this->assertLessThan($posBarato, $posCaro, 'El producto más caro debe aparecer antes con precio_desc');
    }

    // ── 6. Ordenación por nombre alfabético ───────────────────────────────────

    #[Test]
    public function it_orders_results_by_nombre_alphabetically(): void
    {
        $idA = $this->insertProduct(['nombre' => 'SRCH_alfa_AAA']);
        $idZ = $this->insertProduct(['nombre' => 'SRCH_alfa_ZZZ']);

        $results = $this->model->listarPaginado(1, 200, 'SRCH_alfa', null, null, null, null, null, 'nombre_asc');
        $ids     = array_column($results, 'id');

        $posA = array_search($idA, $ids, true);
        $posZ = array_search($idZ, $ids, true);

        $this->assertNotFalse($posA);
        $this->assertNotFalse($posZ);
        $this->assertLessThan($posZ, $posA, 'SRCH_alfa_AAA debe aparecer antes que SRCH_alfa_ZZZ');
    }

    // ── 7. Excluir soft-deleted ───────────────────────────────────────────────

    #[Test]
    public function it_excludes_soft_deleted_products_from_search_results(): void
    {
        $id = $this->insertProduct(['nombre' => 'SRCH_softdel_xyz']);
        $this->pdo->prepare("UPDATE productos SET deleted_at = NOW() WHERE id = :id")
                  ->execute([':id' => $id]);

        $results = $this->model->listarPaginado(1, 200, 'softdel_xyz');
        $ids     = array_column($results, 'id');

        $this->assertNotContains($id, $ids, 'Los productos con soft-delete no deben aparecer en resultados');

        $count = $this->model->contarFiltrados('softdel_xyz');
        $this->assertSame(0, $count, 'contarFiltrados tampoco debe contar soft-deleted');
    }

    // ── 8. Sin resultados ─────────────────────────────────────────────────────

    #[Test]
    public function it_returns_empty_array_when_no_products_match_filters(): void
    {
        $results = $this->model->listarPaginado(1, 20, 'SRCH_termino_que_nunca_existira_99z99');
        $this->assertIsArray($results);
        $this->assertEmpty($results, 'Debe devolver array vacío cuando no hay coincidencias');

        $count = $this->model->contarFiltrados('SRCH_termino_que_nunca_existira_99z99');
        $this->assertSame(0, $count);
    }
}
