<?php
/**
 * Modelo de gestión de usuarios y autenticación.
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */

require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';

class Usuario
{
    private PDO $pdo;

    /** Máximo de intentos fallidos antes de bloquear. */
    private const MAX_INTENTOS = 5;

    /** Minutos de bloqueo tras superar el límite. */
    private const MINUTOS_BLOQUEO = 15;

    /** Cost mínimo para password_hash bcrypt. */
    private const BCRYPT_COST = 12;

    /** Roles válidos del sistema. */
    private const ROLES_VALIDOS = ['admin', 'operario'];

    /**
     * @param PDO|null $pdo Conexión inyectada (útil para tests SQLite); null usa el singleton MySQL.
     * @throws AppException Si falla la conexión.
     * @author Carlitos6712
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    // ── CRUD de usuarios ──────────────────────────────────────────────────────

    /**
     * Lista todos los usuarios sin exponer el campo password_hash.
     *
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function listar(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, username, nombre_completo, email, rol, activo, last_login, created_at
             FROM usuarios
             ORDER BY nombre_completo ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un usuario por su ID sin exponer password_hash.
     *
     * @param int $id ID del usuario.
     * @throws AppException Si el usuario no existe.
     * @return array<string, mixed>
     * @author Carlitos6712
     */
    public function obtenerPorId(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, nombre_completo, email, rol, activo, last_login, created_at
             FROM usuarios WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new AppException("Usuario #{$id} no encontrado.", 404);
        }
        return $row;
    }

    /**
     * Crea un nuevo usuario con la contraseña hasheada en bcrypt.
     *
     * @param string $username        Nombre de usuario único.
     * @param string $password        Contraseña en texto plano (mínimo 8 caracteres).
     * @param string $nombreCompleto  Nombre completo.
     * @param string $email           Email (opcional).
     * @param string $rol             'admin' o 'operario'.
     * @throws AppException Si el username ya existe, el rol no es válido o la contraseña es demasiado corta.
     * @return int ID del usuario creado.
     * @author Carlitos6712
     */
    public function crear(
        string $username,
        string $password,
        string $nombreCompleto,
        string $email = '',
        string $rol   = 'operario'
    ): int {
        $this->validarRol($rol);
        if (mb_strlen($password) < 8) {
            throw new AppException('La contraseña debe tener al menos 8 caracteres.', 400);
        }
        if ($this->existeUsername($username)) {
            throw new AppException("El username '{$username}' ya está en uso.", 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
        $stmt = $this->pdo->prepare(
            "INSERT INTO usuarios (username, password_hash, nombre_completo, email, rol)
             VALUES (:username, :password_hash, :nombre_completo, :email, :rol)"
        );
        $stmt->execute([
            ':username'        => $username,
            ':password_hash'   => $hash,
            ':nombre_completo' => $nombreCompleto,
            ':email'           => $email,
            ':rol'             => $rol,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza los datos de un usuario (sin tocar la contraseña).
     *
     * @param int    $id             ID del usuario a actualizar.
     * @param string $username       Nuevo username (único, excluye al propio usuario).
     * @param string $nombreCompleto Nuevo nombre completo.
     * @param string $email          Nuevo email.
     * @param string $rol            Nuevo rol ('admin' o 'operario').
     * @throws AppException Si el rol es inválido, el username ya está en uso por otro usuario,
     *                      o el usuario no existe.
     * @return bool
     * @author Carlitos6712
     */
    public function actualizar(
        int    $id,
        string $username,
        string $nombreCompleto,
        string $email,
        string $rol
    ): bool {
        $this->validarRol($rol);
        $this->obtenerPorId($id); // lanza 404 si no existe

        if ($this->existeUsername($username, $id)) {
            throw new AppException("El username '{$username}' ya está en uso por otro usuario.", 409);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE usuarios
             SET username = :username, nombre_completo = :nombre_completo,
                 email = :email, rol = :rol
             WHERE id = :id"
        );
        return $stmt->execute([
            ':username'        => $username,
            ':nombre_completo' => $nombreCompleto,
            ':email'           => $email,
            ':rol'             => $rol,
            ':id'              => $id,
        ]);
    }

    /**
     * Activa o desactiva un usuario.
     *
     * No permite desactivar al último admin activo del sistema.
     *
     * @param int $id ID del usuario a activar/desactivar.
     * @throws AppException Si es el último admin activo (409) o el usuario no existe (404).
     * @return bool Nuevo estado: true = activo, false = inactivo.
     * @author Carlitos6712
     */
    public function toggleActivo(int $id): bool
    {
        $usuario = $this->obtenerPorId($id);

        // Protección: no desactivar al último admin activo
        if ((int)$usuario['activo'] === 1 && $usuario['rol'] === 'admin') {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND activo = 1"
            );
            $stmt->execute();
            if ((int)$stmt->fetchColumn() <= 1) {
                throw new AppException(
                    'No se puede desactivar al único administrador activo del sistema.',
                    409
                );
            }
        }

        $nuevoEstado = (int)$usuario['activo'] === 1 ? 0 : 1;
        $stmt = $this->pdo->prepare("UPDATE usuarios SET activo = :activo WHERE id = :id");
        $stmt->execute([':activo' => $nuevoEstado, ':id' => $id]);
        return (bool) $nuevoEstado;
    }

    // ── Perfil propio ─────────────────────────────────────────────────────────

    /**
     * Actualiza el nombre completo y el email del usuario indicado.
     *
     * Registra la acción en la tabla de auditoría.
     * No permite cambiar username, rol ni estado desde este método
     * (esas operaciones requieren privilegio admin).
     *
     * @param int         $id             ID del usuario a actualizar.
     * @param string      $nombreCompleto Nuevo nombre completo.
     * @param string      $email          Nuevo email (puede ser vacío).
     * @param object|null $auditoria      Instancia de Auditoria para registrar el cambio.
     * @throws AppException Si el usuario no existe (404).
     * @return bool
     * @author Carlitos6712
     */
    public function actualizarPerfil(
        int    $id,
        string $nombreCompleto,
        string $email,
        ?object $auditoria = null
    ): bool {
        $anterior = $this->obtenerPorId($id); // lanza 404 si no existe

        $stmt = $this->pdo->prepare(
            "UPDATE usuarios
             SET nombre_completo = :nombre_completo, email = :email
             WHERE id = :id"
        );
        $ok = $stmt->execute([
            ':nombre_completo' => $nombreCompleto,
            ':email'           => $email,
            ':id'              => $id,
        ]);

        if ($ok && $auditoria !== null) {
            $auditoria->registrar(
                'usuarios',
                $id,
                'actualizar',
                ['nombre_completo' => $anterior['nombre_completo'], 'email' => $anterior['email']],
                ['nombre_completo' => $nombreCompleto,              'email' => $email]
            );
        }

        return $ok;
    }

    /**
     * Cambia la contraseña de un usuario tras verificar la contraseña actual.
     *
     * Requisitos de la nueva contraseña: mínimo 8 caracteres.
     * La nueva contraseña se hashea con bcrypt (cost ≥ 12).
     * Registra la acción en la tabla de auditoría (sin incluir el hash).
     *
     * @param int         $id              ID del usuario.
     * @param string      $passwordActual  Contraseña actual en texto plano.
     * @param string      $passwordNueva   Nueva contraseña en texto plano.
     * @param object|null $auditoria       Instancia de Auditoria para registrar el cambio.
     * @throws AppException Si el usuario no existe (404), la contraseña actual es incorrecta (401)
     *                      o la nueva contraseña es demasiado corta (400).
     * @return void
     * @author Carlitos6712
     */
    public function cambiarPassword(
        int    $id,
        string $passwordActual,
        string $passwordNueva,
        ?object $auditoria = null
    ): void {
        if (mb_strlen($passwordNueva) < 8) {
            throw new AppException('La nueva contraseña debe tener al menos 8 caracteres.', 400);
        }

        // Obtener el hash actual directamente (no usamos obtenerPorId que lo excluye)
        $stmt = $this->pdo->prepare("SELECT password_hash FROM usuarios WHERE id = :id AND activo = 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new AppException("Usuario #{$id} no encontrado o inactivo.", 404);
        }

        if (!password_verify($passwordActual, $row['password_hash'])) {
            throw new AppException('La contraseña actual no es correcta.', 401);
        }

        $nuevoHash = password_hash($passwordNueva, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
        $stmt = $this->pdo->prepare("UPDATE usuarios SET password_hash = :hash WHERE id = :id");
        $stmt->execute([':hash' => $nuevoHash, ':id' => $id]);

        if ($auditoria !== null) {
            $auditoria->registrar(
                'usuarios',
                $id,
                'actualizar',
                ['campo' => 'password_hash'],
                ['campo' => 'password_hash', 'accion' => 'contraseña cambiada']
            );
        }
    }

    // ── Autenticación y sesión ────────────────────────────────────────────────

    /**
     * Busca un usuario activo por su username.
     *
     * @param string $username Nombre de usuario.
     * @return array<string, mixed>|null null si no existe o está inactivo.
     * @author Carlitos6712
     */
    public function buscarPorUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM usuarios WHERE username = :username AND activo = 1"
        );
        $stmt->execute([':username' => $username]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Verifica credenciales y retorna el usuario si son correctas.
     *
     * password_hash se usa internamente para la verificación y se elimina
     * del array de retorno para evitar exponer el hash a los llamadores.
     *
     * @param string $username Nombre de usuario.
     * @param string $password Contraseña en texto plano.
     * @throws AppException Si el usuario no existe o la contraseña es incorrecta.
     * @return array<string, mixed> Datos del usuario autenticado (sin password_hash).
     * @author Carlitos6712
     */
    public function verificarCredenciales(string $username, string $password): array
    {
        $usuario = $this->buscarPorUsername($username);
        if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
            throw new AppException('Usuario o contraseña incorrectos.', 401);
        }
        unset($usuario['password_hash']);
        return $usuario;
    }

    /**
     * Actualiza el timestamp de último login.
     *
     * @param int $id ID del usuario.
     * @return void
     * @author Carlitos6712
     */
    public function registrarLogin(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios SET last_login = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * Comprueba si una IP está bloqueada por demasiados intentos fallidos.
     *
     * @param string $ip Dirección IP del cliente.
     * @throws AppException Si la IP está bloqueada temporalmente.
     * @return void
     * @author Carlitos6712
     */
    public function verificarRateLimit(string $ip): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT intentos, bloqueado_hasta FROM intentos_login WHERE ip = :ip"
        );
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch();

        if ($row && $row['bloqueado_hasta'] && new \DateTime() < new \DateTime($row['bloqueado_hasta'])) {
            throw new AppException('Demasiados intentos fallidos. Inténtalo más tarde.', 429);
        }
    }

    /**
     * Registra un intento de login fallido para una IP.
     *
     * @param string $ip Dirección IP del cliente.
     * @return void
     * @author Carlitos6712
     */
    public function registrarIntentoFallido(string $ip): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO intentos_login (ip, intentos) VALUES (:ip, 1)
             ON DUPLICATE KEY UPDATE
               intentos = intentos + 1,
               bloqueado_hasta = IF(intentos + 1 >= :max,
                   DATE_ADD(NOW(), INTERVAL :min MINUTE), NULL)"
        );
        $stmt->execute([
            ':ip'  => $ip,
            ':max' => self::MAX_INTENTOS,
            ':min' => self::MINUTOS_BLOQUEO,
        ]);
    }

    /**
     * Limpia los intentos fallidos de una IP tras login exitoso.
     *
     * @param string $ip Dirección IP del cliente.
     * @return void
     * @author Carlitos6712
     */
    public function limpiarIntentos(string $ip): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM intentos_login WHERE ip = :ip");
        $stmt->execute([':ip' => $ip]);
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Lanza excepción si el rol no es válido.
     *
     * @param string $rol Rol a validar.
     * @throws AppException Si el rol no está permitido.
     * @return void
     * @author Carlitos6712
     */
    private function validarRol(string $rol): void
    {
        if (!in_array($rol, self::ROLES_VALIDOS, true)) {
            throw new AppException("Rol inválido: '{$rol}'. Valores permitidos: admin, operario.", 400);
        }
    }

    /**
     * Comprueba si un username ya existe en la BD (opcionalmente excluyendo un ID).
     *
     * @param string   $username Username a comprobar.
     * @param int|null $excluirId ID a excluir (para ediciones propias).
     * @return bool
     * @author Carlitos6712
     */
    private function existeUsername(string $username, ?int $excluirId = null): bool
    {
        if ($excluirId !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM usuarios WHERE username = :username AND id != :id"
            );
            $stmt->execute([':username' => $username, ':id' => $excluirId]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM usuarios WHERE username = :username"
            );
            $stmt->execute([':username' => $username]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }
}
