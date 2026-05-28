<?php
/**
 * Tests unitarios para el modelo Pedido.
 *
 * Usa SQLite en memoria: autocontenido, sin depender de la DB MySQL
 * de Docker ni de ningún servicio externo.
 *
 * Cubre: crear pedido, añadir líneas, máquina de estados, generación de
 *        movimientos al recibir, validaciones de transición y conteo pendientes.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PedidoTest extends TestCase
{
    private PDO    $pdo;
    private Pedido $model;

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->crearSchema();

        $auditoria   = new Auditoria($this->pdo);
        $this->model = new Pedido($this->pdo, $auditoria);
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
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre   TEXT    NOT NULL,
                contacto TEXT,
                email    TEXT,
                telefono TEXT,
                web      TEXT,
                notas    TEXT,
                activo   INTEGER DEFAULT 1
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE categorias (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT    NOT NULL
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE marcas (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT    NOT NULL
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE productos (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre            TEXT    NOT NULL,
                descripcion       TEXT,
                descripcion_larga TEXT,
                precio            REAL    DEFAULT 0,
                stock             INTEGER DEFAULT 0,
                stock_minimo      INTEGER DEFAULT 5,
                codigo_ref        TEXT,
                categoria_id      INTEGER,
                marca_id          INTEGER,
                imagen            TEXT,
                codigo_barras     TEXT,
                url_proveedor     TEXT,
                proveedor         TEXT,
                ubicacion         TEXT,
                peso              INTEGER,
                capacidad         INTEGER,
                longitud          INTEGER,
                anchura           INTEGER,
                diametro          REAL,
                alertas_email     INTEGER DEFAULT 1,
                deleted_at        TEXT    DEFAULT NULL,
                created_at        TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at        TEXT    DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE movimientos (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                producto_id   INTEGER NOT NULL,
                tipo          TEXT    NOT NULL,
                cantidad      INTEGER NOT NULL,
                observaciones TEXT,
                fecha         TEXT    DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE alertas_stock (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                producto_id     INTEGER NOT NULL,
                enviada_at      TEXT    DEFAULT CURRENT_TIMESTAMP,
                stock_al_enviar INTEGER
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE pedidos (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                proveedor_id INTEGER NULL,
                estado       TEXT    DEFAULT 'borrador',
                notas        TEXT,
                created_at   TEXT    DEFAULT CURRENT_TIMESTAMP,
                enviado_at   TEXT    NULL,
                recibido_at  TEXT    NULL
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE pedidos_lineas (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id   INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                cantidad    INTEGER NOT NULL,
                precio_unit REAL    NULL
            )"
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Inserta un producto de prueba con stock dado y devuelve su ID.
     *
     * @param int $stock Stock inicial.
     * @return int
     * @author Carlitos6712
     */
    private function insertarProducto(int $stock = 10): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO productos (nombre, stock, stock_minimo) VALUES (:n, :s, 5)"
        );
        $stmt->execute([':n' => 'Producto Test', ':s' => $stock]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Crea un pedido y devuelve su ID.
     *
     * @param array<string,mixed> $datos
     * @return int
     * @author Carlitos6712
     */
    private function crearPedido(array $datos = []): int
    {
        return $this->model->crear($datos);
    }

    // ── Tests: crear ──────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_pedido_in_borrador_state(): void
    {
        $id = $this->crearPedido(['notas' => 'Test pedido']);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = $this->pdo->query("SELECT * FROM pedidos WHERE id = {$id}")->fetch();
        $this->assertNotFalse($row, 'Debe existir el pedido en la BD');
        $this->assertSame('borrador', $row['estado']);
    }

    // ── Tests: añadirLinea ────────────────────────────────────────────────────

    #[Test]
    public function it_adds_linea_to_borrador_pedido(): void
    {
        $productoId = $this->insertarProducto();
        $pedidoId   = $this->crearPedido();

        $lineaId = $this->model->añadirLinea($pedidoId, $productoId, 5, 9.99);

        $this->assertGreaterThan(0, $lineaId);

        $lineas = $this->model->obtenerLineas($pedidoId);
        $this->assertCount(1, $lineas);
        $this->assertSame($productoId, (int)$lineas[0]['producto_id']);
        $this->assertSame(5, (int)$lineas[0]['cantidad']);
    }

    #[Test]
    public function it_throws_409_when_adding_linea_to_enviado_pedido(): void
    {
        $productoId = $this->insertarProducto();
        $pedidoId   = $this->crearPedido();
        $this->model->añadirLinea($pedidoId, $productoId, 1);
        $this->model->cambiarEstado($pedidoId, 'enviado');

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        $this->model->añadirLinea($pedidoId, $productoId, 2);
    }

    // ── Tests: cambiarEstado ──────────────────────────────────────────────────

    #[Test]
    public function it_transitions_from_borrador_to_enviado_and_sets_timestamp(): void
    {
        $pedidoId = $this->crearPedido();

        $result = $this->model->cambiarEstado($pedidoId, 'enviado');

        $this->assertTrue($result);

        $row = $this->pdo->query("SELECT * FROM pedidos WHERE id = {$pedidoId}")->fetch();
        $this->assertSame('enviado', $row['estado']);
        $this->assertNotNull($row['enviado_at'], 'enviado_at debe estar establecido');
        $this->assertNotEmpty($row['enviado_at']);
    }

    #[Test]
    public function it_transitions_from_enviado_to_recibido_and_generates_movements(): void
    {
        $productoId = $this->insertarProducto(10);
        $pedidoId   = $this->crearPedido();
        $this->model->añadirLinea($pedidoId, $productoId, 5);
        $this->model->cambiarEstado($pedidoId, 'enviado');

        $this->model->cambiarEstado($pedidoId, 'recibido');

        // Verificar estado y timestamp
        $rowPedido = $this->pdo->query("SELECT * FROM pedidos WHERE id = {$pedidoId}")->fetch();
        $this->assertSame('recibido', $rowPedido['estado']);
        $this->assertNotNull($rowPedido['recibido_at'], 'recibido_at debe estar establecido');

        // Verificar movimiento de entrada generado
        $movs = $this->pdo->query(
            "SELECT * FROM movimientos WHERE producto_id = {$productoId}"
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $movs, 'Debe generarse un movimiento de entrada');
        $this->assertSame('entrada', $movs[0]['tipo']);
        $this->assertSame(5, (int)$movs[0]['cantidad']);

        // Verificar stock actualizado
        $rowProd = $this->pdo->query("SELECT stock FROM productos WHERE id = {$productoId}")->fetch();
        $this->assertSame(15, (int)$rowProd['stock'], 'El stock debe aumentar en 5');
    }

    #[Test]
    public function it_throws_409_when_invalid_state_transition(): void
    {
        $pedidoId = $this->crearPedido();

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        // borrador → recibido no es una transición válida
        $this->model->cambiarEstado($pedidoId, 'recibido');
    }

    #[Test]
    public function it_throws_409_when_transitioning_from_terminal_state(): void
    {
        $productoId = $this->insertarProducto();
        $pedidoId   = $this->crearPedido();
        $this->model->añadirLinea($pedidoId, $productoId, 1);
        $this->model->cambiarEstado($pedidoId, 'enviado');
        $this->model->cambiarEstado($pedidoId, 'recibido');

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        // recibido → cancelado no es válido (estado terminal)
        $this->model->cambiarEstado($pedidoId, 'cancelado');
    }

    // ── Tests: contarPendientes ───────────────────────────────────────────────

    #[Test]
    public function it_counts_pending_orders_correctly(): void
    {
        $productoId = $this->insertarProducto();

        // Pedido en borrador
        $this->crearPedido();

        // Pedido en enviado
        $pedidoEnviado = $this->crearPedido();
        $this->model->cambiarEstado($pedidoEnviado, 'enviado');

        // Pedido en recibido (no debe contar)
        $pedidoRecibido = $this->crearPedido();
        $this->model->añadirLinea($pedidoRecibido, $productoId, 1);
        $this->model->cambiarEstado($pedidoRecibido, 'enviado');
        $this->model->cambiarEstado($pedidoRecibido, 'recibido');

        // Pedido cancelado (no debe contar)
        $pedidoCancelado = $this->crearPedido();
        $this->model->cambiarEstado($pedidoCancelado, 'cancelado');

        $pendientes = $this->model->contarPendientes();

        $this->assertSame(2, $pendientes, 'Deben contarse solo borrador+enviado');
    }
}
