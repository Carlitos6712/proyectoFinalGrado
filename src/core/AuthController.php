<?php
/**
 * Controlador de autenticación para empleados de empresa (multi-tenant).
 *
 * Gestiona el login con empresa + email + contraseña usando la tabla employees.
 * El login de superadmin usa /superadmin/login de forma independiente.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/Session.php';

class AuthController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Autentica un empleado de empresa.
     *
     * @param string $business  Nombre o slug del negocio.
     * @param string $email     Email del empleado.
     * @param string $password  Contraseña en texto plano.
     * @throws AppException En caso de credenciales incorrectas o empresa inactiva.
     * @return void — redirige a /index.php en éxito.
     */
    public function login(string $business, string $email, string $password): void
    {
        $business = trim($business);
        $email    = trim($email);

        if ($business === '' || $email === '' || $password === '') {
            throw new AppException('Empresa, email y contraseña son obligatorios.', 400);
        }

        // 1. Buscar empresa activa
        $stmt = $this->pdo->prepare(
            "SELECT * FROM businesses WHERE (name = ? OR slug = ?) AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$business, $business]);
        $biz = $stmt->fetch();
        if (!$biz) {
            throw new AppException('Empresa no encontrada o inactiva.', 401);
        }

        // 2. Buscar empleado activo en esa empresa
        $stmt = $this->pdo->prepare(
            "SELECT * FROM employees WHERE email = ? AND business_id = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$email, $biz['id']]);
        $employee = $stmt->fetch();
        if (!$employee) {
            throw new AppException('Credenciales incorrectas.', 401);
        }

        // 3. Verificar contraseña
        if (!password_verify($password, $employee['password'])) {
            throw new AppException('Credenciales incorrectas.', 401);
        }

        // 4. Crear sesión multi-tenant
        Session::setBusinessUser($employee, $biz);

        // Registrar último login
        $this->pdo->prepare(
            "UPDATE employees SET last_login = NOW() WHERE id = ?"
        )->execute([$employee['id']]);
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
