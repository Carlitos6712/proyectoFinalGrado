<?php
/**
 * Controlador de reportes para el superadmin.
 *
 * Genera datos para los 4 tipos de informe:
 *   - businesses: listado de negocios con estado/plan
 *   - billing:    facturas filtradas por estado y período
 *   - activity:   últimos N registros de actividad
 *   - tickets:    tickets por estado / negocio
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';

class ReportsController
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ──────────────────────────────────────────
    //  Reporte: negocios
    // ──────────────────────────────────────────

    /**
     * Devuelve datos de negocios para exportación.
     *
     * @param array<string,mixed> $filters  Keys: status (active|inactive), plan
     * @return array{headers: array, rows: array}
     */
    public function businesses(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[]  = 'b.is_active = ?';
            $params[] = $filters['status'] === 'active' ? 1 : 0;
        }
        if (!empty($filters['plan'])) {
            $where[]  = 'b.plan = ?';
            $params[] = $filters['plan'];
        }

        $sql = implode(' AND ', $where);
        $stmt = $this->pdo->prepare(
            "SELECT b.id, b.name, b.email, b.plan,
                    b.plan_expires_at, b.is_active,
                    COUNT(e.id) AS total_empleados,
                    b.created_at
             FROM businesses b
             LEFT JOIN employees e ON e.business_id = b.id AND e.is_active = 1
             WHERE {$sql}
             GROUP BY b.id
             ORDER BY b.created_at DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $headers = ['ID', 'Nombre', 'Email', 'Plan', 'Vence', 'Activo', 'Empleados', 'Registrado'];
        $data    = array_map(fn($r) => [
            $r['id'],
            $r['name'],
            $r['email'],
            ucfirst($r['plan']),
            $r['plan_expires_at'] ? date('d/m/Y', strtotime($r['plan_expires_at'])) : '—',
            $r['is_active'] ? 'Sí' : 'No',
            $r['total_empleados'],
            date('d/m/Y', strtotime($r['created_at'])),
        ], $rows);

        return ['headers' => $headers, 'rows' => $data];
    }

    // ──────────────────────────────────────────
    //  Reporte: facturación
    // ──────────────────────────────────────────

    /**
     * Devuelve datos de facturas para exportación.
     *
     * @param array<string,mixed> $filters  Keys: status, fecha_desde, fecha_hasta
     * @return array{headers: array, rows: array}
     */
    public function billing(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['fecha_desde'])) {
            $where[]  = 'i.created_at >= ?';
            $params[] = $filters['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[]  = 'i.created_at <= ?';
            $params[] = $filters['fecha_hasta'] . ' 23:59:59';
        }

        $sql = implode(' AND ', $where);
        $stmt = $this->pdo->prepare(
            "SELECT i.invoice_number, b.name AS business_name, i.amount,
                    i.status, i.period_start, i.period_end, i.paid_at, i.created_at
             FROM invoices i
             LEFT JOIN businesses b ON b.id = i.business_id
             WHERE {$sql}
             ORDER BY i.created_at DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $statusMap = ['pending' => 'Pendiente', 'paid' => 'Pagada', 'cancelled' => 'Cancelada'];
        $headers   = ['N.° Factura', 'Negocio', 'Importe (€)', 'Estado', 'Periodo inicio', 'Periodo fin', 'Pagado el', 'Emitida'];
        $data      = array_map(fn($r) => [
            $r['invoice_number'],
            $r['business_name'],
            number_format((float)$r['amount'], 2, ',', '.'),
            $statusMap[$r['status']] ?? $r['status'],
            $r['period_start'] ? date('d/m/Y', strtotime($r['period_start'])) : '—',
            $r['period_end']   ? date('d/m/Y', strtotime($r['period_end']))   : '—',
            $r['paid_at']      ? date('d/m/Y H:i', strtotime($r['paid_at']))  : '—',
            date('d/m/Y', strtotime($r['created_at'])),
        ], $rows);

        return ['headers' => $headers, 'rows' => $data];
    }

    // ──────────────────────────────────────────
    //  Reporte: actividad
    // ──────────────────────────────────────────

    /**
     * Devuelve registros de actividad para exportación.
     *
     * @param array<string,mixed> $filters  Keys: fecha_desde, fecha_hasta, business_id
     * @return array{headers: array, rows: array}
     */
    public function activity(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['fecha_desde'])) {
            $where[]  = 'al.created_at >= ?';
            $params[] = $filters['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[]  = 'al.created_at <= ?';
            $params[] = $filters['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filters['business_id'])) {
            $where[]  = 'al.business_id = ?';
            $params[] = (int)$filters['business_id'];
        }

        $sql = implode(' AND ', $where);
        $stmt = $this->pdo->prepare(
            "SELECT al.created_at, b.name AS negocio, e.name AS empleado,
                    al.action, al.entity, al.ip_address
             FROM activity_logs al
             LEFT JOIN businesses b ON b.id = al.business_id
             LEFT JOIN employees  e ON e.id = al.employee_id
             WHERE {$sql}
             ORDER BY al.created_at DESC
             LIMIT 5000"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $headers = ['Fecha', 'Negocio', 'Empleado', 'Acción', 'Entidad', 'IP'];
        $data    = array_map(fn($r) => [
            date('d/m/Y H:i', strtotime($r['created_at'])),
            $r['negocio']  ?? '—',
            $r['empleado'] ?? 'superadmin',
            $r['action'],
            $r['entity']     ?? '—',
            $r['ip_address'] ?? '—',
        ], $rows);

        return ['headers' => $headers, 'rows' => $data];
    }

    // ──────────────────────────────────────────
    //  Reporte: tickets
    // ──────────────────────────────────────────

    /**
     * Devuelve tickets para exportación.
     *
     * @param array<string,mixed> $filters  Keys: status, priority, fecha_desde, fecha_hasta
     * @return array{headers: array, rows: array}
     */
    public function tickets(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'st.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[]  = 'st.priority = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['fecha_desde'])) {
            $where[]  = 'st.created_at >= ?';
            $params[] = $filters['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[]  = 'st.created_at <= ?';
            $params[] = $filters['fecha_hasta'] . ' 23:59:59';
        }

        $sql = implode(' AND ', $where);
        $stmt = $this->pdo->prepare(
            "SELECT st.ticket_number, b.name AS negocio, st.subject,
                    st.status, st.priority,
                    (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = st.id) AS mensajes,
                    st.created_at, st.closed_at
             FROM support_tickets st
             LEFT JOIN businesses b ON b.id = st.business_id
             WHERE {$sql}
             ORDER BY st.created_at DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $statusMap   = ['open'=>'Abierto','in_progress'=>'En proceso','resolved'=>'Resuelto','closed'=>'Cerrado'];
        $priorityMap = ['low'=>'Baja','normal'=>'Normal','high'=>'Alta','urgent'=>'Urgente'];

        $headers = ['Ticket', 'Negocio', 'Asunto', 'Estado', 'Prioridad', 'Mensajes', 'Creado', 'Cerrado'];
        $data    = array_map(fn($r) => [
            $r['ticket_number'],
            $r['negocio'] ?? '—',
            $r['subject'],
            $statusMap[$r['status']]     ?? $r['status'],
            $priorityMap[$r['priority']] ?? $r['priority'],
            $r['mensajes'],
            date('d/m/Y H:i', strtotime($r['created_at'])),
            $r['closed_at'] ? date('d/m/Y', strtotime($r['closed_at'])) : '—',
        ], $rows);

        return ['headers' => $headers, 'rows' => $data];
    }
}
