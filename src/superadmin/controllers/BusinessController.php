<?php
/**
 * Controlador CRUD de negocios (businesses).
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.1.0
 */

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../core/Mailer.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/BusinessSeeder.php';
require_once __DIR__ . '/../../core/ActivityLogger.php';
require_once __DIR__ . '/../../core/NotificationService.php';

class BusinessController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Listado paginado de negocios con búsqueda por nombre.
     *
     * @param int    $page  Página actual (desde 1).
     * @param string $q     Término de búsqueda.
     * @return array{items: array, total: int, pages: int, page: int}
     */
    public function index(int $page = 1, string $q = ''): array
    {
        $perPage = 10;
        $offset  = ($page - 1) * $perPage;
        $search  = '%' . $q . '%';

        $cnt = $this->pdo->prepare('SELECT COUNT(*) FROM businesses WHERE name LIKE ?');
        $cnt->execute([$search]);
        $total = (int)$cnt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT b.*, COUNT(e.id) AS num_empleados
             FROM businesses b
             LEFT JOIN employees e ON e.business_id = b.id
             WHERE b.name LIKE ?
             GROUP BY b.id
             ORDER BY b.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$search, $perPage, $offset]);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'pages' => (int)ceil($total / $perPage),
            'page'  => $page,
        ];
    }

    /**
     * Devuelve un negocio por ID con sus empleados y métricas.
     *
     * @param int $id
     * @throws AppException Si no existe.
     * @return array
     */
    public function show(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM businesses WHERE id = ?');
        $stmt->execute([$id]);
        $business = $stmt->fetch();
        if (!$business) {
            throw new AppException('Negocio no encontrado.', 404);
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM employees WHERE business_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$id]);
        $business['employees'] = $stmt->fetchAll();

        $stmt = $this->pdo->prepare(
            "SELECT m.*, p.nombre AS producto
             FROM movimientos m
             LEFT JOIN productos p ON p.id = m.producto_id
             WHERE m.business_id = ?
             ORDER BY m.fecha DESC LIMIT 10"
        );
        $stmt->execute([$id]);
        $business['movimientos'] = $stmt->fetchAll();

        $cntProd = $this->pdo->prepare('SELECT COUNT(*) FROM productos WHERE business_id = ? AND deleted_at IS NULL');
        $cntProd->execute([$id]);
        $business['total_productos'] = (int)$cntProd->fetchColumn();

        $cntMov = $this->pdo->prepare(
            "SELECT COUNT(*) FROM movimientos WHERE business_id = ? AND MONTH(fecha) = MONTH(NOW())"
        );
        $cntMov->execute([$id]);
        $business['movimientos_mes'] = (int)$cntMov->fetchColumn();

        return $business;
    }

    /**
     * Crea un nuevo negocio y genera su empleado admin inicial.
     *
     * @param array $data Campos del formulario.
     * @throws AppException Si validación falla.
     * @return int ID del negocio creado.
     */
    public function store(array $data): int
    {
        $name  = trim($data['name'] ?? '');
        $email = trim($data['contact_email'] ?? '');
        if (!$name) {
            throw new AppException('El nombre del negocio es obligatorio.', 422);
        }

        $slug = $this->generarSlug($name);

        $stmt = $this->pdo->prepare(
            'INSERT INTO businesses (name, slug, contact_email, phone, address, plan, plan_expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $slug,
            $email ?: null,
            trim($data['phone'] ?? '') ?: null,
            trim($data['address'] ?? '') ?: null,
            $data['plan'] ?? 'free',
            ($data['plan_expires_at'] ?? '') ?: null,
        ]);
        $businessId = (int)$this->pdo->lastInsertId();

        $plainPassword = $this->generarPassword();
        $this->crearEmpleadoAdmin($businessId, $name, $email, $plainPassword);

        BusinessSeeder::seed($businessId);

        if ($email) {
            $this->enviarBienvenida($email, $name, $email, $plainPassword);
        }

        // Notificar nuevo negocio al superadmin
        NotificationService::notify(
            'new_business',
            'Nuevo negocio registrado: ' . $name,
            "Se ha creado el negocio \"{$name}\" con plan " . ($data['plan'] ?? 'free') . '.',
            '/superadmin/businesses.php?action=show&id=' . $businessId
        );

        return $businessId;
    }

    /**
     * Actualiza los datos de un negocio.
     * Si cambia el plan, registra acción crítica en activity_logs.
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

        // Capturar estado anterior para auditoría
        $antes = $this->pdo->prepare('SELECT name, plan, plan_expires_at FROM businesses WHERE id = ?');
        $antes->execute([$id]);
        $anterior = $antes->fetch();

        $stmt = $this->pdo->prepare(
            'UPDATE businesses SET name=?, contact_email=?, phone=?, address=?, plan=?, plan_expires_at=?
             WHERE id=?'
        );
        $stmt->execute([
            $name,
            trim($data['contact_email'] ?? '') ?: null,
            trim($data['phone'] ?? '') ?: null,
            trim($data['address'] ?? '') ?: null,
            $data['plan'] ?? 'free',
            ($data['plan_expires_at'] ?? '') ?: null,
            $id,
        ]);

        // Log crítico si cambia el plan
        if ($anterior && $anterior['plan'] !== ($data['plan'] ?? 'free')) {
            $saId = (int)($_SESSION['usuario_id'] ?? 0);
            ActivityLogger::log(
                $id,
                $saId,
                'Cambió el plan de ' . $anterior['plan'] . ' a ' . ($data['plan'] ?? 'free'),
                'business',
                $id,
                [
                    'antes'   => ['plan' => $anterior['plan'], 'plan_expires_at' => $anterior['plan_expires_at']],
                    'despues' => ['plan' => $data['plan'] ?? 'free', 'plan_expires_at' => $data['plan_expires_at'] ?? null],
                ],
                true
            );
        }
    }

    /**
     * Activa o desactiva un negocio.
     * Desactivar registra acción crítica y crea notificación.
     *
     * @param int $id
     * @return bool Nuevo estado is_active.
     */
    public function toggle(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT is_active, name FROM businesses WHERE id = ?');
        $stmt->execute([$id]);
        $row     = $stmt->fetch();
        $current = (int)($row['is_active'] ?? 1);
        $nuevo   = $current === 1 ? 0 : 1;

        $this->pdo->prepare('UPDATE businesses SET is_active = ? WHERE id = ?')->execute([$nuevo, $id]);

        if ($nuevo === 0) {
            $saId = (int)($_SESSION['usuario_id'] ?? 0);
            ActivityLogger::log(
                $id,
                $saId,
                'Desactivó el negocio "' . ($row['name'] ?? '') . '"',
                'business',
                $id,
                ['antes' => ['is_active' => 1], 'despues' => ['is_active' => 0]],
                true
            );
            NotificationService::notify(
                'business_deactivated',
                'Negocio desactivado: ' . ($row['name'] ?? ''),
                'El negocio "' . ($row['name'] ?? '') . '" ha sido desactivado por el superadmin.',
                '/superadmin/businesses.php?action=show&id=' . $id
            );
        }

        return (bool)$nuevo;
    }

    /**
     * Inicia una sesión impersonada como administrador de la empresa.
     * Registra acción crítica en activity_logs.
     *
     * @param int $id ID del negocio.
     * @throws AppException Si el negocio no existe o no tiene empleado admin.
     * @return void — redirige a /index.php
     */
    public function impersonate(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM businesses WHERE id = ?');
        $stmt->execute([$id]);
        $business = $stmt->fetch();
        if (!$business) {
            throw new AppException('Negocio no encontrado.', 404);
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM employees WHERE business_id = ? AND role = 'admin' AND is_active = 1
             ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute([$id]);
        $employee = $stmt->fetch();
        if (!$employee) {
            throw new AppException('Este negocio no tiene empleados admin activos.', 409);
        }

        $saId = (int)($_SESSION['usuario_id'] ?? 0);
        ActivityLogger::log(
            $id,
            $saId,
            'Acceso impersonado al negocio "' . $business['name'] . '"',
            'business',
            $id,
            ['superadmin_id' => $saId, 'employee_id' => (int)$employee['id']],
            true
        );

        $saReturn = [
            'usuario_id'       => $_SESSION['usuario_id']       ?? null,
            'usuario_nombre'   => $_SESSION['usuario_nombre']   ?? '',
            'usuario_username' => $_SESSION['usuario_username'] ?? '',
            'rol'              => $_SESSION['rol']              ?? 'superadmin',
        ];

        Session::setBusinessUser($employee, $business);

        $_SESSION['superadmin_impersonating']   = true;
        $_SESSION['superadmin_return_uid']      = $saReturn['usuario_id'];
        $_SESSION['superadmin_return_nombre']   = $saReturn['usuario_nombre'];
        $_SESSION['superadmin_return_username'] = $saReturn['usuario_username'];

        header('Location: /index.php');
        exit;
    }

    private function generarSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $base = $slug;
        $i    = 1;
        while (true) {
            $check = $this->pdo->prepare('SELECT COUNT(*) FROM businesses WHERE slug = ?');
            $check->execute([$slug]);
            if (!(int)$check->fetchColumn()) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
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

    private function crearEmpleadoAdmin(int $businessId, string $businessName, string $email, string $plainPwd): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO employees (business_id, name, email, password, role)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $businessId,
            'Admin ' . $businessName,
            $email,
            password_hash($plainPwd, PASSWORD_BCRYPT, ['cost' => 12]),
            'admin',
        ]);
    }

    private function enviarBienvenida(string $to, string $business, string $username, string $pwd): void
    {
        $subject = "Bienvenido a es21plus – Acceso a tu panel";
        $body    = "<h2>Bienvenido, {$business}</h2>
                    <p>Tu panel de inventario está listo. Accede con:</p>
                    <ul><li><strong>Usuario:</strong> {$username}</li>
                        <li><strong>Contraseña:</strong> {$pwd}</li></ul>
                    <p>Cambia tu contraseña al primer acceso.</p>
                    <p><em>es21plus</em></p>";
        try {
            Mailer::send($to, $subject, $body);
        } catch (\Throwable) {
            // Correo no bloquea el alta del negocio
        }
    }
}
