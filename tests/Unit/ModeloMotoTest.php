<?php
/**
 * Tests unitarios para el modelo ModeloMoto.
 *
 * Usa SQLite en memoria: autocontenido, sin depender de la DB MySQL
 * de Docker ni de ningún servicio externo.
 *
 * Cubre: crear, listar, obtenerPorId, listarParaSelect,
 *        eliminar sin compatibilidades, eliminar con compatibilidades (409),
 *        validaciones de campos vacíos (400).
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ModeloMotoTest extends TestCase
{
    private PDO       $pdo;
    private ModeloMoto $model;

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    /**
     * @author Carlitos6712
     */
    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->crearSchema();

        $this->model = new ModeloMoto($this->pdo);
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
            CREATE TABLE modelos_moto (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                marca      TEXT    NOT NULL,
                modelo     TEXT    NOT NULL,
                anio_desde INTEGER NOT NULL,
                anio_hasta INTEGER NULL,
                created_at TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE productos (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre     TEXT    NOT NULL,
                deleted_at TEXT    DEFAULT NULL
            )
        ");
        $this->pdo->exec("
            CREATE TABLE compatibilidades (
                producto_id INTEGER NOT NULL,
                modelo_id   INTEGER NOT NULL,
                notas       TEXT,
                PRIMARY KEY (producto_id, modelo_id)
            )
        ");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Crea un modelo de moto y devuelve su ID.
     *
     * @author Carlitos6712
     */
    private function crearModelo(
        string $marca = 'Honda',
        string $modelo = 'CB500F',
        int $anioDes = 2019,
        ?int $anioHasta = null
    ): int {
        return $this->model->crear($marca, $modelo, $anioDes, $anioHasta);
    }

    /**
     * Inserta un producto activo y retorna su ID.
     *
     * @author Carlitos6712
     */
    private function insertarProducto(): int
    {
        $this->pdo->exec("INSERT INTO productos (nombre) VALUES ('Filtro de aceite')");
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Vincula un producto a un modelo en compatibilidades.
     *
     * @author Carlitos6712
     */
    private function vincularCompatibilidad(int $productoId, int $modeloId): void
    {
        $this->pdo->prepare(
            "INSERT INTO compatibilidades (producto_id, modelo_id) VALUES (:p, :m)"
        )->execute([':p' => $productoId, ':m' => $modeloId]);
    }

    // ── Tests: crear ─────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_moto_model_and_returns_its_id(): void
    {
        $id = $this->crearModelo();

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    #[Test]
    public function it_persists_all_fields_after_create(): void
    {
        $id  = $this->crearModelo('Yamaha', 'MT-07', 2021, 2024);
        $row = $this->model->obtenerPorId($id);

        $this->assertSame('Yamaha', $row['marca']);
        $this->assertSame('MT-07',  $row['modelo']);
        $this->assertSame('2021',   (string) $row['anio_desde']);
        $this->assertSame('2024',   (string) $row['anio_hasta']);
    }

    #[Test]
    public function it_stores_null_anio_hasta_when_model_still_in_production(): void
    {
        $id  = $this->crearModelo('Kawasaki', 'Z650', 2020, null);
        $row = $this->model->obtenerPorId($id);

        $this->assertNull($row['anio_hasta']);
    }

    #[Test]
    public function it_throws_400_when_marca_is_empty(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->crear('', 'CB500F', 2019, null);
    }

    #[Test]
    public function it_throws_400_when_modelo_is_empty(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->crear('Honda', '', 2019, null);
    }

    // ── Tests: listar ─────────────────────────────────────────────────────────

    #[Test]
    public function it_lists_all_moto_models_ordered_by_marca_then_modelo(): void
    {
        $this->crearModelo('Yamaha',   'MT-07',  2021);
        $this->crearModelo('Honda',    'CB500F', 2019);
        $this->crearModelo('Kawasaki', 'Z650',   2020);

        $marcas = array_column($this->model->listar(), 'marca');

        $this->assertSame(['Honda', 'Kawasaki', 'Yamaha'], $marcas);
    }

    // ── Tests: obtenerPorId ───────────────────────────────────────────────────

    #[Test]
    public function it_throws_404_for_nonexistent_model_id(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);

        $this->model->obtenerPorId(99999);
    }

    // ── Tests: eliminar ───────────────────────────────────────────────────────

    #[Test]
    public function it_deletes_a_model_without_compatibilities(): void
    {
        $id = $this->crearModelo();

        $result = $this->model->eliminar($id);

        $this->assertTrue($result);
        $this->assertEmpty($this->model->listar());
    }

    #[Test]
    public function it_throws_409_when_deleting_model_with_existing_compatibilities(): void
    {
        $modeloId   = $this->crearModelo();
        $productoId = $this->insertarProducto();
        $this->vincularCompatibilidad($productoId, $modeloId);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        $this->model->eliminar($modeloId);
    }

    // ── Tests: listarParaSelect ───────────────────────────────────────────────

    #[Test]
    public function it_returns_id_marca_modelo_and_anios_for_select(): void
    {
        $id = $this->crearModelo('Suzuki', 'GSX-S750', 2018, null);

        $rows = $this->model->listarParaSelect();

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('id',         $rows[0]);
        $this->assertArrayHasKey('marca',      $rows[0]);
        $this->assertArrayHasKey('modelo',     $rows[0]);
        $this->assertArrayHasKey('anio_desde', $rows[0]);
        $this->assertArrayHasKey('anio_hasta', $rows[0]);
        $this->assertSame($id, (int) $rows[0]['id']);
    }
}
