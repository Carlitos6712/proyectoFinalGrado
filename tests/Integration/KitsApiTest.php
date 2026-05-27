<?php
/**
 * Integration tests for the kits API layer (MySQL).
 *
 * Tests verify: listing, create (with/without lineas), update,
 * toggle activo (PATCH), getLineas, error handling.
 *
 * Requires a live MySQL connection (docker-compose DB).
 *
 * @package  Es21Plus\Tests\Integration
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class KitsApiTest extends TestCase
{
    private Kit   $model;
    private PDO   $pdo;
    private array $createdKitIds     = [];
    private array $createdProductoIds = [];

    protected function setUp(): void
    {
        $this->model = new Kit();
        $this->pdo   = Database::getInstance();
    }

    protected function tearDown(): void
    {
        if ($this->createdKitIds) {
            $ids = implode(',', array_map('intval', $this->createdKitIds));
            $this->pdo->exec("DELETE FROM kits_lineas WHERE kit_id IN ({$ids})");
            $this->pdo->exec("DELETE FROM kits WHERE id IN ({$ids}) AND nombre LIKE 'INTTEST_%'");
            $this->createdKitIds = [];
        }
        if ($this->createdProductoIds) {
            $ids = implode(',', array_map('intval', $this->createdProductoIds));
            $this->pdo->exec("DELETE FROM productos WHERE id IN ({$ids})");
            $this->createdProductoIds = [];
        }
    }

    /**
     * Inserta un kit de prueba y registra el ID para tearDown.
     *
     * @param string $nombre
     * @param array  $lineas
     * @return int
     * @author Carlitos6712
     */
    private function insertarKit(string $nombre = 'INTTEST_kit', array $lineas = []): int
    {
        $id = $this->model->crear(['nombre' => $nombre], $lineas);
        $this->createdKitIds[] = $id;
        return $id;
    }

    /**
     * Inserta un producto mínimo de prueba.
     *
     * @param string $nombre
     * @return int
     * @author Carlitos6712
     */
    private function insertarProducto(string $nombre = 'INTTEST_producto'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO productos (nombre, stock, precio) VALUES (:nombre, 10, 1.00)"
        );
        $stmt->execute([':nombre' => $nombre]);
        $id = (int) $this->pdo->lastInsertId();
        $this->createdProductoIds[] = $id;
        return $id;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    #[Test]
    public function it_returns_kit_list_as_array(): void
    {
        $result = $this->model->listar(false);
        $this->assertIsArray($result);
    }

    #[Test]
    public function it_creates_kit_without_lineas(): void
    {
        $id = $this->insertarKit('INTTEST_kit_vacio');
        $this->assertGreaterThan(0, $id);

        $kit = $this->model->obtener($id);
        $this->assertSame('INTTEST_kit_vacio', $kit['nombre']);
    }

    #[Test]
    public function it_creates_kit_with_lineas(): void
    {
        $prodId = $this->insertarProducto('INTTEST_prod_kit');
        $id     = $this->insertarKit('INTTEST_kit_con_lineas', [
            ['producto_id' => $prodId, 'cantidad' => 3],
        ]);

        $lineas = $this->model->getLineas($id);
        $this->assertCount(1, $lineas);
        $this->assertSame($prodId, (int)$lineas[0]['producto_id']);
        $this->assertSame(3,       (int)$lineas[0]['cantidad']);
    }

    #[Test]
    public function it_throws_422_when_nombre_is_empty(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(422);
        $this->model->crear(['nombre' => '']);
    }

    #[Test]
    public function it_throws_404_when_kit_not_found(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);
        $this->model->obtener(PHP_INT_MAX);
    }

    #[Test]
    public function it_updates_kit_and_replaces_lineas(): void
    {
        $prod1 = $this->insertarProducto('INTTEST_prod_update1');
        $prod2 = $this->insertarProducto('INTTEST_prod_update2');

        $id = $this->insertarKit('INTTEST_kit_update', [
            ['producto_id' => $prod1, 'cantidad' => 1],
        ]);

        $this->model->actualizar(
            $id,
            ['nombre' => 'INTTEST_kit_update_ok'],
            [['producto_id' => $prod2, 'cantidad' => 5]]
        );

        $kit    = $this->model->obtener($id);
        $lineas = $this->model->getLineas($id);

        $this->assertSame('INTTEST_kit_update_ok', $kit['nombre']);
        $this->assertCount(1, $lineas);
        $this->assertSame($prod2, (int)$lineas[0]['producto_id']);
    }

    #[Test]
    public function it_toggles_kit_activo(): void
    {
        $id     = $this->insertarKit('INTTEST_kit_toggle');
        $kit    = $this->model->obtener($id);
        $this->assertSame(1, (int)$kit['activo']);

        $nuevo  = $this->model->toggleActivo($id);
        $this->assertFalse($nuevo);

        $kit2   = $this->model->obtener($id);
        $this->assertSame(0, (int)$kit2['activo']);
    }

    #[Test]
    public function it_lists_only_activos_by_default(): void
    {
        $activo   = $this->insertarKit('INTTEST_kit_list_activo');
        $inactivo = $this->insertarKit('INTTEST_kit_list_inactivo');
        $this->model->toggleActivo($inactivo); // → inactivo

        $activos = $this->model->listar(true);
        $ids     = array_column($activos, 'id');

        $this->assertContains($activo,   array_map('intval', $ids));
        $this->assertNotContains($inactivo, array_map('intval', $ids));
    }

    #[Test]
    public function it_counts_lineas_correctly(): void
    {
        $p1 = $this->insertarProducto('INTTEST_cnt1');
        $p2 = $this->insertarProducto('INTTEST_cnt2');
        $id = $this->insertarKit('INTTEST_kit_count', [
            ['producto_id' => $p1, 'cantidad' => 1],
            ['producto_id' => $p2, 'cantidad' => 2],
        ]);
        $this->assertSame(2, $this->model->contarLineas($id));
    }

    #[Test]
    public function it_syncs_lineas_replaces_old_ones(): void
    {
        $p1 = $this->insertarProducto('INTTEST_sync1');
        $p2 = $this->insertarProducto('INTTEST_sync2');
        $id = $this->insertarKit('INTTEST_kit_sync', [
            ['producto_id' => $p1, 'cantidad' => 1],
        ]);
        $this->assertSame(1, $this->model->contarLineas($id));

        $this->model->syncLineas($id, [
            ['producto_id' => $p2, 'cantidad' => 3],
        ]);
        $this->assertSame(1, $this->model->contarLineas($id));

        $lineas = $this->model->getLineas($id);
        $this->assertSame($p2, (int)$lineas[0]['producto_id']);
    }
}
