<?php
/**
 * Integration tests for the categorias API layer.
 *
 * Tests verify: listing with product count, create, 409 when deleting
 * a category with active products, and successful deletion of empty category.
 *
 * Requires a live MySQL connection (docker-compose DB).
 *
 * @package  Es21Plus\Tests\Integration
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ApiCategoriasTest extends TestCase
{
    private Categoria $model;
    private PDO       $pdo;
    private array     $createdCatIds     = [];
    private array     $createdProductIds = [];

    protected function setUp(): void
    {
        $this->model = new Categoria();
        $this->pdo   = Database::getInstance();
    }

    protected function tearDown(): void
    {
        if ($this->createdProductIds) {
            $ids = implode(',', array_map('intval', $this->createdProductIds));
            $this->pdo->exec("DELETE FROM movimientos WHERE producto_id IN ({$ids})");
            $this->pdo->exec("DELETE FROM productos WHERE id IN ({$ids})");
            $this->createdProductIds = [];
        }
        if ($this->createdCatIds) {
            $ids = implode(',', array_map('intval', $this->createdCatIds));
            $this->pdo->exec("DELETE FROM categorias WHERE id IN ({$ids}) AND nombre LIKE 'INTTEST_%'");
            $this->createdCatIds = [];
        }
    }

    private function insertCategoria(string $nombre = 'INTTEST_categoria'): int
    {
        $this->pdo->prepare(
            "INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, '')"
        )->execute([':nombre' => $nombre]);
        $id = (int) $this->pdo->lastInsertId();
        $this->createdCatIds[] = $id;
        return $id;
    }

    private function insertProduct(int $catId): int
    {
        $this->pdo->prepare(
            "INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, categoria_id)
             VALUES ('INTTEST_cat_prod', 'test', 10.00, 5, 2, :cat_id)"
        )->execute([':cat_id' => $catId]);
        $id = (int) $this->pdo->lastInsertId();
        $this->createdProductIds[] = $id;
        return $id;
    }

    #[Test]
    public function it_returns_category_list_with_product_count(): void
    {
        $result = $this->model->listar();

        $this->assertIsArray($result);
        if (!empty($result)) {
            $first = $result[0];
            $this->assertArrayHasKey('total_productos', $first);
            $this->assertIsNumeric($first['total_productos']);
        }
    }

    #[Test]
    public function it_creates_category_and_returns_new_id(): void
    {
        $id = $this->model->crear('INTTEST_nueva_cat', 'descripción de prueba');
        $this->createdCatIds[] = $id;

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = $this->model->obtenerPorId($id);
        $this->assertSame('INTTEST_nueva_cat', $row['nombre']);
    }

    #[Test]
    public function it_returns_409_when_deleting_category_with_active_products(): void
    {
        $catId = $this->insertCategoria('INTTEST_cat_con_productos');
        $this->insertProduct($catId);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);
        $this->model->eliminar($catId);
    }

    #[Test]
    public function it_deletes_empty_category_successfully(): void
    {
        $catId = $this->insertCategoria('INTTEST_cat_vacia');

        $ok = $this->model->eliminar($catId);
        $this->assertTrue($ok);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);
        $this->model->obtenerPorId($catId);
    }
}
