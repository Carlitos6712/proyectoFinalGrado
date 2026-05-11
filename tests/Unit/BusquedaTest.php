<?php
/**
 * Tests unitarios para el query builder de búsqueda avanzada.
 *
 * Verifica que listarPaginado() y contarFiltrados() de Producto
 * construyen correctamente las cláusulas WHERE con prepared statements,
 * combinan filtros, respetan ordenación y excluyen soft-deleted.
 *
 * Usa SQLite en memoria: sin depender de la DB MySQL de Docker.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BusquedaTest extends TestCase
{
    private PDO     $pdo;
    private Producto $model;

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->crearSchema();

        $auditoria   = new Auditoria($this->pdo);
        $this->model = new Producto($this->pdo, $auditoria);
    }

    // ── Schema SQLite en memoria ──────────────────────────────────────────────

    /**
     * Crea las tablas necesarias para las búsquedas (incluye JOINs con marcas y categorias).
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
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT    NOT NULL
            )
        ");
        $this->pdo->exec("
            CREATE TABLE categorias (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT    NOT NULL
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
                deleted_at       TEXT    DEFAULT NULL,
                created_at       TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at       TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE movimientos (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                producto_id INTEGER NOT NULL,
                tipo        TEXT    NOT NULL,
                cantidad    INTEGER NOT NULL
            )
        ");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Inserta un producto de prueba y devuelve su ID.
     *
     * @param array<string, mixed> $overrides
     * @return int
     */
    private function insertarProducto(array $overrides = []): int
    {
        $defaults = [
            'nombre'       => 'SRCH_producto',
            'descripcion'  => 'Test búsqueda',
            'precio'       => 10.00,
            'stock'        => 20,
            'stock_minimo' => 5,
            'codigo_ref'   => null,
            'categoria_id' => null,
        ];
        $d = array_merge($defaults, $overrides);

        $codigoRef   = $d['codigo_ref']   ? "'{$d['codigo_ref']}'"   : 'NULL';
        $categoriaId = $d['categoria_id'] ? (int) $d['categoria_id'] : 'NULL';

        $this->pdo->exec(
            "INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, codigo_ref, categoria_id)
             VALUES ('{$d['nombre']}', '{$d['descripcion']}', {$d['precio']}, {$d['stock']}, {$d['stock_minimo']}, {$codigoRef}, {$categoriaId})"
        );
        return (int) $this->pdo->lastInsertId();
    }

    // ── 1. Filtro por nombre con prepared statements ───────────────────────────

    #[Test]
    public function it_builds_query_with_name_filter_using_prepared_statements(): void
    {
        $id = $this->insertarProducto(['nombre' => 'SRCH_filtroNombre_unico_xyz']);

        $results = $this->model->listarPaginado(1, 50, 'filtroNombre_unico_xyz');
        $ids     = array_column($results, 'id');

        $this->assertContains($id, $ids, 'listarPaginado debe encontrar el producto por nombre');
    }

    // ── 2. Combinación de filtro categoría + rango de precio ──────────────────

    #[Test]
    public function it_combines_categoria_and_precio_range_filters(): void
    {
        $this->pdo->exec("INSERT INTO categorias (nombre) VALUES ('SRCH_Cat_Test')");
        $catId = (int) $this->pdo->lastInsertId();

        $idDentro = $this->insertarProducto(['nombre' => 'SRCH_combo_dentro',  'precio' => 50.00,  'categoria_id' => $catId]);
        $idFuera1 = $this->insertarProducto(['nombre' => 'SRCH_combo_precio',  'precio' => 200.00, 'categoria_id' => $catId]);
        $idFuera2 = $this->insertarProducto(['nombre' => 'SRCH_combo_cat',     'precio' => 50.00,  'categoria_id' => null]);

        $results = $this->model->listarPaginado(1, 50, null, $catId, 10.0, 100.0);
        $ids     = array_column($results, 'id');

        $this->assertContains($idDentro,    $ids, 'Producto dentro del rango y categoría debe aparecer');
        $this->assertNotContains($idFuera1, $ids, 'Producto fuera del rango de precio no debe aparecer');
        $this->assertNotContains($idFuera2, $ids, 'Producto de otra categoría no debe aparecer');
    }

    // ── 3. Filtro stock_bajo ──────────────────────────────────────────────────

    #[Test]
    public function it_applies_stock_bajo_filter_correctly(): void
    {
        $idBajo = $this->insertarProducto(['nombre' => 'SRCH_bajo_stock',  'stock' => 2,  'stock_minimo' => 5]);
        $idOk   = $this->insertarProducto(['nombre' => 'SRCH_stock_sufic', 'stock' => 20, 'stock_minimo' => 5]);

        $results = $this->model->listarPaginado(1, 100, null, null, null, null, null, null, 'nombre_asc', true);
        $ids     = array_column($results, 'id');

        $this->assertContains($idBajo,   $ids, 'Producto con stock bajo debe aparecer con el filtro activo');
        $this->assertNotContains($idOk,  $ids, 'Producto con stock suficiente no debe aparecer');
    }

    // ── 4. Ordenación precio ascendente ───────────────────────────────────────

    #[Test]
    public function it_orders_results_by_precio_ascending(): void
    {
        $idBarato = $this->insertarProducto(['nombre' => 'SRCH_ord_barato', 'precio' => 5.00]);
        $idCaro   = $this->insertarProducto(['nombre' => 'SRCH_ord_caro',   'precio' => 500.00]);

        $results = $this->model->listarPaginado(1, 200, 'SRCH_ord', null, null, null, null, null, 'precio_asc');
        $ids     = array_column($results, 'id');

        $posBarato = array_search($idBarato, $ids, true);
        $posCaro   = array_search($idCaro,   $ids, true);

        $this->assertNotFalse($posBarato);
        $this->assertNotFalse($posCaro);
        $this->assertLessThan($posCaro, $posBarato, 'El producto más barato debe aparecer antes con precio_asc');
    }

    // ── 5. Ordenación precio descendente ─────────────────────────────────────

    #[Test]
    public function it_orders_results_by_precio_descending(): void
    {
        $idBarato = $this->insertarProducto(['nombre' => 'SRCH_desc_barato', 'precio' => 1.00]);
        $idCaro   = $this->insertarProducto(['nombre' => 'SRCH_desc_caro',   'precio' => 999.00]);

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
        $idA = $this->insertarProducto(['nombre' => 'SRCH_alfa_AAA']);
        $idZ = $this->insertarProducto(['nombre' => 'SRCH_alfa_ZZZ']);

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
        $id = $this->insertarProducto(['nombre' => 'SRCH_softdel_xyz']);
        $this->pdo->exec("UPDATE productos SET deleted_at = CURRENT_TIMESTAMP WHERE id = {$id}");

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

    // ── 9. Paginación ─────────────────────────────────────────────────────────

    #[Test]
    public function it_paginates_results_correctly(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertarProducto(['nombre' => "SRCH_pag_{$i}"]);
        }

        $pagina1 = $this->model->listarPaginado(1, 3, 'SRCH_pag');
        $pagina2 = $this->model->listarPaginado(2, 3, 'SRCH_pag');

        $this->assertCount(3, $pagina1, 'La primera página debe tener 3 registros');
        $this->assertCount(2, $pagina2, 'La segunda página debe tener 2 registros');
        $this->assertEmpty(
            array_intersect(array_column($pagina1, 'id'), array_column($pagina2, 'id')),
            'Las páginas no deben solaparse'
        );
    }

    // ── 10. contarFiltrados coherente con listarPaginado ─────────────────────

    #[Test]
    public function it_returns_consistent_count_and_list_results(): void
    {
        $this->insertarProducto(['nombre' => 'SRCH_cons_1', 'precio' => 50.00]);
        $this->insertarProducto(['nombre' => 'SRCH_cons_2', 'precio' => 75.00]);
        $this->insertarProducto(['nombre' => 'SRCH_cons_3', 'precio' => 200.00]);

        $total   = $this->model->contarFiltrados('SRCH_cons', null, 40.0, 100.0);
        $results = $this->model->listarPaginado(1, 100, 'SRCH_cons', null, 40.0, 100.0);

        $this->assertSame($total, count($results), 'contarFiltrados debe coincidir con el total de listarPaginado');
    }
}
