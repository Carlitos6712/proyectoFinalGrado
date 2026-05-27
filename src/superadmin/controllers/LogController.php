<?php
/**
 * Controlador de registros de actividad (activity_logs).
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../includes/Database.php';

class LogController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Listado paginado con filtros por negocio, empleado y rango de fechas.
     *
     * @param array $filters business_id, empleado, fecha_desde, fecha_hasta.
     * @param int   $page
     * @return array{items: array, total: int, pages: int, page: int}
     */
    public function index(array $filters = [], int $page = 1): array
    {
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;
        $where   = [];
        $params  = [];

        if (!empty($filters['business_id'])) {
            $where[]  = 'al.business_id = ?';
            $params[] = (int)$filters['business_id'];
        }
        if (!empty($filters['empleado'])) {
            $where[]  = 'e.name LIKE ?';
            $params[] = '%' . $filters['empleado'] . '%';
        }
        if (!empty($filters['fecha_desde'])) {
            $where[]  = 'al.created_at >= ?';
            $params[] = $filters['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[]  = 'al.created_at <= ?';
            $params[] = $filters['fecha_hasta'] . ' 23:59:59';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $cntStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM activity_logs al
             LEFT JOIN businesses b ON b.id = al.business_id
             LEFT JOIN employees  e ON e.id = al.employee_id
             {$whereClause}"
        );
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT al.*, b.name AS negocio, e.name AS empleado
             FROM activity_logs al
             LEFT JOIN businesses b ON b.id = al.business_id
             LEFT JOIN employees  e ON e.id = al.employee_id
             {$whereClause}
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'pages' => (int)ceil($total / $perPage),
            'page'  => $page,
        ];
    }

    /** @return array Lista de negocios para el select de filtro. */
    public function negocios(): array
    {
        return $this->pdo->query(
            'SELECT id, name FROM businesses ORDER BY name'
        )->fetchAll();
    }
}
