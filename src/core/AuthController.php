<?php
/**
 * Controlador de autenticación para empleados de empresa (multi-tenant).
 *
 * Gestiona el login con empresa + email + contraseña usando la tabla employees.
 * El login de superadmin usa /login.php con la tabla usuarios de forma independiente.
 * Incluye protección contra fuerza bruta vía SecurityService.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.1.0
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/SecurityService.php';

class AuthController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Autentica un empleado de empresa con protección contra fuerza bruta.
     *
     * @param string $business  Nombre o slug del negocio.
     * @param string $email     Email del empleado.
     * @param string $password  Contraseña en texto plano.
     * @throws AppException En caso de bloqueo, credenciales incorrectas o empresa inactiva.
     * @return void — redirige a /index.php en éxito.
     */
    public function login(string $business, string $email, string $password): void
    {
        $business = trim($business);
        $email    = trim($email);
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($business === '' || $email === '' || $password === '') {
            throw new AppException('Empresa, email y contraseña son obligatorios.', 400);
        }

        // 1. Verificar bloqueo por fuerza bruta
        if (SecurityService::isIpBlocked($ip)) {
            throw new AppException('Demasiados intentos. Espera 15 minutos.', 429);
        }

        // 2. Buscar empresa activa
        $stmt = $this->pdo->prepare(
            "SELECT * FROM businesses WHERE (name = ? OR slug = ?) AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$business, $business]);
        $biz = $stmt->fetch();
        if (!$biz) {
            SecurityService::registerFailedAttempt($ip, $email);
            throw new AppException('Empresa no encontrada o inactiva.', 401);
        }

        // 3. Buscar empleado activo en esa empresa
        $stmt = $this->pdo->prepare(
            "SELECT * FROM employees WHERE email = ? AND business_id = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$email, $biz['id']]);
        $employee = $stmt->fetch();
        if (!$employee) {
            SecurityService::registerFailedAttempt($ip, $email);
            throw new AppException('Credenciales incorrectas.', 401);
        }

        // 4. Verificar contraseña
        if (!password_verify($password, $employee['password'])) {
            SecurityService::registerFailedAttempt($ip, $email);
            throw new AppException('Credenciales incorrectas.', 401);
        }

        // 5. Login correcto: limpiar intentos y crear sesión
        SecurityService::clearAttempts($ip);
        Session::setBusinessUser($employee, $biz);
        SecurityService::logSession('employee', (int)$employee['id'], (int)$biz['id'], 'login');

        // Registrar último login
        $this->pdo->prepare(
            "UPDATE employees SET last_login = NOW() WHERE id = ?"
        )->execute([$employee['id']]);
    }

    /**
     * Registra el cierre de sesión de un empleado en session_logs.
     *
     * @return void
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId     = (int)($_SESSION['user_id']    ?? 0);
        $businessId = (int)($_SESSION['business_id'] ?? 0);

        if ($userId > 0) {
            SecurityService::logSession('employee', $userId, $businessId ?: null, 'logout');
        }
    }

    /**
     * Devuelve la lista de empresas activas (para el select del login).
     *
     * @return array
     */
    public function listarEmpresasActivas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, name, slug FROM businesses WHERE is_active = 1 ORDER BY name"
        );
        return $stmt->fetchAll();
    }
}
