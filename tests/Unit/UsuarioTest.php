<?php
/**
 * Tests unitarios para el modelo Usuario.
 *
 * Usa SQLite en memoria: autocontenido, sin depender de la DB MySQL
 * de Docker ni de ningún servicio externo.
 *
 * Cubre: crear, obtenerPorId, listar, actualizar, toggleActivo,
 *        validaciones de contraseña y rol, protección del último admin.
 *
 * Nota: los métodos que usan sintaxis MySQL exclusiva (ON DUPLICATE KEY UPDATE,
 * DATE_ADD, INTERVAL) — verificarRateLimit, registrarIntentoFallido, limpiarIntentos —
 * se prueban en los tests de integración con MySQL real.
 *
 * @package  Es21Plus\Tests\Unit
 * @author   Carlitos6712
 * @author   miguelrechefdez
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UsuarioTest extends TestCase
{
    private PDO     $pdo;
    private Usuario $model;

    // ── Ciclo de vida ─────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->crearSchema();
        $this->model = new Usuario($this->pdo);
    }

    // ── Schema SQLite en memoria ──────────────────────────────────────────────

    /**
     * Crea la tabla usuarios en SQLite in-memory.
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
            CREATE TABLE usuarios (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                username        TEXT    NOT NULL UNIQUE,
                password_hash   TEXT    NOT NULL,
                nombre_completo TEXT    NOT NULL,
                email           TEXT    DEFAULT '',
                rol             TEXT    DEFAULT 'employee',
                activo          INTEGER DEFAULT 1,
                last_login      TEXT    NULL,
                created_at      TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Crea un usuario de prueba y devuelve su ID.
     *
     * @param array<string, mixed> $overrides
     * @return int
     */
    private function crearUsuario(array $overrides = []): int
    {
        $defaults = [
            'username'        => 'employee1',
            'password'        => 'password123',
            'nombre_completo' => 'Employee Uno',
            'email'           => 'employee@test.local',
            'rol'             => 'employee',
        ];
        $d = array_merge($defaults, $overrides);

        return $this->model->crear(
            $d['username'],
            $d['password'],
            $d['nombre_completo'],
            $d['email'],
            $d['rol']
        );
    }

    // ── Tests: crear ─────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_user_and_returns_positive_id(): void
    {
        $id = $this->crearUsuario();

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    #[Test]
    public function it_stores_bcrypt_hash_not_plain_password(): void
    {
        $this->crearUsuario(['username' => 'hashtest', 'password' => 'segura123']);

        $row = $this->pdo->query("SELECT password_hash FROM usuarios WHERE username = 'hashtest'")->fetch();

        $this->assertNotFalse($row);
        $this->assertStringStartsWith('$2y$', $row['password_hash'], 'El hash debe ser bcrypt');
        $this->assertTrue(password_verify('segura123', $row['password_hash']));
    }

    #[Test]
    public function it_throws_exception_when_password_is_too_short(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->crear('shorty', '1234567', 'Shorty', '', 'employee');
    }

    #[Test]
    public function it_throws_exception_when_username_is_duplicated(): void
    {
        $this->crearUsuario(['username' => 'duplicate']);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        $this->crearUsuario(['username' => 'duplicate']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_rol_on_create(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->crear('roltest', 'password123', 'Test', '', 'operario');
    }

    // ── Tests: obtenerPorId ───────────────────────────────────────────────────

    #[Test]
    public function it_retrieves_user_by_id_without_password_hash(): void
    {
        $id  = $this->crearUsuario(['username' => 'visible', 'nombre_completo' => 'Usuario Visible']);
        $row = $this->model->obtenerPorId($id);

        $this->assertSame('visible',         $row['username']);
        $this->assertSame('Usuario Visible', $row['nombre_completo']);
        $this->assertArrayNotHasKey('password_hash', $row, 'password_hash NO debe estar en la respuesta');
    }

    #[Test]
    public function it_throws_404_when_user_not_found(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);

        $this->model->obtenerPorId(99999);
    }

    // ── Tests: listar ─────────────────────────────────────────────────────────

    #[Test]
    public function it_lists_all_users_without_password_hash(): void
    {
        $this->crearUsuario(['username' => 'user1']);
        $this->crearUsuario(['username' => 'user2']);

        $lista = $this->model->listar();

        $this->assertCount(2, $lista);
        foreach ($lista as $row) {
            $this->assertArrayNotHasKey('password_hash', $row, 'password_hash NO debe aparecer en listar()');
        }
    }

    #[Test]
    public function it_returns_empty_array_when_no_users_exist(): void
    {
        $lista = $this->model->listar();

        $this->assertIsArray($lista);
        $this->assertEmpty($lista);
    }

    #[Test]
    public function it_lists_users_ordered_by_nombre_completo_asc(): void
    {
        $this->crearUsuario(['username' => 'zeta', 'nombre_completo' => 'Zeta User']);
        $this->crearUsuario(['username' => 'alfa', 'nombre_completo' => 'Alfa User']);

        $lista = $this->model->listar();

        $this->assertSame('Alfa User', $lista[0]['nombre_completo']);
        $this->assertSame('Zeta User', $lista[1]['nombre_completo']);
    }

    // ── Tests: actualizar ─────────────────────────────────────────────────────

    #[Test]
    public function it_updates_user_data_without_changing_password(): void
    {
        $id          = $this->crearUsuario(['username' => 'before', 'password' => 'pass_original1']);
        $hashAntes   = $this->pdo->query("SELECT password_hash FROM usuarios WHERE id = {$id}")->fetchColumn();

        $result = $this->model->actualizar($id, 'after', 'Nombre Nuevo', 'new@test.local', 'admin');

        $this->assertTrue($result);
        $row       = $this->model->obtenerPorId($id);
        $hashDespues = $this->pdo->query("SELECT password_hash FROM usuarios WHERE id = {$id}")->fetchColumn();

        $this->assertSame('after',          $row['username']);
        $this->assertSame('Nombre Nuevo',   $row['nombre_completo']);
        $this->assertSame('admin',          $row['rol']);
        $this->assertSame($hashAntes, $hashDespues, 'El password_hash no debe cambiar al actualizar');
    }

    #[Test]
    public function it_throws_exception_when_updating_with_duplicate_username(): void
    {
        $this->crearUsuario(['username' => 'taken']);
        $id = $this->crearUsuario(['username' => 'free']);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        $this->model->actualizar($id, 'taken', 'Nombre', '', 'employee');
    }

    #[Test]
    public function it_allows_updating_own_username_without_conflict(): void
    {
        $id = $this->crearUsuario(['username' => 'myuser']);

        // Actualizar con el mismo username no debe lanzar excepción
        $result = $this->model->actualizar($id, 'myuser', 'Mi Nombre Nuevo', '', 'employee');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_throws_exception_for_invalid_rol_on_update(): void
    {
        $id = $this->crearUsuario();

        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->actualizar($id, 'roltest', 'Test', '', 'superuser');
    }

    // ── Tests: toggleActivo ───────────────────────────────────────────────────

    #[Test]
    public function it_deactivates_an_active_operario(): void
    {
        $id     = $this->crearUsuario(['username' => 'opuser', 'rol' => 'employee']);
        $nuevo  = $this->model->toggleActivo($id);

        $this->assertFalse($nuevo);
        $row = $this->model->obtenerPorId($id);
        $this->assertSame(0, (int) $row['activo']);
    }

    #[Test]
    public function it_reactivates_an_inactive_user(): void
    {
        $id = $this->crearUsuario(['username' => 'reactive', 'rol' => 'employee']);
        $this->pdo->exec("UPDATE usuarios SET activo = 0 WHERE id = {$id}");

        $nuevo = $this->model->toggleActivo($id);

        $this->assertTrue($nuevo);
        $row = $this->model->obtenerPorId($id);
        $this->assertSame(1, (int) $row['activo']);
    }

    #[Test]
    public function it_allows_deactivating_admin_when_another_admin_exists(): void
    {
        $this->crearUsuario(['username' => 'admin1', 'rol' => 'admin']);
        $id2 = $this->crearUsuario(['username' => 'admin2', 'rol' => 'admin']);

        // No debe lanzar excepción porque quedan 2 admins activos
        $nuevo = $this->model->toggleActivo($id2);

        $this->assertFalse($nuevo);
    }

    #[Test]
    public function it_throws_exception_when_deactivating_the_only_active_admin(): void
    {
        $id = $this->crearUsuario(['username' => 'solo_admin', 'rol' => 'admin']);
        // Solo hay un admin activo en la BD

        $this->expectException(AppException::class);
        $this->expectExceptionCode(409);

        $this->model->toggleActivo($id);
    }

    // ── Tests: actualizarPerfil ───────────────────────────────────────────────

    #[Test]
    public function it_updates_nombre_completo_and_email_correctly(): void
    {
        $id = $this->crearUsuario(['username' => 'perfil1', 'nombre_completo' => 'Nombre Antiguo']);

        $result = $this->model->actualizarPerfil($id, 'Nombre Nuevo', 'nuevo@test.local');

        $this->assertTrue($result);
        $row = $this->model->obtenerPorId($id);
        $this->assertSame('Nombre Nuevo',      $row['nombre_completo']);
        $this->assertSame('nuevo@test.local',  $row['email']);
    }

    #[Test]
    public function it_registers_profile_update_in_auditoria(): void
    {
        $auditoria = new Auditoria($this->pdo);
        $id        = $this->crearUsuario(['username' => 'perfil2', 'nombre_completo' => 'Antes']);

        $this->model->actualizarPerfil($id, 'Despues', 'despues@test.local', $auditoria);

        $row = $this->pdo->query(
            "SELECT * FROM auditoria WHERE tabla = 'usuarios' AND registro_id = {$id} AND accion = 'actualizar'"
        )->fetch();

        $this->assertNotFalse($row, 'Debe existir un registro de auditoría tras actualizarPerfil');
        $this->assertStringContainsString('Antes',   $row['datos_anteriores']);
        $this->assertStringContainsString('Despues', $row['datos_nuevos']);
    }

    #[Test]
    public function it_throws_404_when_updating_nonexistent_user_profile(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);

        $this->model->actualizarPerfil(99999, 'Nombre', '');
    }

    #[Test]
    public function it_does_not_change_username_or_rol_when_updating_profile(): void
    {
        $id = $this->crearUsuario(['username' => 'notouch', 'rol' => 'employee']);

        $this->model->actualizarPerfil($id, 'Nombre Cambiado', 'x@y.com');

        $row = $this->model->obtenerPorId($id);
        $this->assertSame('notouch',  $row['username']);
        $this->assertSame('employee', $row['rol']);
    }

    // ── Tests: cambiarPassword ────────────────────────────────────────────────

    #[Test]
    public function it_changes_password_when_current_is_correct(): void
    {
        $id = $this->crearUsuario(['username' => 'pwchange', 'password' => 'password123']);

        $this->model->cambiarPassword($id, 'password123', 'nuevaPass456');

        $row = $this->pdo->query("SELECT password_hash FROM usuarios WHERE id = {$id}")->fetch();
        $this->assertNotFalse($row);
        $this->assertTrue(password_verify('nuevaPass456', $row['password_hash']));
    }

    #[Test]
    public function it_hashes_new_password_with_bcrypt(): void
    {
        $id = $this->crearUsuario(['username' => 'bcryptcheck', 'password' => 'password123']);

        $this->model->cambiarPassword($id, 'password123', 'nuevaPass789');

        $row = $this->pdo->query("SELECT password_hash FROM usuarios WHERE id = {$id}")->fetch();
        $this->assertNotFalse($row);
        $this->assertStringStartsWith('$2y$', $row['password_hash'], 'El hash debe ser bcrypt');

        $info = password_get_info($row['password_hash']);
        $this->assertSame('bcrypt',  $info['algoName']);
        $this->assertGreaterThanOrEqual(12, $info['options']['cost']);
    }

    #[Test]
    public function it_throws_401_when_current_password_is_wrong(): void
    {
        $id = $this->crearUsuario(['username' => 'wrongpw', 'password' => 'password123']);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(401);

        $this->model->cambiarPassword($id, 'incorrecta999', 'nuevaPass456');
    }

    #[Test]
    public function it_throws_400_when_new_password_is_too_short(): void
    {
        $id = $this->crearUsuario(['username' => 'shortpw', 'password' => 'password123']);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(400);

        $this->model->cambiarPassword($id, 'password123', 'corta1');
    }

    #[Test]
    public function it_registers_password_change_in_auditoria(): void
    {
        $auditoria = new Auditoria($this->pdo);
        $id        = $this->crearUsuario(['username' => 'pwaudit', 'password' => 'password123']);

        $this->model->cambiarPassword($id, 'password123', 'nuevaPass456', $auditoria);

        $row = $this->pdo->query(
            "SELECT * FROM auditoria WHERE tabla = 'usuarios' AND registro_id = {$id} AND accion = 'actualizar'"
        )->fetch();

        $this->assertNotFalse($row, 'Debe existir un registro de auditoría tras cambiarPassword');
        $this->assertStringContainsString('password_hash', $row['datos_anteriores']);
        $this->assertStringContainsString('password_hash', $row['datos_nuevos']);
    }

    #[Test]
    public function it_throws_404_when_changing_password_of_nonexistent_user(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);

        $this->model->cambiarPassword(99999, 'cualquier', 'cualquier123');
    }
}
