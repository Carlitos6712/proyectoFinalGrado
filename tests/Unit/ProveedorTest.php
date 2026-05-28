<?php
/**
 * Tests unitarios para el modelo Proveedor.
 *
 * Usa SQLite en memoria: autocontenido, sin depender de la DB MySQL
 * de Docker ni de ningún servicio externo.
 *
 * Cubre: crear, listar (activos/todos), obtener, actualizar, desactivar,
 *        error 409 con productos activos, error 400 nombre vacío, error 404.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProveedorTest extends TestCase
{
    private PDO       $pdo;
    private Proveedor $model;

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->crearSchema();

        $auditoria   = new Auditoria($this->pdo);
        $this->model = new Proveedor($this->pdo, $auditoria);
    }

    // ── Schema SQLite en memoria ──────────────────────────────────────────────

    /**
     * Crea las tablas necesarias en SQLite in-memory.
     *
     * @author Carlitos6712
     */
    private function crearSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE auditoria (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                tabla            TEXT    NOT NULL,
                registro_id      INTEGER NOT NULL,
                accion           TEXT    NOT NULL,
                datos_anteriores TEXT    NULL,
                datos_nuevos     TEXT    NULL,
                usuario          TEXT    DEFAULT 'admin',
                ip               TEXT,
                business_id      INTEGER NULL,
                fecha            TEXT    DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE proveedores (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre     TEXT    NOT NULL,
                contacto   TEXT,
                email      TEXT,
                telefono   TEXT,
                web        TEXT,
                notas      TEXT,
                activo     INTEGER DEFAULT 1,
                created_at TEXT    DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE categorias (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT    NOT NULL
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE productos (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre       TEXT    NOT NULL,
                descripcion  TEXT,
                precio       REAL    DEFAULT 0,
                stock        INTEGER DEFAULT 0,
                stock_minimo INTEGER DEFAULT 5,
                proveedor_id INTEGER NULL,
                deleted_at   TEXT    DEFAULT NULL,
                created_at   TEXT    DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Inserta un proveedor básico y devuelve su ID.
     *
     * @param string $nombre
     * @param int    $activo
     * @return int
     * @author Carlitos6712
     */
    private function insertarProveedor(string $nombre = 'Proveedor Test', int $activo = 1): int
    {
        return $this->model->crear(['nombre' => $nombre, 'activo' => $activo]);
    }

    /**
     * Inserta un producto activo vinculado a un proveedor.
     *
     * @param int $proveedorId
     * @return void
     * @author Carlitos6712
     */
    private function insertarProductoActivo(int $proveedorId): void
    {
        $this->pdo->exec(
            "INSERT INTO productos (nombre, proveedor_id) VALUES ('Prod Test', {$proveedorId})"
        );
    }

    // ── Tests: crear ─────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_proveedor_and_registers_audit(): void
    {
        $id = $this->insertarProveedor('ProvAudit');

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $fila = $this->pdo
            ->query("SELECT * FROM auditoria WHERE tabla = 'proveedores' AND registro_id = {$id}")
            ->fetch();
        $this->assertNotFalse($fila, 'Debe existir registro de auditoría tras crear');
        $this->assertSame('crear', $fila['accion']);
    }

    #[Test]
    public function it_throws_400_when_crear_with_empty_nombre(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->crear(['nombre' => '   ']);
    }

    // ── Tests: listar ────────────────────────────────────────────────────────

    #[Test]
    public function it_lists_only_active_proveedores(): void
    {
        $this->insertarProveedor('Activo');
        $idInactivo = $this->insertarProveedor('Inactivo');
        $this->model->desactivar($idInactivo);

        $lista = $this->model->listar(true);
        $nombres = array_column($lista, 'nombre');

        $this->assertContains('Activo', $nombres);
        $this->assertNotContains('Inactivo', $nombres);
    }

    #[Test]
    public function it_lists_all_proveedores_when_false(): void
    {
        $this->insertarProveedor('Activo');
        $idInactivo = $this->insertarProveedor('Inactivo');
        $this->model->desactivar($idInactivo);

        $lista = $this->model->listar(false);
        $nombres = array_column($lista, 'nombre');

        $this->assertContains('Activo', $nombres);
        $this->assertContains('Inactivo', $nombres);
    }

    // ── Tests: obtener ────────────────────────────────────────────────────────

    #[Test]
    public function it_throws_404_when_obtener_nonexistent(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);

        $this->model->obtener(99999);
    }

    // ── Tests: actualizar ─────────────────────────────────────────────────────

    #[Test]
    public function it_updates_proveedor_and_registers_audit(): void
    {
        $id = $this->insertarProveedor('Nombre Original');

        $ok = $this->model->actualizar($id, ['nombre' => 'Nombre Nuevo', 'email' => 'nuevo@test.com']);

        $this->assertTrue($ok);
        $row = $this->model->obtener($id);
        $this->assertSame('Nombre Nuevo', $row['nombre']);

        $auditCount = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM auditoria WHERE tabla = 'proveedores' AND registro_id = {$id} AND accion = 'actualizar'")
            ->fetchColumn();
        $this->assertGreaterThan(0, $auditCount);
    }

    // ── Tests: desactivar ─────────────────────────────────────────────────────

    #[Test]
    public function it_throws_409_when_desactivar_with_active_products(): void
    {
        $id = $this->insertarProveedor('ConProductos');
        $this->insertarProductoActivo($id);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        $this->model->desactivar($id);
    }

    #[Test]
    public function it_desactivates_proveedor_without_products(): void
    {
        $id = $this->insertarProveedor('SinProductos');

        $ok = $this->model->desactivar($id);

        $this->assertTrue($ok);
        $row = $this->model->obtener($id);
        $this->assertSame(0, (int)$row['activo']);
    }
}
