<?php
/**
 * Controlador CRUD de empleados (employees).
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../core/Mailer.php';

class EmployeeController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Listado global de empleados, filtrable por negocio.
     *
     * @param int $businessId 0 = todos.
     * @return array
     */
    public function index(int $businessId = 0): array
    {
        if ($businessId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT e.*, b.name AS negocio
                 FROM employees e
                 JOIN businesses b ON b.id = e.business_id
                 WHERE e.business_id = ?
                 ORDER BY e.created_at DESC'
            );
            $stmt->execute([$businessId]);
        } else {
            $stmt = $this->pdo->query(
                'SELECT e.*, b.name AS negocio
                 FROM employees e
                 JOIN businesses b ON b.id = e.business_id
                 ORDER BY b.name, e.name'
            );
        }
        return $stmt->fetchAll();
    }

    /**
     * Devuelve un empleado por ID.
     *
     * @param int $id
     * @throws AppException
     * @return array
     */
    public function find(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, b.name AS negocio
             FROM employees e
             JOIN businesses b ON b.id = e.business_id
             WHERE e.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new AppException('Empleado no encontrado.', 404);
        }
        return $row;
    }

    /**
     * Crea un empleado nuevo.
     *
     * @param array $data
     * @throws AppException
     * @return int
     */
    public function store(array $data): int
    {
        $name       = trim($data['name'] ?? '');
        $email      = trim($data['email'] ?? '');
        $businessId = (int)($data['business_id'] ?? 0);

        if (!$name || !$email || !$businessId) {
            throw new AppException('Nombre, email y negocio son obligatorios.', 422);
        }

        $plain = $this->generarPassword();
        $stmt  = $this->pdo->prepare(
            'INSERT INTO employees (business_id, name, email, password, role)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $businessId,
            $name,
            $email,
            password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]),
            $data['role'] ?? 'employee',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Actualiza datos de un empleado.
     *
     * @param int   $id
     * @param array $data
     * @throws AppException
     * @return void
     */
    public function update(int $id, array $data): void
    {
        $name = trim($data['name'] ?? '');
        if (!$name) {
            throw new AppException('El nombre es obligatorio.', 422);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE employees SET name=?, email=?, role=?, business_id=? WHERE id=?'
        );
        $stmt->execute([
            $name,
            trim($data['email'] ?? ''),
            $data['role'] ?? 'employee',
            (int)($data['business_id'] ?? 0),
            $id,
        ]);
    }

    /**
     * Activa o desactiva un empleado.
     *
     * @param int $id
     * @return bool Nuevo estado.
     */
    public function toggle(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT is_active FROM employees WHERE id = ?');
        $stmt->execute([$id]);
        $current = (int)$stmt->fetchColumn();
        $nuevo   = $current === 1 ? 0 : 1;

        $this->pdo->prepare('UPDATE employees SET is_active = ? WHERE id = ?')->execute([$nuevo, $id]);
        return (bool)$nuevo;
    }

    /**
     * Genera nueva contraseña aleatoria, la guarda y envía por correo.
     * Nunca devuelve la contraseña en texto plano.
     *
     * @param int $id
     * @throws AppException
     * @return void
     */
    public function resetPassword(int $id): void
    {
        $emp = $this->find($id);
        $plain = $this->generarPassword(10);

        $this->pdo->prepare('UPDATE employees SET password = ? WHERE id = ?')
            ->execute([password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]), $id]);

        $body = "<h3>Nueva contraseña</h3>
                 <p>Hola {$emp['name']}, tu contraseña ha sido restablecida.</p>
                 <p><strong>Nueva contraseña:</strong> {$plain}</p>
                 <p>Cámbiala al iniciar sesión.</p>";
        try {
            Mailer::send($emp['email'], 'Contraseña restablecida – es21plus', $body);
        } catch (\Throwable) {
            // No bloquear si el correo falla
        }
    }

    /** @return array Lista de negocios activos para selects. */
    public function negociosActivos(): array
    {
        return $this->pdo->query(
            'SELECT id, name FROM businesses WHERE is_active = 1 ORDER BY name'
        )->fetchAll();
    }

    private function generarPassword(int $len = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$';
        $pwd   = '';
        for ($i = 0; $i < $len; $i++) {
            $pwd .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pwd;
    }
}
