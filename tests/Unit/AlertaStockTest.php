<?php
/**
 * Tests unitarios para el servicio AlertaStock.
 *
 * Usa SQLite en memoria y un transport callable capturado para
 * verificar el comportamiento de envío de alertas sin necesidad
 * de conexión MySQL ni servidor SMTP real.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AlertaStockTest extends TestCase
{
    private PDO $pdo;
    /** @var array<int, array{to: string, subject: string, body: string}> */
    private array $emailsSent = [];
    private AlertaStock $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->crearTablas();
        $this->emailsSent = [];

        $capturado = &$this->emailsSent;
        $transport = static function (string $to, string $subject, string $body) use (&$capturado): void {
            $capturado[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
        };

        $this->service = new AlertaStock($this->pdo, $transport);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function crearTablas(): void
    {
        $this->pdo->exec("
            CREATE TABLE productos (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre        TEXT    NOT NULL,
                stock         INTEGER DEFAULT 0,
                stock_minimo  INTEGER DEFAULT 5,
                alertas_email INTEGER DEFAULT 1,
                deleted_at    TEXT    NULL
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
        $this->pdo->exec("
            CREATE TABLE movimientos (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                producto_id INTEGER NOT NULL,
                tipo        TEXT    NOT NULL,
                cantidad    INTEGER NOT NULL,
                fecha       TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /**
     * Inserta un producto de prueba y devuelve su ID.
     *
     * @param array<string, mixed> $overrides
     * @return int
     */
    private function insertarProducto(array $overrides = []): int
    {
        $defaults = [
            'nombre'        => 'Freno Premium',
            'stock'         => 10,
            'stock_minimo'  => 5,
            'alertas_email' => 1,
        ];
        $data = array_merge($defaults, $overrides);
        $this->pdo->exec(
            "INSERT INTO productos (nombre, stock, stock_minimo, alertas_email)
             VALUES ('{$data['nombre']}', {$data['stock']}, {$data['stock_minimo']}, {$data['alertas_email']})"
        );
        return (int) $this->pdo->lastInsertId();
    }

    private function insertarAlerta(int $productoId, int $stockAlEnviar): void
    {
        $this->pdo->exec(
            "INSERT INTO alertas_stock (producto_id, stock_al_enviar)
             VALUES ({$productoId}, {$stockAlEnviar})"
        );
    }

    private function insertarMovimiento(int $productoId, string $tipo, string $fecha = 'now'): void
    {
        $this->pdo->exec(
            "INSERT INTO movimientos (producto_id, tipo, cantidad, fecha)
             VALUES ({$productoId}, '{$tipo}', 1, datetime('{$fecha}'))"
        );
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    #[Test]
    public function it_sends_email_when_stock_drops_below_minimum_after_salida(): void
    {
        $_ENV['ALERT_EMAIL'] = 'admin@test.com';
        $productoId = $this->insertarProducto(['stock' => 3, 'stock_minimo' => 5]);

        $this->service->verificarYEnviar($productoId, 3);

        $this->assertCount(1, $this->emailsSent, 'Debe enviar exactamente un email');
        $this->assertSame('admin@test.com', $this->emailsSent[0]['to']);
    }

    #[Test]
    public function it_does_not_send_duplicate_alert_for_same_stock_low_episode(): void
    {
        $_ENV['ALERT_EMAIL'] = 'admin@test.com';
        $productoId = $this->insertarProducto(['stock' => 3, 'stock_minimo' => 5]);
        // Simular que ya se envió una alerta y no ha habido entradas desde entonces
        $this->insertarAlerta($productoId, 3);

        $this->service->verificarYEnviar($productoId, 2);

        $this->assertCount(0, $this->emailsSent, 'No debe enviar alerta duplicada en el mismo episodio');
    }

    #[Test]
    public function it_sends_new_alert_after_stock_recovered_via_entrada(): void
    {
        $_ENV['ALERT_EMAIL'] = 'admin@test.com';
        $productoId = $this->insertarProducto(['stock' => 3, 'stock_minimo' => 5]);

        // Primera alerta enviada ayer
        $this->pdo->exec(
            "INSERT INTO alertas_stock (producto_id, stock_al_enviar, enviada_at)
             VALUES ({$productoId}, 3, datetime('now', '-1 day'))"
        );
        // Entrada de stock HOY (stock recuperado)
        $this->insertarMovimiento($productoId, 'entrada', 'now');

        // Nueva salida vuelve a poner el stock bajo
        $this->service->verificarYEnviar($productoId, 2);

        $this->assertCount(1, $this->emailsSent, 'Debe enviar alerta nueva tras recuperación de stock');
    }

    #[Test]
    public function it_does_not_send_alert_when_product_has_alerts_disabled(): void
    {
        $_ENV['ALERT_EMAIL'] = 'admin@test.com';
        $productoId = $this->insertarProducto([
            'stock'         => 3,
            'stock_minimo'  => 5,
            'alertas_email' => 0,
        ]);

        $this->service->verificarYEnviar($productoId, 3);

        $this->assertCount(0, $this->emailsSent, 'No debe enviar alerta cuando el producto la tiene desactivada');
    }

    #[Test]
    public function it_does_not_send_alert_when_salida_keeps_stock_above_minimum(): void
    {
        $_ENV['ALERT_EMAIL'] = 'admin@test.com';
        $productoId = $this->insertarProducto(['stock' => 10, 'stock_minimo' => 5]);

        $this->service->verificarYEnviar($productoId, 8);

        $this->assertCount(0, $this->emailsSent, 'No debe enviar alerta cuando el stock sigue sobre el mínimo');
    }

    #[Test]
    public function it_includes_product_name_and_current_stock_in_email_body(): void
    {
        $_ENV['ALERT_EMAIL'] = 'admin@test.com';
        $productoId = $this->insertarProducto(['nombre' => 'Cadena 520', 'stock' => 2, 'stock_minimo' => 5]);

        $this->service->verificarYEnviar($productoId, 2);

        $this->assertCount(1, $this->emailsSent);
        $body = $this->emailsSent[0]['body'];
        $this->assertStringContainsString('Cadena 520', $body, 'El email debe incluir el nombre del producto');
        $this->assertStringContainsString('2', $body, 'El email debe incluir el stock actual');
    }

    #[Test]
    public function it_does_not_send_alert_when_alert_email_env_is_not_set(): void
    {
        unset($_ENV['ALERT_EMAIL']);
        $productoId = $this->insertarProducto(['stock' => 3, 'stock_minimo' => 5]);

        $this->service->verificarYEnviar($productoId, 3);

        $this->assertCount(0, $this->emailsSent, 'No debe enviar si ALERT_EMAIL no está configurado');
    }
}
