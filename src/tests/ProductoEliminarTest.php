<?php
/**
 * Tests for Producto delete (soft-delete) behaviour.
 *
 * Covers:
 *  - Soft delete sets deleted_at
 *  - Deleted product no longer appears in listar()
 *  - Deleted product raises 404 on obtener()
 *  - Deleting already-deleted product returns false (idempotency)
 *  - contarMovimientos returns correct count
 *  - CSRF token helpers (generateCsrfToken / validateCsrfToken)
 *
 * @package  Es21Plus\Tests
 * @author   Carlitos6712
 * @author   miguelrechefdez
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProductoEliminarTest extends TestCase
{
    private Producto $model;
    private PDO      $pdo;

    /** Fixture: ID of the product created for each test. */
    private int $productoId;

    protected function setUp(): void
    {
        $this->model = new Producto();
        $this->pdo   = Database::getInstance();

        // Insert a disposable test product
        $this->pdo->exec("
            INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo)
            VALUES ('TEST_ELIMINAR', 'Producto de prueba', 9.99, 10, 2)
        ");
        $this->productoId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Hard-delete all test rows to keep DB clean
        $this->pdo->prepare("DELETE FROM movimientos WHERE producto_id = :id")
            ->execute([':id' => $this->productoId]);
        $this->pdo->prepare("DELETE FROM productos WHERE id = :id AND nombre LIKE 'TEST_%'")
            ->execute([':id' => $this->productoId]);
    }

    // ────────────────────────────────────────────────────────────────
    // Soft-delete behaviour
    // ────────────────────────────────────────────────────────────────

    #[Test]
    public function eliminar_sets_deleted_at_timestamp(): void
    {
        $this->model->eliminar($this->productoId);

        $stmt = $this->pdo->prepare(
            "SELECT deleted_at FROM productos WHERE id = :id"
        );
        $stmt->execute([':id' => $this->productoId]);
        $deletedAt = $stmt->fetchColumn();

        $this->assertNotNull($deletedAt, 'deleted_at must be set after eliminar()');
    }

    #[Test]
    public function deleted_product_does_not_appear_in_listar(): void
    {
        $this->model->eliminar($this->productoId);

        $productos = $this->model->listar();
        $ids = array_column($productos, 'id');

        $this->assertNotContains(
            $this->productoId,
            $ids,
            'Soft-deleted product must not appear in listar()'
        );
    }

    #[Test]
    public function deleted_product_throws_on_obtener(): void
    {
        $this->model->eliminar($this->productoId);

        $this->expectException(AppException::class);
        $this->model->obtener($this->productoId);
    }

    #[Test]
    public function eliminar_already_deleted_product_returns_false(): void
    {
        $this->model->eliminar($this->productoId);

        $result = $this->model->eliminar($this->productoId);

        $this->assertFalse(
            $result,
            'Deleting an already-deleted product must return false (no rows affected)'
        );
    }

    // ────────────────────────────────────────────────────────────────
    // contarMovimientos
    // ────────────────────────────────────────────────────────────────

    #[Test]
    public function contar_movimientos_returns_zero_for_new_product(): void
    {
        $count = $this->model->contarMovimientos($this->productoId);
        $this->assertSame(0, $count);
    }

    #[Test]
    public function contar_movimientos_returns_correct_count_after_insert(): void
    {
        // Insert 2 fake movement rows directly
        $stmt = $this->pdo->prepare(
            "INSERT INTO movimientos (producto_id, tipo, cantidad, usuario)
             VALUES (:id, 'entrada', 5, 'test'), (:id2, 'salida', 2, 'test')"
        );
        $stmt->execute([':id' => $this->productoId, ':id2' => $this->productoId]);

        $count = $this->model->contarMovimientos($this->productoId);
        $this->assertSame(2, $count);
    }

    // ────────────────────────────────────────────────────────────────
    // CSRF helpers (will fail until functions are created)
    // ────────────────────────────────────────────────────────────────

    #[Test]
    public function generate_csrf_token_returns_non_empty_string(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = generateCsrfToken('eliminar_producto');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    #[Test]
    public function valid_csrf_token_passes_validation(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = generateCsrfToken('eliminar_producto');
        $valid = validateCsrfToken('eliminar_producto', $token);

        $this->assertTrue($valid, 'A freshly generated token must pass validation');
    }

    #[Test]
    public function invalid_csrf_token_fails_validation(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        generateCsrfToken('eliminar_producto');
        $valid = validateCsrfToken('eliminar_producto', 'bad-token');

        $this->assertFalse($valid, 'A tampered token must fail validation');
    }
}
