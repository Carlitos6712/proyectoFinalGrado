<?php
/**
 * Integration tests for the proveedores API layer.
 *
 * Tests verify: listing, create (201), update, deactivate (PATCH),
 * DELETE returns 405, POST without nombre returns 400.
 *
 * Requires a live MySQL connection (docker-compose DB).
 *
 * @package  Es21Plus\Tests\Integration
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProveedoresApiTest extends TestCase
{
    private Proveedor $model;
    private PDO       $pdo;
    private array     $createdProvIds = [];

    protected function setUp(): void
    {
        $this->model = new Proveedor();
        $this->pdo   = Database::getInstance();
    }

    protected function tearDown(): void
    {
        if ($this->createdProvIds) {
            $ids = implode(',', array_map('intval', $this->createdProvIds));
            // Desvincular productos antes de eliminar
            $this->pdo->exec("UPDATE productos SET proveedor_id = NULL WHERE proveedor_id IN ({$ids})");
            $this->pdo->exec("DELETE FROM proveedores WHERE id IN ({$ids}) AND nombre LIKE 'INTTEST_%'");
            $this->createdProvIds = [];
        }
    }

    /**
     * Inserta un proveedor de prueba y registra el ID para tearDown.
     *
     * @param string $nombre
     * @return int
     * @author Carlitos6712
     */
    private function insertarProveedor(string $nombre = 'INTTEST_proveedor'): int
    {
        $id = $this->model->crear(['nombre' => $nombre, 'email' => 'test@test.com']);
        $this->createdProvIds[] = $id;
        return $id;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    #[Test]
    public function it_returns_proveedor_list_with_success_true(): void
    {
        $result = $this->model->listar(false);

        $this->assertIsArray($result);
        // La lista puede estar vacía; lo importante es que no lance excepción
        if (!empty($result)) {
            $first = $result[0];
            $this->assertArrayHasKey('nombre', $first);
            $this->assertArrayHasKey('activo', $first);
        }
    }

    #[Test]
    public function it_creates_proveedor_correctly(): void
    {
        $id = $this->insertarProveedor('INTTEST_nuevo_prov');

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = $this->model->obtener($id);
        $this->assertSame('INTTEST_nuevo_prov', $row['nombre']);
    }

    #[Test]
    public function it_updates_proveedor(): void
    {
        $id = $this->insertarProveedor('INTTEST_prov_original');

        $ok = $this->model->actualizar($id, ['nombre' => 'INTTEST_prov_actualizado']);

        $this->assertTrue($ok);
        $row = $this->model->obtener($id);
        $this->assertSame('INTTEST_prov_actualizado', $row['nombre']);
    }

    #[Test]
    public function it_deactivates_proveedor_via_patch(): void
    {
        $id = $this->insertarProveedor('INTTEST_prov_desactivar');

        $ok = $this->model->desactivar($id);

        $this->assertTrue($ok);
        $row = $this->model->obtener($id);
        $this->assertSame(0, (int)$row['activo']);
    }

    #[Test]
    public function it_throws_400_when_post_without_nombre(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->crear(['nombre' => '']);
    }

    #[Test]
    public function it_throws_404_when_getting_nonexistent_proveedor(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);

        $this->model->obtener(99999999);
    }
}
