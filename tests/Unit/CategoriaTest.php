<?php
/**
 * Tests unitarios para el modelo Categoria.
 *
 * Usa SQLite en memoria: autocontenido, sin depender de la DB MySQL
 * de Docker ni de ningún servicio externo.
 *
 * Cubre: crear, listar (con contador de productos), obtenerPorId,
 *        actualizar, eliminar, error al eliminar con productos activos.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CategoriaTest extends TestCase
{
    private PDO       $pdo;
    private Categoria $model;

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->crearSchema();

        $auditoria   = new Auditoria($this->pdo);
        $this->model = new Categoria($this->pdo, $auditoria);
    }

    // ── Schema SQLite en memoria ──────────────────────────────────────────────

    /**
     * Crea las tablas necesarias en SQLite in-memory.
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
            CREATE TABLE categorias (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre      TEXT    NOT NULL,
                descripcion TEXT,
                created_at  TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE productos (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre       TEXT    NOT NULL,
                categoria_id INTEGER,
                deleted_at   TEXT    DEFAULT NULL
            )
        ");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Inserta un producto activo vinculado a una categoría.
     *
     * @param int $categoriaId
     * @return void
     */
    private function insertarProductoActivo(int $categoriaId): void
    {
        $this->pdo->exec(
            "INSERT INTO productos (nombre, categoria_id) VALUES ('Producto Test', {$categoriaId})"
        );
    }

    // ── Tests: crear ─────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_category_and_returns_its_id(): void
    {
        $id = $this->model->crear('Frenos');

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    #[Test]
    public function it_persists_category_name_and_description_after_create(): void
    {
        $id  = $this->model->crear('Aceites', 'Aceites y lubricantes');
        $row = $this->model->obtenerPorId($id);

        $this->assertSame('Aceites', $row['nombre']);
        $this->assertSame('Aceites y lubricantes', $row['descripcion']);
    }

    // ── Tests: listar ────────────────────────────────────────────────────────

    #[Test]
    public function it_lists_all_categories_ordered_by_name(): void
    {
        $this->model->crear('Zapatas');
        $this->model->crear('Aceites');
        $this->model->crear('Filtros');

        $nombres = array_column($this->model->listar(), 'nombre');

        $this->assertSame(['Aceites', 'Filtros', 'Zapatas'], $nombres);
    }

    #[Test]
    public function it_returns_product_count_per_category_in_listar(): void
    {
        $catId = $this->model->crear('Frenos con productos');
        $this->insertarProductoActivo($catId);
        $this->insertarProductoActivo($catId);

        $categorias = $this->model->listar();
        $fila       = array_filter($categorias, fn($c) => (int)$c['id'] === $catId);
        $fila       = reset($fila);

        $this->assertSame(2, (int) $fila['total_productos']);
    }

    #[Test]
    public function it_does_not_count_soft_deleted_products_in_category_total(): void
    {
        $catId = $this->model->crear('Cat con softdelete');
        $this->pdo->exec(
            "INSERT INTO productos (nombre, categoria_id, deleted_at)
             VALUES ('Eliminado', {$catId}, CURRENT_TIMESTAMP)"
        );

        $categorias = $this->model->listar();
        $fila       = array_filter($categorias, fn($c) => (int)$c['id'] === $catId);
        $fila       = reset($fila);

        $this->assertSame(0, (int) $fila['total_productos'], 'Los soft-deleted no deben contarse');
    }

    // ── Tests: obtenerPorId ───────────────────────────────────────────────────

    #[Test]
    public function it_returns_category_data_by_id(): void
    {
        $id  = $this->model->crear('Transmision');
        $row = $this->model->obtenerPorId($id);

        $this->assertSame('Transmision', $row['nombre']);
    }

    #[Test]
    public function it_throws_not_found_exception_for_nonexistent_category(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);

        $this->model->obtenerPorId(99999);
    }

    // ── Tests: actualizar ─────────────────────────────────────────────────────

    #[Test]
    public function it_updates_category_name_and_description(): void
    {
        $id = $this->model->crear('Nombre viejo', 'Desc vieja');

        $result = $this->model->actualizar($id, 'Nombre nuevo', 'Desc nueva');

        $this->assertTrue($result);
        $row = $this->model->obtenerPorId($id);
        $this->assertSame('Nombre nuevo', $row['nombre']);
        $this->assertSame('Desc nueva',   $row['descripcion']);
    }

    // ── Tests: eliminar ───────────────────────────────────────────────────────

    #[Test]
    public function it_deletes_a_category_with_no_active_products(): void
    {
        $id = $this->model->crear('Para borrar');

        $result = $this->model->eliminar($id);

        $this->assertTrue($result);
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM categorias WHERE id = {$id}")->fetchColumn();
        $this->assertSame(0, $count);
    }

    #[Test]
    public function it_throws_conflict_exception_when_deleting_category_with_active_products(): void
    {
        $id = $this->model->crear('Con productos activos');
        $this->insertarProductoActivo($id);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        $this->model->eliminar($id);
    }

    #[Test]
    public function it_allows_deleting_category_when_only_soft_deleted_products_exist(): void
    {
        $id = $this->model->crear('Solo soft-deleted');
        $this->pdo->exec(
            "INSERT INTO productos (nombre, categoria_id, deleted_at)
             VALUES ('Borrado', {$id}, CURRENT_TIMESTAMP)"
        );

        // No debe lanzar excepción: los soft-deleted no bloquean el borrado
        $result = $this->model->eliminar($id);

        $this->assertTrue($result);
    }

    // ── Tests: auditoría integrada ────────────────────────────────────────────

    #[Test]
    public function it_logs_create_action_in_audit_table(): void
    {
        $id = $this->model->crear('Auditada');

        $stmt  = $this->pdo->query("SELECT * FROM auditoria WHERE tabla = 'categorias' AND registro_id = {$id}");
        $fila  = $stmt->fetch();

        $this->assertNotFalse($fila, 'Debe haber un registro de auditoría para la creación');
        $this->assertSame('crear', $fila['accion']);
    }
}
