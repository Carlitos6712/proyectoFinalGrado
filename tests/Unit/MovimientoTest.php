<?php
/**
 * Tests unitarios para el modelo Movimiento.
 *
 * Usa SQLite en memoria: autocontenido, sin depender de la DB MySQL
 * de Docker ni de ningún servicio externo.
 *
 * Cubre: registrar entrada/salida, stock insuficiente, tipo inválido,
 *        cantidad inválida, listarPorProducto, resumenStock.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MovimientoTest extends TestCase
{
    private PDO        $pdo;
    private Movimiento $model;
    /** @var array<int, array{to: string, subject: string, body: string}> */
    private array $emailsSent = [];

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->crearSchema();

        $this->emailsSent = [];
        $capturado        = &$this->emailsSent;
        $transport        = static function (string $to, string $subject, string $body) use (&$capturado): void {
            $capturado[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
        };

        $alertaStock = new AlertaStock($this->pdo, $transport);
        $this->model = new Movimiento($this->pdo, $alertaStock);
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
                business_id      INTEGER NULL,
                fecha            TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE marcas (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT    NOT NULL
            )
        ");
        $this->pdo->exec("
            CREATE TABLE categorias (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT    NOT NULL
            )
        ");
        $this->pdo->exec("
            CREATE TABLE productos (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre        TEXT    NOT NULL,
                descripcion   TEXT,
                descripcion_larga TEXT,
                precio        REAL    DEFAULT 0,
                stock         INTEGER DEFAULT 0,
                stock_minimo  INTEGER DEFAULT 5,
                codigo_ref    TEXT,
                categoria_id  INTEGER,
                marca_id      INTEGER,
                imagen        TEXT,
                codigo_barras TEXT,
                url_proveedor TEXT,
                proveedor     TEXT,
                ubicacion     TEXT,
                peso          INTEGER,
                capacidad     INTEGER,
                longitud      INTEGER,
                anchura       INTEGER,
                diametro      REAL,
                alertas_email INTEGER DEFAULT 1,
                deleted_at    TEXT    DEFAULT NULL,
                created_at    TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at    TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE movimientos (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                producto_id   INTEGER NOT NULL,
                tipo          TEXT    NOT NULL,
                cantidad      INTEGER NOT NULL,
                observaciones TEXT,
                usuario       TEXT    DEFAULT 'admin',
                fecha         TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("
            CREATE TABLE alertas_stock (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                producto_id     INTEGER NOT NULL,
                enviada_at      TEXT    DEFAULT CURRENT_TIMESTAMP,
                stock_al_enviar INTEGER
            )
        ");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Inserta un producto de prueba y devuelve su ID.
     *
     * @param array<string, mixed> $overrides
     * @return int
     */
    private function insertarProducto(array $overrides = []): int
    {
        $defaults = [
            'nombre'       => 'Producto Test',
            'stock'        => 10,
            'stock_minimo' => 5,
            'alertas_email'=> 1,
        ];
        $d    = array_merge($defaults, $overrides);
        $stmt = $this->pdo->prepare(
            "INSERT INTO productos (nombre, stock, stock_minimo, alertas_email)
             VALUES (:nombre, :stock, :stock_minimo, :alertas_email)"
        );
        $stmt->execute([
            ':nombre'        => $d['nombre'],
            ':stock'         => (int) $d['stock'],
            ':stock_minimo'  => (int) $d['stock_minimo'],
            ':alertas_email' => (int) $d['alertas_email'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ── Tests: registrar entrada ──────────────────────────────────────────────

    #[Test]
    public function it_registers_an_entrada_movement_and_increases_stock(): void
    {
        $id = $this->insertarProducto(['stock' => 10]);

        $movId = $this->model->registrar($id, 'entrada', 5, 'Reposición');

        $this->assertGreaterThan(0, $movId);
        $row = $this->pdo->query("SELECT stock FROM productos WHERE id = {$id}")->fetch();
        $this->assertSame(15, (int) $row['stock']);
    }

    #[Test]
    public function it_creates_movement_record_on_entrada(): void
    {
        $id = $this->insertarProducto();

        $this->model->registrar($id, 'entrada', 3, 'Stock inicial');

        $mov = $this->pdo->query(
            "SELECT * FROM movimientos WHERE producto_id = {$id}"
        )->fetch();

        $this->assertNotFalse($mov);
        $this->assertSame('entrada',      $mov['tipo']);
        $this->assertSame(3, (int)         $mov['cantidad']);
        $this->assertSame('Stock inicial', $mov['observaciones']);
    }

    // ── Tests: registrar salida ───────────────────────────────────────────────

    #[Test]
    public function it_registers_a_salida_movement_and_decreases_stock(): void
    {
        $id = $this->insertarProducto(['stock' => 10]);

        $this->model->registrar($id, 'salida', 4);

        $row = $this->pdo->query("SELECT stock FROM productos WHERE id = {$id}")->fetch();
        $this->assertSame(6, (int) $row['stock']);
    }

    #[Test]
    public function it_registers_salida_that_brings_stock_to_zero(): void
    {
        $id = $this->insertarProducto(['stock' => 5]);

        $this->model->registrar($id, 'salida', 5);

        $row = $this->pdo->query("SELECT stock FROM productos WHERE id = {$id}")->fetch();
        $this->assertSame(0, (int) $row['stock']);
    }

    // ── Tests: validaciones ───────────────────────────────────────────────────

    #[Test]
    public function it_throws_exception_when_salida_would_leave_negative_stock(): void
    {
        $id = $this->insertarProducto(['stock' => 3]);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->registrar($id, 'salida', 10);
    }

    #[Test]
    public function it_throws_exception_for_invalid_movement_type(): void
    {
        $id = $this->insertarProducto();

        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->registrar($id, 'devolucion', 1);
    }

    #[Test]
    public function it_throws_exception_when_cantidad_is_zero(): void
    {
        $id = $this->insertarProducto();

        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->registrar($id, 'entrada', 0);
    }

    #[Test]
    public function it_throws_exception_when_cantidad_is_negative(): void
    {
        $id = $this->insertarProducto();

        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->registrar($id, 'entrada', -5);
    }

    #[Test]
    public function it_rolls_back_transaction_on_failure(): void
    {
        $id           = $this->insertarProducto(['stock' => 2]);
        $stockAnterior = 2;

        try {
            $this->model->registrar($id, 'salida', 99); // stock insuficiente
        } catch (AppException $e) {
            // esperado
        }

        $row = $this->pdo->query("SELECT stock FROM productos WHERE id = {$id}")->fetch();
        $this->assertSame($stockAnterior, (int) $row['stock'], 'El stock no debe cambiar tras un rollback');

        $countMov = $this->pdo->query(
            "SELECT COUNT(*) FROM movimientos WHERE producto_id = {$id}"
        )->fetchColumn();
        $this->assertSame(0, (int) $countMov, 'No debe quedar registro de movimiento fallido');
    }

    // ── Tests: listarPorProducto ──────────────────────────────────────────────

    #[Test]
    public function it_lists_movements_for_a_product_in_descending_order(): void
    {
        $id = $this->insertarProducto(['stock' => 50]);

        // Insertar la entrada con una fecha pasada explícita para garantizar
        // orden determinista incluso cuando ambas operaciones ocurren en el mismo segundo.
        $stmt = $this->pdo->prepare(
            "INSERT INTO movimientos (producto_id, tipo, cantidad, fecha)
             VALUES (:pid, 'entrada', 10, datetime('now', '-1 minute'))"
        );
        $stmt->execute([':pid' => $id]);
        // Actualizar el stock manualmente (bypasseamos el modelo para la entrada antigua)
        $this->pdo->exec("UPDATE productos SET stock = stock + 10 WHERE id = {$id}");

        // La salida se registra con CURRENT_TIMESTAMP (más reciente)
        $this->model->registrar($id, 'salida', 3, 'Segunda');

        $movimientos = $this->model->listarPorProducto($id);

        $this->assertCount(2, $movimientos);
        // Más reciente primero → salida (CURRENT_TIMESTAMP) > entrada (ahora - 1 min)
        $this->assertSame('salida',  $movimientos[0]['tipo']);
        $this->assertSame('entrada', $movimientos[1]['tipo']);
    }

    #[Test]
    public function it_returns_empty_array_when_product_has_no_movements(): void
    {
        $id = $this->insertarProducto();

        $movimientos = $this->model->listarPorProducto($id);

        $this->assertIsArray($movimientos);
        $this->assertEmpty($movimientos);
    }

    // ── Tests: resumenStock ───────────────────────────────────────────────────

    #[Test]
    public function it_calculates_stock_summary_from_movements(): void
    {
        $id = $this->insertarProducto(['stock' => 100]);

        $this->model->registrar($id, 'entrada', 20);
        $this->model->registrar($id, 'entrada', 10);
        $this->model->registrar($id, 'salida',  15);

        $resumen = $this->model->resumenStock($id);

        $this->assertSame(30, (int) $resumen['entradas']);
        $this->assertSame(15, (int) $resumen['salidas']);
        $this->assertSame(15, (int) $resumen['balance']);
    }

    #[Test]
    public function it_returns_zero_balance_when_no_movements_exist(): void
    {
        $id = $this->insertarProducto();

        $resumen = $this->model->resumenStock($id);

        $this->assertSame(0, $resumen['entradas']);
        $this->assertSame(0, $resumen['salidas']);
        $this->assertSame(0, $resumen['balance']);
    }
}
