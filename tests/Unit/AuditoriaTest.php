<?php
/**
 * Tests unitarios para el modelo Auditoria.
 *
 * Usa SQLite en memoria: autocontenido, sin depender de la DB MySQL
 * de Docker ni de ningún servicio externo.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AuditoriaTest extends TestCase
{
    private PDO $pdo;
    private Auditoria $auditoria;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
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
        $this->auditoria = new Auditoria($this->pdo);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Obtiene el último registro de auditoría insertado. */
    private function ultimoRegistro(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM auditoria ORDER BY id DESC LIMIT 1");
        return $stmt->fetch() ?: [];
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    #[Test]
    public function it_logs_crear_action_with_new_data_in_json(): void
    {
        $nuevos = ['nombre' => 'Freno X', 'precio' => 12.50];
        $this->auditoria->registrar('productos', 1, 'crear', null, $nuevos);

        $row = $this->ultimoRegistro();
        $this->assertSame('productos', $row['tabla']);
        $this->assertSame(1, (int) $row['registro_id']);
        $this->assertSame('crear', $row['accion']);
        $this->assertNull($row['datos_anteriores']);
        $decoded = json_decode($row['datos_nuevos'], true);
        $this->assertSame('Freno X', $decoded['nombre']);
        $this->assertSame(12.50, (float) $decoded['precio']);
    }

    #[Test]
    public function it_logs_actualizar_action_with_previous_and_new_data(): void
    {
        $anterior = ['nombre' => 'Freno Viejo', 'precio' => 10.00];
        $nuevo    = ['nombre' => 'Freno Nuevo', 'precio' => 15.00];
        $this->auditoria->registrar('productos', 5, 'actualizar', $anterior, $nuevo);

        $row = $this->ultimoRegistro();
        $this->assertSame('actualizar', $row['accion']);
        $this->assertSame(5, (int) $row['registro_id']);

        $decAnterior = json_decode($row['datos_anteriores'], true);
        $decNuevo    = json_decode($row['datos_nuevos'], true);
        $this->assertSame('Freno Viejo', $decAnterior['nombre']);
        $this->assertSame('Freno Nuevo', $decNuevo['nombre']);
    }

    #[Test]
    public function it_logs_eliminar_action_with_previous_data_and_null_as_new(): void
    {
        $anterior = ['nombre' => 'Producto Borrado', 'stock' => 0];
        $this->auditoria->registrar('productos', 99, 'eliminar', $anterior, null);

        $row = $this->ultimoRegistro();
        $this->assertSame('eliminar', $row['accion']);
        $this->assertNotNull($row['datos_anteriores']);
        $this->assertNull($row['datos_nuevos']);
    }

    #[Test]
    public function it_stores_client_ip_in_audit_record(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.42';
        $this->auditoria->registrar('categorias', 3, 'crear', null, ['nombre' => 'Test']);

        $row = $this->ultimoRegistro();
        $this->assertSame('192.168.1.42', $row['ip']);
    }

    #[Test]
    public function it_filters_audit_log_by_tabla(): void
    {
        $this->auditoria->registrar('productos',  1, 'crear', null, ['n' => 'p']);
        $this->auditoria->registrar('categorias', 2, 'crear', null, ['n' => 'c']);
        $this->auditoria->registrar('productos',  3, 'eliminar', ['n' => 'p2'], null);

        $resultados = $this->auditoria->listar(tabla: 'productos');

        $this->assertCount(2, $resultados);
        foreach ($resultados as $r) {
            $this->assertSame('productos', $r['tabla']);
        }
    }

    #[Test]
    public function it_filters_audit_log_by_action_type(): void
    {
        $this->auditoria->registrar('productos', 1, 'crear',     null,        ['n' => 'p1']);
        $this->auditoria->registrar('productos', 2, 'actualizar', ['n' => 'old'], ['n' => 'new']);
        $this->auditoria->registrar('productos', 3, 'eliminar',  ['n' => 'p3'], null);

        $soloCrear = $this->auditoria->listar(accion: 'crear');
        $this->assertCount(1, $soloCrear);
        $this->assertSame('crear', $soloCrear[0]['accion']);
    }

    #[Test]
    public function it_filters_audit_log_by_date_range(): void
    {
        // Insertar registro con fecha pasada directamente en SQLite
        $this->pdo->exec(
            "INSERT INTO auditoria (tabla, registro_id, accion, datos_nuevos, fecha)
             VALUES ('productos', 1, 'crear', '{\"n\":\"old\"}', '2020-01-01 10:00:00')"
        );
        $this->auditoria->registrar('productos', 2, 'crear', null, ['n' => 'reciente']);

        $hoy    = date('Y-m-d');
        $result = $this->auditoria->listar(fechaDesde: $hoy, fechaHasta: $hoy);

        $this->assertCount(1, $result);
        $this->assertSame('reciente', json_decode($result[0]['datos_nuevos'], true)['n']);
    }

    #[Test]
    public function it_returns_correct_count_with_filters(): void
    {
        $this->auditoria->registrar('productos',  1, 'crear',    null, ['a' => 1]);
        $this->auditoria->registrar('categorias', 2, 'crear',    null, ['b' => 2]);
        $this->auditoria->registrar('productos',  3, 'eliminar', ['a' => 1], null);

        $total          = $this->auditoria->contar();
        $soloProd       = $this->auditoria->contar(tabla: 'productos');
        $soloCateg      = $this->auditoria->contar(tabla: 'categorias');

        $this->assertSame(3, $total);
        $this->assertSame(2, $soloProd);
        $this->assertSame(1, $soloCateg);
    }
}
