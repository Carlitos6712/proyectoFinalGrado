<?php
/**
 * Unit + integration tests for Movimiento model.
 *
 * Covers:
 *  - registrar() inserts movement and returns ID
 *  - registrar() updates product stock (entrada/salida)
 *  - registrar() throws on invalid tipo
 *  - registrar() throws on cantidad <= 0
 *  - registrar() throws when salida would cause negative stock
 *  - listarPorProducto() returns movements for given product
 *  - resumenStock() returns correct entradas/salidas/balance
 *  - contarEsteMes() returns integer >= 0
 *  - ultimosMovimientos() respects limit
 *
 * @package  Es21Plus\Tests
 * @author   Carlos Vico
 * @author   miguelrechefdez
 */

use PHPUnit\Framework\TestCase;

class MovimientoTest extends TestCase
{
    private Movimiento $model;
    private Producto   $productoModel;
    private PDO        $pdo;
    private int        $productoId;

    protected function setUp(): void
    {
        $this->model        = new Movimiento();
        $this->productoModel = new Producto();
        $this->pdo          = Database::getInstance();

        $this->pdo->exec("
            INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo)
            VALUES ('TEST_MOV_producto', 'Para tests Movimiento', 19.99, 20, 3)
        ");
        $this->productoId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare("DELETE FROM movimientos WHERE producto_id = :id")
                  ->execute([':id' => $this->productoId]);
        $this->pdo->prepare("DELETE FROM productos WHERE id = :id AND nombre LIKE 'TEST_%'")
                  ->execute([':id' => $this->productoId]);
    }

    // ── registrar ─────────────────────────────────────────────

    /** @test */
    public function registrar_returns_integer_id(): void
    {
        $id = $this->model->registrar($this->productoId, 'entrada', 5, 'test entrada');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    /** @test */
    public function registrar_entrada_increases_stock(): void
    {
        $antes = (int) $this->productoModel->obtener($this->productoId)['stock'];

        $this->model->registrar($this->productoId, 'entrada', 8, '');

        $despues = (int) $this->productoModel->obtener($this->productoId)['stock'];
        $this->assertSame($antes + 8, $despues);
    }

    /** @test */
    public function registrar_salida_decreases_stock(): void
    {
        $antes = (int) $this->productoModel->obtener($this->productoId)['stock'];

        $this->model->registrar($this->productoId, 'salida', 4, '');

        $despues = (int) $this->productoModel->obtener($this->productoId)['stock'];
        $this->assertSame($antes - 4, $despues);
    }

    /** @test */
    public function registrar_throws_on_invalid_tipo(): void
    {
        $this->expectException(AppException::class);
        $this->model->registrar($this->productoId, 'transferencia', 1, '');
    }

    /** @test */
    public function registrar_throws_on_zero_cantidad(): void
    {
        $this->expectException(AppException::class);
        $this->model->registrar($this->productoId, 'entrada', 0, '');
    }

    /** @test */
    public function registrar_throws_on_negative_cantidad(): void
    {
        $this->expectException(AppException::class);
        $this->model->registrar($this->productoId, 'entrada', -3, '');
    }

    /** @test */
    public function registrar_salida_throws_when_stock_insufficient(): void
    {
        // Product has 20 stock; try to take out 100
        $this->expectException(AppException::class);
        $this->model->registrar($this->productoId, 'salida', 100, '');
    }

    // ── listarPorProducto ─────────────────────────────────────

    /** @test */
    public function listar_por_producto_returns_movements_for_product(): void
    {
        $this->model->registrar($this->productoId, 'entrada', 2, 'mov A');
        $this->model->registrar($this->productoId, 'salida',  1, 'mov B');

        $movs = $this->model->listarPorProducto($this->productoId);

        $this->assertCount(2, $movs);
    }

    /** @test */
    public function listar_por_producto_returns_empty_for_no_movements(): void
    {
        $movs = $this->model->listarPorProducto($this->productoId);
        $this->assertEmpty($movs);
    }

    // ── resumenStock ──────────────────────────────────────────

    /** @test */
    public function resumen_stock_calculates_correct_balance(): void
    {
        $this->model->registrar($this->productoId, 'entrada', 10, '');
        $this->model->registrar($this->productoId, 'entrada',  5, '');
        $this->model->registrar($this->productoId, 'salida',   3, '');

        $resumen = $this->model->resumenStock($this->productoId);

        $this->assertSame(15, (int) $resumen['entradas']);
        $this->assertSame(3,  (int) $resumen['salidas']);
        $this->assertSame(12, (int) $resumen['balance']);
    }

    /** @test */
    public function resumen_stock_returns_zeros_for_no_movements(): void
    {
        $resumen = $this->model->resumenStock($this->productoId);

        $this->assertSame(0, (int) $resumen['entradas']);
        $this->assertSame(0, (int) $resumen['salidas']);
        $this->assertSame(0, (int) $resumen['balance']);
    }

    // ── contarEsteMes ─────────────────────────────────────────

    /** @test */
    public function contar_este_mes_returns_non_negative_integer(): void
    {
        $count = $this->model->contarEsteMes();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /** @test */
    public function contar_este_mes_increments_after_registrar(): void
    {
        $antes = $this->model->contarEsteMes();

        $this->model->registrar($this->productoId, 'entrada', 1, '');

        $this->assertSame($antes + 1, $this->model->contarEsteMes());
    }

    // ── ultimosMovimientos ────────────────────────────────────

    /** @test */
    public function ultimos_movimientos_respects_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->model->registrar($this->productoId, 'entrada', 1, "mov {$i}");
        }

        $movs = $this->model->ultimosMovimientos(3);
        $this->assertCount(3, $movs);
    }

    /** @test */
    public function ultimos_movimientos_returns_most_recent_first(): void
    {
        $this->model->registrar($this->productoId, 'entrada', 1, 'primero');
        $this->model->registrar($this->productoId, 'entrada', 2, 'segundo');

        $movs = $this->model->ultimosMovimientos(2);
        $this->assertSame('segundo', $movs[0]['observaciones']);
    }
}
