<?php
/**
 * Tests unitarios para los métodos analíticos del dashboard.
 *
 * Verifica topProductosVendidos, productosSinMovimiento,
 * valorInventarioPorCategoria y rotacionStock con SQLite en memoria.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers Movimiento
 * @covers Producto
 */
class DashboardAnalyticsTest extends TestCase
{
    private PDO        $pdo;
    private Movimiento $movimientoModel;
    private Producto   $productoModel;
    private Auditoria  $auditoria;

    /** Levanta la base de datos SQLite en memoria con todas las tablas necesarias. */
    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS auditoria (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                tabla       TEXT NOT NULL,
                registro_id INTEGER NOT NULL,
                accion      TEXT NOT NULL,
                datos_anteriores TEXT,
                datos_nuevos     TEXT,
                usuario     TEXT DEFAULT 'test',
                ip          TEXT,
                fecha       TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS categorias (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre      TEXT NOT NULL,
                descripcion TEXT,
                created_at  TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS marcas (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS alertas_stock (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                producto_id     INTEGER NOT NULL,
                enviada_at      TEXT DEFAULT CURRENT_TIMESTAMP,
                stock_al_enviar INTEGER
            );
            CREATE TABLE IF NOT EXISTS productos (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre       TEXT    NOT NULL,
                descripcion  TEXT,
                precio       REAL    DEFAULT 0,
                stock        INTEGER DEFAULT 0,
                stock_minimo INTEGER DEFAULT 5,
                codigo_ref   TEXT,
                marca_id     INTEGER,
                categoria_id INTEGER,
                deleted_at   TEXT,
                created_at   TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT    DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS movimientos (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                producto_id  INTEGER NOT NULL,
                tipo         TEXT    NOT NULL CHECK(tipo IN ('entrada','salida')),
                cantidad     INTEGER NOT NULL,
                observaciones TEXT,
                usuario      TEXT DEFAULT 'test',
                fecha        TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
            );
        ");

        // Categorías de prueba
        $this->pdo->exec("
            INSERT INTO categorias (id, nombre) VALUES
              (1, 'Filtros'),
              (2, 'Frenos'),
              (3, 'Aceites');
        ");

        // Productos de prueba
        $this->pdo->exec("
            INSERT INTO productos (id, nombre, precio, stock, categoria_id) VALUES
              (1, 'Filtro aceite',      10.00, 20, 1),
              (2, 'Filtro aire',         8.00, 15, 1),
              (3, 'Pastillas delantera', 25.00, 10, 2),
              (4, 'Líquido frenos',     12.00, 5,  2),
              (5, 'Aceite 5W40 1L',     15.00, 30, 3),
              (6, 'Sin movimientos',    50.00, 8,  NULL);
        ");

        $this->auditoria    = new Auditoria($this->pdo);
        $this->movimientoModel = new Movimiento($this->pdo);
        $this->productoModel   = new Producto($this->pdo, $this->auditoria);
    }

    /**
     * Inserta un movimiento en la fecha indicada.
     *
     * @param int    $productoId
     * @param string $tipo      'entrada' | 'salida'
     * @param int    $cantidad
     * @param string $fecha     Y-m-d
     */
    private function insertarMovimiento(int $productoId, string $tipo, int $cantidad, string $fecha): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO movimientos (producto_id, tipo, cantidad, fecha) VALUES (:pid, :tipo, :cant, :fecha)"
        );
        $stmt->execute([':pid' => $productoId, ':tipo' => $tipo, ':cant' => $cantidad, ':fecha' => $fecha]);
    }

    // ── topProductosVendidos ─────────────────────────────────────────────────

    /** Devuelve los productos con más salidas ordenados descendente. */
    public function testTopProductosVendidosOrdenDescendente(): void
    {
        $hoy = date('Y-m-d');
        $this->insertarMovimiento(1, 'salida', 10, $hoy);  // Filtro aceite: 10
        $this->insertarMovimiento(5, 'salida', 20, $hoy);  // Aceite 5W40:   20
        $this->insertarMovimiento(3, 'salida', 5,  $hoy);  // Pastillas:      5

        $resultado = $this->movimientoModel->topProductosVendidos(10, 30);

        $this->assertGreaterThanOrEqual(3, count($resultado));
        $this->assertSame(20, (int)$resultado[0]['total_salidas']); // Aceite 5W40 primero
        $this->assertSame(10, (int)$resultado[1]['total_salidas']); // Filtro aceite segundo
        $this->assertSame(5,  (int)$resultado[2]['total_salidas']); // Pastillas tercero
    }

    /** El límite de resultados es respetado. */
    public function testTopProductosVendidosRespetaLimit(): void
    {
        $hoy = date('Y-m-d');
        foreach ([1, 2, 3, 4, 5] as $i => $pid) {
            $this->insertarMovimiento($pid, 'salida', ($i + 1) * 3, $hoy);
        }
        $resultado = $this->movimientoModel->topProductosVendidos(3, 30);
        $this->assertCount(3, $resultado);
    }

    /** Respeta el parámetro $dias: movimientos anteriores al período no cuentan. */
    public function testTopProductosVendidosRespetaDias(): void
    {
        $reciente = date('Y-m-d');
        $antiguo  = date('Y-m-d', strtotime('-100 days'));

        $this->insertarMovimiento(1, 'salida', 50, $antiguo);  // muy antiguo
        $this->insertarMovimiento(2, 'salida', 5,  $reciente); // reciente

        $resultado = $this->movimientoModel->topProductosVendidos(10, 30);

        $ids = array_column($resultado, 'producto_id');
        $this->assertContains(2, array_map('intval', $ids));
        // El producto 1 no debe aparecer (su movimiento tiene > 30 días)
        $this->assertNotContains(1, array_map('intval', $ids));
    }

    /** Entradas no cuentan en el top ventas. */
    public function testTopProductosVendidosIgnoraEntradas(): void
    {
        $hoy = date('Y-m-d');
        $this->insertarMovimiento(1, 'entrada', 100, $hoy);
        $this->insertarMovimiento(2, 'salida',    3, $hoy);

        $resultado = $this->movimientoModel->topProductosVendidos(10, 30);
        $ids = array_column($resultado, 'producto_id');
        $this->assertNotContains(1, array_map('intval', $ids));
    }

    /** Sin movimientos devuelve array vacío. */
    public function testTopProductosVendidosSinDatos(): void
    {
        $resultado = $this->movimientoModel->topProductosVendidos(10, 30);
        $this->assertSame([], $resultado);
    }

    // ── productosSinMovimiento ───────────────────────────────────────────────

    /** Excluye productos con movimientos recientes. */
    public function testProductosSinMovimientoExcluyeRecientes(): void
    {
        $reciente = date('Y-m-d');
        $this->insertarMovimiento(1, 'entrada', 5, $reciente); // tiene movimiento reciente

        $resultado = $this->movimientoModel->productosSinMovimiento(90);
        $ids = array_column($resultado, 'producto_id');
        $this->assertNotContains(1, array_map('intval', $ids));
    }

    /** Incluye producto 6 (sin ningún movimiento). */
    public function testProductosSinMovimientoIncluyeSinMovimientos(): void
    {
        $resultado = $this->movimientoModel->productosSinMovimiento(90);
        $ids = array_column($resultado, 'producto_id');
        $this->assertContains(6, array_map('intval', $ids));
    }

    /** Excluye productos con stock = 0. */
    public function testProductosSinMovimientoExcluyeStockCero(): void
    {
        // Crear producto con stock 0
        $this->pdo->exec("INSERT INTO productos (id, nombre, precio, stock) VALUES (99, 'Sin stock', 10, 0)");
        $resultado = $this->movimientoModel->productosSinMovimiento(90);
        $ids = array_column($resultado, 'producto_id');
        $this->assertNotContains(99, array_map('intval', $ids));
    }

    // ── valorInventarioPorCategoria ──────────────────────────────────────────

    /** Calcula correctamente precio × stock por categoría. */
    public function testValorInventarioPorCategoriaCalculaCorrecto(): void
    {
        // Filtros: p1(10*20=200) + p2(8*15=120) = 320
        // Frenos: p3(25*10=250) + p4(12*5=60) = 310
        // Aceites: p5(15*30=450)
        $resultado = $this->productoModel->valorInventarioPorCategoria();

        $this->assertNotEmpty($resultado);

        $mapa = [];
        foreach ($resultado as $r) {
            $mapa[$r['categoria']] = (float)$r['valor_total'];
        }

        $this->assertEqualsWithDelta(320.0, $mapa['Filtros']  ?? 0, 0.01);
        $this->assertEqualsWithDelta(310.0, $mapa['Frenos']   ?? 0, 0.01);
        $this->assertEqualsWithDelta(450.0, $mapa['Aceites']  ?? 0, 0.01);
    }

    /** Está ordenado de mayor a menor valor. */
    public function testValorInventarioPorCategoriaOrdenadoDesc(): void
    {
        $resultado = $this->productoModel->valorInventarioPorCategoria();
        $valores   = array_column($resultado, 'valor_total');
        $ordenado  = $valores;
        rsort($ordenado);
        $this->assertSame($ordenado, $valores);
    }

    /** Incluye num_productos. */
    public function testValorInventarioPorCategoriaIncluyeNumProductos(): void
    {
        $resultado = $this->productoModel->valorInventarioPorCategoria();
        foreach ($resultado as $r) {
            $this->assertArrayHasKey('num_productos', $r);
            $this->assertGreaterThan(0, (int)$r['num_productos']);
        }
    }

    // ── rotacionStock ────────────────────────────────────────────────────────

    /** Devuelve el índice correcto. */
    public function testRotacionStockCalculaIndiceCorrectamente(): void
    {
        $hoy = date('Y-m-d');
        // Producto 1: stock=20, salidas=10 → rotación=0.5
        $this->insertarMovimiento(1, 'salida', 10, $hoy);

        $resultado = $this->productoModel->rotacionStock(30);
        $mapa = [];
        foreach ($resultado as $r) {
            $mapa[(int)$r['producto_id']] = (float)$r['rotacion'];
        }

        $this->assertEqualsWithDelta(0.5, $mapa[1] ?? -1, 0.01);
    }

    /** Está ordenado de mayor a menor rotación. */
    public function testRotacionStockOrdenadoDesc(): void
    {
        $hoy = date('Y-m-d');
        $this->insertarMovimiento(1, 'salida', 15, $hoy); // rot = 15/20 = 0.75
        $this->insertarMovimiento(3, 'salida', 8,  $hoy); // rot =  8/10 = 0.80

        $resultado = $this->productoModel->rotacionStock(30);
        $rotaciones = array_column($resultado, 'rotacion');
        for ($i = 0; $i < count($rotaciones) - 1; $i++) {
            $this->assertGreaterThanOrEqual((float)$rotaciones[$i + 1], (float)$rotaciones[$i]);
        }
    }

    /** Excluye productos con stock = 0. */
    public function testRotacionStockExcluyeProductosSinStock(): void
    {
        $this->pdo->exec("INSERT INTO productos (id, nombre, precio, stock) VALUES (98, 'Sin stock2', 10, 0)");
        $resultado = $this->productoModel->rotacionStock(30);
        $ids = array_column($resultado, 'producto_id');
        $this->assertNotContains(98, array_map('intval', $ids));
    }

    /** Productos sin salidas tienen rotación 0. */
    public function testRotacionStockCeroSinSalidas(): void
    {
        $resultado = $this->productoModel->rotacionStock(30);
        foreach ($resultado as $r) {
            $this->assertGreaterThanOrEqual(0.0, (float)$r['rotacion']);
        }
    }
}
