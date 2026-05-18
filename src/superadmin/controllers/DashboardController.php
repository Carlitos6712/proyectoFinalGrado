<?php
/**
 * Controlador del dashboard global de superadmin.
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../includes/Database.php';

class DashboardController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Devuelve todas las métricas para el dashboard.
     *
     * @return array{
     *   totalNegocios: int,
     *   totalEmpleados: int,
     *   mensajesSinLeer: int,
     *   movimientosHoy: int,
     *   movimientosPorNegocio: array,
     *   ultimosMensajes: array,
     *   ultimaActividad: array
     * }
     */
    public function index(): array
    {
        return [
            'totalNegocios'          => $this->contarNegociosActivos(),
            'totalEmpleados'         => $this->contarEmpleadosActivos(),
            'mensajesSinLeer'        => $this->contarMensajesSinLeer(),
            'movimientosHoy'         => $this->contarMovimientosHoy(),
            'movimientosPorNegocio'  => $this->movimientosUltimos7Dias(),
            'ultimosMensajes'        => $this->ultimosMensajesSinLeer(),
            'ultimaActividad'        => $this->ultimaActividad(),
        ];
    }

    private function contarNegociosActivos(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM businesses WHERE is_active = 1')->fetchColumn();
    }

    private function contarEmpleadosActivos(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM employees WHERE is_active = 1')->fetchColumn();
    }

    private function contarMensajesSinLeer(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
    }

    private function contarMovimientosHoy(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM movimientos WHERE DATE(fecha) = CURDATE()');
        return (int)$stmt->fetchColumn();
    }

    private function movimientosUltimos7Dias(): array
    {
        $stmt = $this->pdo->query(
            "SELECT b.name AS negocio, COUNT(m.id) AS total
             FROM businesses b
             LEFT JOIN movimientos m ON m.business_id = b.id
                 AND m.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             WHERE b.is_active = 1
             GROUP BY b.id, b.name
             ORDER BY total DESC
             LIMIT 10"
        );
        return $stmt->fetchAll();
    }

    private function ultimosMensajesSinLeer(): array
    {
        $stmt = $this->pdo->query(
            "SELECT cm.id, cm.sender_name, cm.sender_email, cm.subject,
                    cm.created_at, b.name AS negocio
             FROM contact_messages cm
             LEFT JOIN businesses b ON b.id = cm.business_id
             WHERE cm.is_read = 0
             ORDER BY cm.created_at DESC
             LIMIT 5"
        );
        return $stmt->fetchAll();
    }

    private function ultimaActividad(): array
    {
        $stmt = $this->pdo->query(
            "SELECT al.action, al.created_at, al.ip_address,
                    b.name AS negocio, e.name AS empleado
             FROM activity_logs al
             LEFT JOIN businesses b ON b.id = al.business_id
             LEFT JOIN employees  e ON e.id = al.employee_id
             ORDER BY al.created_at DESC
             LIMIT 5"
        );
        return $stmt->fetchAll();
    }
}
