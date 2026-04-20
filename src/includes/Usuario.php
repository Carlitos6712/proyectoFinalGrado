<?php
/**
 * Modelo de gestión de usuarios y autenticación.
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
 * @author   miguelrechefdez
 * @version  1.0.0
 */
class Usuario
{
    private PDO $pdo;

    /** Máximo de intentos fallidos antes de bloquear. */
    private const MAX_INTENTOS = 5;

    /** Minutos de bloqueo tras superar el límite. */
    private const MINUTOS_BLOQUEO = 15;

    /**
     * @throws AppException Si falla la conexión.
     */
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Busca un usuario activo por su username.
     *
     * @param string $username Nombre de usuario.
     * @return array<string, mixed>|null null si no existe o está inactivo.
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
     * @param string $username Nombre de usuario.
     * @param string $password Contraseña en texto plano.
     * @throws AppException Si el usuario no existe o la contraseña es incorrecta.
     * @return array<string, mixed> Datos del usuario autenticado.
     */
    public function verificarCredenciales(string $username, string $password): array
    {
        $usuario = $this->buscarPorUsername($username);
        if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
            throw new AppException('Usuario o contraseña incorrectos.', 401);
        }
        return $usuario;
    }

    /**
     * Actualiza el timestamp de último login.
     *
     * @param int $id ID del usuario.
     * @return void
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
     */
    public function limpiarIntentos(string $ip): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM intentos_login WHERE ip = :ip");
        $stmt->execute([':ip' => $ip]);
    }
}
