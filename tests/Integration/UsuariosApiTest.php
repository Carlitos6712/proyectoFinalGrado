<?php
/**
 * Tests de integración para el modelo Usuario.
 *
 * Requiere una conexión MySQL activa (docker-compose DB).
 * Verifica: listar, crear, obtenerPorId, actualizar, toggleActivo
 * y las protecciones de unicidad y último admin activo.
 *
 * Todos los usuarios creados tienen username prefijado con 'INTTEST_'
 * para facilitar la limpieza en tearDown sin afectar datos de producción.
 *
 * @package  Es21Plus\Tests\Integration
 * @author   Carlitos6712
 * @author   miguelrechefdez
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UsuariosApiTest extends TestCase
{
    private Usuario $model;
    private PDO     $pdo;
    /** @var int[] IDs de usuarios creados en cada test. */
    private array $createdIds = [];

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo   = Database::getInstance();
        $this->model = new Usuario($this->pdo);
    }

    protected function tearDown(): void
    {
        if ($this->createdIds) {
            $ids = implode(',', array_map('intval', $this->createdIds));
            $this->pdo->exec("DELETE FROM usuarios WHERE id IN ({$ids}) AND username LIKE 'INTTEST_%'");
            $this->createdIds = [];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Crea un usuario de prueba en la BD real y registra su ID para limpiar.
     *
     * @param array<string, string> $overrides
     * @return int
     */
    private function crearUsuario(array $overrides = []): int
    {
        $suffix = substr(str_replace('.', '', uniqid('', true)), -8);

        $defaults = [
            'username'        => "INTTEST_u{$suffix}",
            'password'        => 'Password123!',
            'nombre_completo' => "Test User {$suffix}",
            'email'           => "inttest{$suffix}@test.local",
            'rol'             => 'employee',
        ];
        $d = array_merge($defaults, $overrides);

        $id = $this->model->crear(
            $d['username'],
            $d['password'],
            $d['nombre_completo'],
            $d['email'],
            $d['rol']
        );
        $this->createdIds[] = $id;
        return $id;
    }

    // ── Tests: crear ─────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_user_in_real_database(): void
    {
        $id = $this->crearUsuario();

        $this->assertGreaterThan(0, $id);
    }

    #[Test]
    public function it_persists_hashed_password_not_plain_text(): void
    {
        $id = $this->crearUsuario(['username' => 'INTTEST_hashcheck']);

        $row = $this->pdo->query("SELECT password_hash FROM usuarios WHERE id = {$id}")->fetch();

        $this->assertNotFalse($row);
        $this->assertStringStartsWith('$2y$', $row['password_hash']);
        $this->assertTrue(password_verify('Password123!', $row['password_hash']));
    }

    #[Test]
    public function it_throws_409_on_duplicate_username(): void
    {
        $this->crearUsuario(['username' => 'INTTEST_dup']);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        // intentamos crear el mismo username de nuevo
        $dupId = $this->model->crear('INTTEST_dup', 'Password123!', 'Dup Test', '', 'employee');
        $this->createdIds[] = $dupId; // por si no lanza
    }

    #[Test]
    public function it_throws_400_for_password_shorter_than_8_chars(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $id = $this->model->crear('INTTEST_shortpw', '1234567', 'Short', '', 'employee');
        $this->createdIds[] = $id;
    }

    // ── Tests: obtenerPorId ───────────────────────────────────────────────────

    #[Test]
    public function it_fetches_user_by_id_from_real_database(): void
    {
        $id  = $this->crearUsuario(['username' => 'INTTEST_fetch', 'nombre_completo' => 'Fetch Test']);
        $row = $this->model->obtenerPorId($id);

        $this->assertSame('INTTEST_fetch', $row['username']);
        $this->assertSame('Fetch Test',   $row['nombre_completo']);
        $this->assertArrayNotHasKey('password_hash', $row);
    }

    #[Test]
    public function it_throws_404_for_nonexistent_user(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);

        $this->model->obtenerPorId(999999999);
    }

    // ── Tests: listar ─────────────────────────────────────────────────────────

    #[Test]
    public function it_lists_users_and_none_expose_password_hash(): void
    {
        $this->crearUsuario(['username' => 'INTTEST_list1']);
        $this->crearUsuario(['username' => 'INTTEST_list2']);

        $lista = $this->model->listar();

        $this->assertNotEmpty($lista);
        foreach ($lista as $row) {
            $this->assertArrayNotHasKey('password_hash', $row, 'password_hash nunca debe estar en listar()');
        }
    }

    // ── Tests: actualizar ─────────────────────────────────────────────────────

    #[Test]
    public function it_updates_user_fields_without_changing_password_hash(): void
    {
        $id       = $this->crearUsuario(['username' => 'INTTEST_upd']);
        $hashAntes = $this->pdo->query("SELECT password_hash FROM usuarios WHERE id = {$id}")->fetchColumn();

        $this->model->actualizar($id, 'INTTEST_upd_new', 'Updated Name', 'upd@test.local', 'admin');

        $row       = $this->model->obtenerPorId($id);
        $hashDespues = $this->pdo->query("SELECT password_hash FROM usuarios WHERE id = {$id}")->fetchColumn();

        $this->assertSame('INTTEST_upd_new', $row['username']);
        $this->assertSame('Updated Name',    $row['nombre_completo']);
        $this->assertSame('admin',           $row['rol']);
        $this->assertSame($hashAntes, $hashDespues);
    }

    #[Test]
    public function it_throws_409_when_updating_username_to_an_existing_one(): void
    {
        $this->crearUsuario(['username' => 'INTTEST_taken']);
        $id = $this->crearUsuario(['username' => 'INTTEST_free2']);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        $this->model->actualizar($id, 'INTTEST_taken', 'Name', '', 'employee');
    }

    // ── Tests: toggleActivo ───────────────────────────────────────────────────

    #[Test]
    public function it_deactivates_employee_and_returns_false(): void
    {
        $id    = $this->crearUsuario(['username' => 'INTTEST_tgl', 'rol' => 'employee']);
        $nuevo = $this->model->toggleActivo($id);

        $this->assertFalse($nuevo);
        $row = $this->model->obtenerPorId($id);
        $this->assertSame(0, (int) $row['activo']);
    }

    #[Test]
    public function it_reactivates_inactive_user_and_returns_true(): void
    {
        $id = $this->crearUsuario(['username' => 'INTTEST_react', 'rol' => 'employee']);
        $this->pdo->exec("UPDATE usuarios SET activo = 0 WHERE id = {$id}");

        $nuevo = $this->model->toggleActivo($id);

        $this->assertTrue($nuevo);
    }

    #[Test]
    public function it_throws_409_when_deactivating_the_only_active_admin(): void
    {
        // Esta lógica se prueba exhaustivamente con SQLite en Unit/UsuarioTest.php.
        // No se replica aquí para evitar el riesgo de dejar cuentas de admin
        // reales desactivadas si el proceso se interrumpe durante la prueba.
        $this->markTestSkipped(
            'Protección de último admin cubierta en Unit/UsuarioTest (SQLite). ' .
            'Se omite en integración para no mutar cuentas de admin reales.'
        );
    }
}
