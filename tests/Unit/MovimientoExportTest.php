<?php
/**
 * Tests unitarios para el método listarParaExportar del modelo Movimiento.
 *
 * Usa SQLite en memoria: autocontenido, sin depender de la DB MySQL
 * de Docker ni de ningún servicio externo.
 *
 * Cubre: rango de fechas por defecto (30 días), filtros desde/hasta inclusivos,
 *        filtro por tipo entrada/salida, filtro por producto, columnas requeridas,
 *        cálculo de totales y resultado vacío fuera de rango.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MovimientoExportTest extends TestCase
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
            'nombre'       => 'Producto Export Test',
            'stock'        => 100,
            'stock_minimo' => 5,
            'alertas_email'=> 1,
            'codigo_ref'   => 'REF-TEST',
        ];
        $d    = array_merge($defaults, $overrides);
        $stmt = $this->pdo->prepare(
            "INSERT INTO productos (nombre, stock, stock_minimo, alertas_email, codigo_ref)
             VALUES (:nombre, :stock, :stock_minimo, :alertas_email, :codigo_ref)"
        );
        $stmt->execute([
            ':nombre'        => $d['nombre'],
            ':stock'         => (int) $d['stock'],
            ':stock_minimo'  => (int) $d['stock_minimo'],
            ':alertas_email' => (int) $d['alertas_email'],
            ':codigo_ref'    => $d['codigo_ref'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Inserta un movimiento con una fecha específica y devuelve su ID.
     *
     * @param int    $productoId
     * @param string $tipo       'entrada' o 'salida'
     * @param int    $cantidad
     * @param string $fecha      Fecha en formato Y-m-d H:i:s o Y-m-d
     * @return int
     * @author Carlitos6712
     */
    private function insertarMovimientoConFecha(int $productoId, string $tipo, int $cantidad, string $fecha): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones, usuario, fecha)
             VALUES (:pid, :tipo, :cantidad, '', 'test', :fecha)"
        );
        $stmt->execute([':pid' => $productoId, ':tipo' => $tipo, ':cantidad' => $cantidad, ':fecha' => $fecha]);
        return (int) $this->pdo->lastInsertId();
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    #[Test]
    public function it_exports_last_30_days_by_default(): void
    {
        $productoId = $this->insertarProducto();

        $idHoy     = $this->insertarMovimientoConFecha($productoId, 'entrada', 5, date('Y-m-d H:i:s'));
        $idAntiguo = $this->insertarMovimientoConFecha($productoId, 'entrada', 3, date('Y-m-d H:i:s', strtotime('-31 days')));

        $filas = $this->model->listarParaExportar();
        $ids   = array_column($filas, 'id');

        $this->assertContains($idHoy,     $ids, 'Movimiento de hoy debe estar en el export por defecto');
        $this->assertNotContains($idAntiguo, $ids, 'Movimiento de hace 31 días no debe estar en export por defecto');
    }

    #[Test]
    public function it_respects_desde_and_hasta_inclusive(): void
    {
        $productoId = $this->insertarProducto();

        $id = $this->insertarMovimientoConFecha($productoId, 'entrada', 10, '2025-01-15 12:00:00');

        // Debe incluirlo cuando el rango es exactamente ese día
        $filasInclusive = $this->model->listarParaExportar('2025-01-15', '2025-01-15');
        $idsInclusive   = array_column($filasInclusive, 'id');
        $this->assertContains($id, $idsInclusive, 'El extremo desde=hasta debe ser inclusivo');

        // No debe incluirlo cuando el rango es el día siguiente
        $filasExcluded = $this->model->listarParaExportar('2025-01-16', '2025-01-20');
        $idsExcluded   = array_column($filasExcluded, 'id');
        $this->assertNotContains($id, $idsExcluded, 'El movimiento no debe aparecer fuera de su rango');
    }

    #[Test]
    public function it_filters_by_tipo_entrada(): void
    {
        $productoId = $this->insertarProducto();
        $hoy        = date('Y-m-d');

        $idEntrada = $this->insertarMovimientoConFecha($productoId, 'entrada', 5, $hoy . ' 10:00:00');
        $idSalida  = $this->insertarMovimientoConFecha($productoId, 'salida',  3, $hoy . ' 11:00:00');

        $filas = $this->model->listarParaExportar(null, null, null, 'entrada');
        $ids   = array_column($filas, 'id');

        $this->assertContains($idEntrada, $ids, 'La entrada debe aparecer al filtrar por tipo=entrada');
        $this->assertNotContains($idSalida, $ids, 'La salida no debe aparecer al filtrar por tipo=entrada');
    }

    #[Test]
    public function it_filters_by_tipo_salida(): void
    {
        $productoId = $this->insertarProducto();
        $hoy        = date('Y-m-d');

        $idEntrada = $this->insertarMovimientoConFecha($productoId, 'entrada', 5, $hoy . ' 10:00:00');
        $idSalida  = $this->insertarMovimientoConFecha($productoId, 'salida',  3, $hoy . ' 11:00:00');

        $filas = $this->model->listarParaExportar(null, null, null, 'salida');
        $ids   = array_column($filas, 'id');

        $this->assertContains($idSalida, $ids, 'La salida debe aparecer al filtrar por tipo=salida');
        $this->assertNotContains($idEntrada, $ids, 'La entrada no debe aparecer al filtrar por tipo=salida');
    }

    #[Test]
    public function it_filters_by_producto_id(): void
    {
        $producto1Id = $this->insertarProducto(['nombre' => 'Producto 1', 'codigo_ref' => 'REF-1']);
        $producto2Id = $this->insertarProducto(['nombre' => 'Producto 2', 'codigo_ref' => 'REF-2']);
        $hoy         = date('Y-m-d');

        $idMov1 = $this->insertarMovimientoConFecha($producto1Id, 'entrada', 5, $hoy . ' 10:00:00');
        $idMov2 = $this->insertarMovimientoConFecha($producto2Id, 'entrada', 3, $hoy . ' 11:00:00');

        $filas = $this->model->listarParaExportar(null, null, $producto1Id);
        $ids   = array_column($filas, 'id');

        $this->assertContains($idMov1,    $ids, 'El movimiento del producto 1 debe aparecer');
        $this->assertNotContains($idMov2, $ids, 'El movimiento del producto 2 no debe aparecer');
    }

    #[Test]
    public function it_returns_all_required_columns(): void
    {
        $productoId = $this->insertarProducto(['nombre' => 'Producto Columnas', 'codigo_ref' => 'COL-001']);
        $hoy        = date('Y-m-d');

        $this->insertarMovimientoConFecha($productoId, 'entrada', 7, $hoy . ' 09:00:00');

        $filas = $this->model->listarParaExportar();

        $this->assertNotEmpty($filas, 'Debe haber al menos una fila');
        $fila = $filas[0];

        foreach (['id', 'fecha', 'producto', 'referencia', 'tipo', 'cantidad', 'observaciones', 'usuario'] as $col) {
            $this->assertArrayHasKey($col, $fila, "La columna '{$col}' debe estar presente en el resultado");
        }
    }

    #[Test]
    public function it_calculates_totals_correctly(): void
    {
        $productoId = $this->insertarProducto();
        $hoy        = date('Y-m-d');

        // 3 entradas: 5 + 3 + 2 = 10
        $this->insertarMovimientoConFecha($productoId, 'entrada', 5, $hoy . ' 08:00:00');
        $this->insertarMovimientoConFecha($productoId, 'entrada', 3, $hoy . ' 09:00:00');
        $this->insertarMovimientoConFecha($productoId, 'entrada', 2, $hoy . ' 10:00:00');
        // 2 salidas: 4 + 1 = 5
        $this->insertarMovimientoConFecha($productoId, 'salida', 4, $hoy . ' 11:00:00');
        $this->insertarMovimientoConFecha($productoId, 'salida', 1, $hoy . ' 12:00:00');

        $filas = $this->model->listarParaExportar();

        $totalEntradas = 0;
        $totalSalidas  = 0;
        foreach ($filas as $f) {
            if ($f['tipo'] === 'entrada') {
                $totalEntradas += (int) $f['cantidad'];
            } else {
                $totalSalidas += (int) $f['cantidad'];
            }
        }

        $this->assertSame(10, $totalEntradas, 'La suma de entradas debe ser 10');
        $this->assertSame(5,  $totalSalidas,  'La suma de salidas debe ser 5');
    }

    #[Test]
    public function it_returns_empty_array_when_no_movements_in_range(): void
    {
        $productoId = $this->insertarProducto();
        // Insertar movimiento fuera del rango consultado
        $this->insertarMovimientoConFecha($productoId, 'entrada', 5, date('Y-m-d H:i:s'));

        $filas = $this->model->listarParaExportar('2000-01-01', '2000-01-02');

        $this->assertIsArray($filas, 'El resultado debe ser un array');
        $this->assertEmpty($filas, 'No debe haber movimientos en un rango sin datos');
    }
}
