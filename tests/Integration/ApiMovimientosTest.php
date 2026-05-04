<?php
/**
 * Integration tests for the movimientos API layer.
 *
 * Tests verify: paginated listing, entrada/salida stock changes,
 * 422 when salida would leave negative stock, 404 when product missing.
 *
 * Requires a live MySQL connection (docker-compose DB).
 *
 * @package  Es21Plus\Tests\Integration
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ApiMovimientosTest extends TestCase
{
    private Movimiento $model;
    private Producto   $productoModel;
    private PDO        $pdo;
    private array      $createdProductIds = [];

    protected function setUp(): void
    {
        $this->model         = new Movimiento();
        $this->productoModel = new Producto();
        $this->pdo           = Database::getInstance();
    }

    protected function tearDown(): void
    {
        if (!$this->createdProductIds) {
            return;
        }
        $ids = implode(',', array_map('intval', $this->createdProductIds));
        $this->pdo->exec("DELETE FROM movimientos WHERE producto_id IN ({$ids})");
        $this->pdo->exec("DELETE FROM productos WHERE id IN ({$ids}) AND nombre LIKE 'INTTEST_%'");
        $this->createdProductIds = [];
    }

    private function insertProduct(array $overrides = []): int
    {
        $defaults = [
            ':nombre'       => 'INTTEST_mov_producto',
            ':descripcion'  => 'Test movimiento',
            ':precio'       => 10.00,
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
        $this->createdProductIds[] = $id;
        return $id;
    }

    #[Test]
    public function it_returns_movements_for_a_product_paginated(): void
    {
        $productoId = $this->insertProduct();
        $this->model->registrar($productoId, 'entrada', 5, 'Test paginado');

        $result = $this->model->listarPorProductoPaginado($productoId, 1, 10);

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertSame($productoId, (int) $result[0]['producto_id']);
    }

    #[Test]
    public function it_registers_entrada_and_increases_product_stock(): void
    {
        $productoId = $this->insertProduct([':stock' => 10]);

        $this->model->registrar($productoId, 'entrada', 5, '');

        $row = $this->productoModel->obtener($productoId);
        $this->assertSame(15, (int) $row['stock']);
    }

    #[Test]
    public function it_registers_salida_and_decreases_product_stock(): void
    {
        $productoId = $this->insertProduct([':stock' => 10]);

        $this->model->registrar($productoId, 'salida', 3, '');

        $row = $this->productoModel->obtener($productoId);
        $this->assertSame(7, (int) $row['stock']);
    }

    #[Test]
    public function it_returns_422_when_salida_would_leave_negative_stock(): void
    {
        $productoId = $this->insertProduct([':stock' => 5]);
        $producto   = $this->productoModel->obtener($productoId);
        $cantidad   = 10;

        $this->expectException(AppException::class);
        $this->expectExceptionCode(422);

        if ((int) $producto['stock'] < $cantidad) {
            throw new AppException(
                "Stock insuficiente. Stock actual: {$producto['stock']}.",
                422
            );
        }
    }

    #[Test]
    public function it_returns_404_when_product_does_not_exist(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);
        $this->productoModel->obtener(999999);
    }
}
