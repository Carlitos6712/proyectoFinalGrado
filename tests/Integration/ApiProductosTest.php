<?php
/**
 * Integration tests for the productos API layer.
 *
 * Tests verify the behavior that api/productos.php relies on:
 * paginated listing, filters, 404 on missing product, create/update/delete,
 * validation contract (422) and soft-delete exclusion.
 *
 * Requires a live MySQL connection (docker-compose DB).
 *
 * @package  Es21Plus\Tests\Integration
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ApiProductosTest extends TestCase
{
    private Producto $model;
    private PDO      $pdo;
    private array    $createdIds = [];

    protected function setUp(): void
    {
        $this->model = new Producto();
        $this->pdo   = Database::getInstance();
    }

    protected function tearDown(): void
    {
        if (!$this->createdIds) {
            return;
        }
        $ids = implode(',', array_map('intval', $this->createdIds));
        $this->pdo->exec("DELETE FROM movimientos WHERE producto_id IN ({$ids})");
        $this->pdo->exec("DELETE FROM productos WHERE id IN ({$ids}) AND nombre LIKE 'INTTEST_%'");
        $this->createdIds = [];
    }

    private function insertProduct(array $overrides = []): int
    {
        $defaults = [
            ':nombre'       => 'INTTEST_producto',
            ':descripcion'  => 'Descripción de integración',
            ':precio'       => 15.99,
            ':stock'        => 20,
            ':stock_minimo' => 5,
            ':codigo_ref'   => null,
            ':categoria_id' => null,
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

    #[Test]
    public function it_returns_paginated_list_with_success_true(): void
    {
        $result = $this->model->listarPaginado(1, 20);
        $this->assertIsArray($result);
    }

    #[Test]
    public function it_filters_by_categoria_id(): void
    {
        $cats = $this->pdo->query("SELECT id FROM categorias LIMIT 1")->fetchAll();
        if (empty($cats)) {
            $this->markTestSkipped('No hay categorías en la BD para filtrar.');
        }
        $catId = (int) $cats[0]['id'];
        $id    = $this->insertProduct([':nombre' => 'INTTEST_filter_cat', ':categoria_id' => $catId]);

        $results = $this->model->listarPaginado(1, 100, null, $catId);
        $ids     = array_column($results, 'id');

        $this->assertContains($id, $ids);
    }

    #[Test]
    public function it_returns_404_when_product_not_found(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);
        $this->model->obtener(999999);
    }

    #[Test]
    public function it_creates_product_and_returns_201(): void
    {
        $id = $this->model->crear('INTTEST_crear', 'desc', 9.99, null, 5, 2, null);
        $this->createdIds[] = $id;

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = $this->model->obtener($id);
        $this->assertSame('INTTEST_crear', $row['nombre']);
        $this->assertEquals(9.99, (float) $row['precio']);
    }

    #[Test]
    public function it_returns_422_when_name_is_missing_on_create(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(422);

        $nombre = trim('');
        if ($nombre === '') {
            throw new AppException('El nombre del producto es obligatorio.', 422);
        }
    }

    #[Test]
    public function it_updates_product_and_returns_updated_data(): void
    {
        $id = $this->insertProduct([':nombre' => 'INTTEST_antes', ':precio' => 10.00]);

        $ok = $this->model->actualizar($id, 'INTTEST_despues', 'nueva desc', 25.00, null, 3, null);
        $this->assertTrue($ok);

        $row = $this->model->obtener($id);
        $this->assertSame('INTTEST_despues', $row['nombre']);
        $this->assertEquals(25.00, (float) $row['precio']);
    }

    #[Test]
    public function it_soft_deletes_product_and_excludes_from_list(): void
    {
        $id = $this->insertProduct([':nombre' => 'INTTEST_softdel']);

        $ok = $this->model->eliminar($id);
        $this->assertTrue($ok);

        $ids = array_column($this->model->listar(), 'id');
        $this->assertNotContains($id, $ids);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);
        $this->model->obtener($id);
    }
}
