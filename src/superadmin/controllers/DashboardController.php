<?php
/**
 * Controlador del dashboard global de superadmin.
 *
 * Incluye KPIs con variación mensual y datos para gráficos Chart.js.
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.1.0
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
     * Devuelve todas las métricas y datos para el dashboard.
     *
     * @return array
     */
    public function index(): array
    {
        return [
            'kpis'              => $this->buildKpis(),
            'negociosPorMes'    => $this->negociosPorMes12(),
            'topMovimientos'    => $this->topMovimientos30Dias(),
            'planesDistrib'     => $this->distribucionPlanes(),
            'mensajesPorSemana' => $this->mensajesPorSemana8(),
            'resumenNegocios'   => $this->resumenNegocios(),
            'ultimosMensajes'   => $this->ultimosMensajesSinLeer(),
            'ultimaActividad'   => $this->ultimaActividad(),
            // backward compat para sidebar badge
            'mensajesSinLeer'   => $this->contarMensajesSinLeer(),
        ];
    }

    /**
     * Devuelve los últimos 15 eventos de actividad como array listo para JSON.
     *
     * @return array
     */
    public function actividadReciente(): array
    {
        $stmt = $this->pdo->query(
            "SELECT al.id, al.action, al.created_at, al.entity, al.is_critical,
                    b.name AS negocio, e.name AS empleado
             FROM activity_logs al
             LEFT JOIN businesses b ON b.id = al.business_id
             LEFT JOIN employees  e ON e.id = al.employee_id
             ORDER BY al.created_at DESC
             LIMIT 15"
        );
        return $stmt->fetchAll();
    }

    // ── KPIs con variación ────────────────────────────────────────────────────

    private function buildKpis(): array
    {
        $negActual  = $this->contarNegociosRegistradosMes(0);
        $negPrev    = $this->contarNegociosRegistradosMes(1);
        $empActual  = $this->contarEmpleadosCreadosMes(0);
        $empPrev    = $this->contarEmpleadosCreadosMes(1);
        $movActual  = $this->contarMovimientosMes(0);
        $movPrev    = $this->contarMovimientosMes(1);
        $msgActual  = $this->contarMensajesMes(0);
        $msgPrev    = $this->contarMensajesMes(1);

        return [
            'negocios' => [
                'value'     => $this->contarNegociosActivos(),
                'mes'       => $negActual,
                'variation' => $this->variacion($negActual, $negPrev),
            ],
            'empleados' => [
                'value'     => $this->contarEmpleadosActivos(),
                'mes'       => $empActual,
                'variation' => $this->variacion($empActual, $empPrev),
            ],
            'movimientos' => [
                'value'     => $movActual,
                'variation' => $this->variacion($movActual, $movPrev),
            ],
            'mensajes' => [
                'value'     => $this->contarMensajesSinLeer(),
                'mes'       => $msgActual,
                'variation' => $this->variacion($msgActual, $msgPrev),
            ],
        ];
    }

    /**
     * Calcula la variación porcentual entre el valor actual y el anterior.
     * Devuelve ['pct' => float, 'dir' => 'up'|'down'|'same'].
     */
    private function variacion(int $actual, int $anterior): array
    {
        if ($anterior === 0) {
            return ['pct' => $actual > 0 ? 100.0 : 0.0, 'dir' => $actual > 0 ? 'up' : 'same'];
        }
        $pct = round((($actual - $anterior) / $anterior) * 100, 1);
        return [
            'pct' => abs($pct),
            'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'same'),
        ];
    }

    // ── Contadores simples ────────────────────────────────────────────────────

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

    /**
     * Negocios registrados en el mes N meses atrás (0 = este mes, 1 = mes anterior).
     */
    private function contarNegociosRegistradosMes(int $mesesAtras): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM businesses
             WHERE YEAR(created_at)  = YEAR(DATE_SUB(NOW(),  INTERVAL ? MONTH))
               AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL ? MONTH))"
        );
        $stmt->execute([$mesesAtras, $mesesAtras]);
        return (int)$stmt->fetchColumn();
    }

    private function contarEmpleadosCreadosMes(int $mesesAtras): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM employees
             WHERE YEAR(created_at)  = YEAR(DATE_SUB(NOW(),  INTERVAL ? MONTH))
               AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL ? MONTH))"
        );
        $stmt->execute([$mesesAtras, $mesesAtras]);
        return (int)$stmt->fetchColumn();
    }

    private function contarMovimientosMes(int $mesesAtras): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM movimientos
             WHERE YEAR(fecha)  = YEAR(DATE_SUB(NOW(),  INTERVAL ? MONTH))
               AND MONTH(fecha) = MONTH(DATE_SUB(NOW(), INTERVAL ? MONTH))"
        );
        $stmt->execute([$mesesAtras, $mesesAtras]);
        return (int)$stmt->fetchColumn();
    }

    private function contarMensajesMes(int $mesesAtras): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM contact_messages
             WHERE YEAR(created_at)  = YEAR(DATE_SUB(NOW(),  INTERVAL ? MONTH))
               AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL ? MONTH))"
        );
        $stmt->execute([$mesesAtras, $mesesAtras]);
        return (int)$stmt->fetchColumn();
    }

    // ── Datos para gráficos ───────────────────────────────────────────────────

    /**
     * Negocios registrados por mes en los últimos 12 meses.
     */
    private function negociosPorMes12(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS mes, COUNT(*) AS total
             FROM businesses
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY mes ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Top 5 empresas por movimientos en los últimos 30 días.
     */
    private function topMovimientos30Dias(): array
    {
        $stmt = $this->pdo->query(
            "SELECT b.name AS negocio, COUNT(m.id) AS total
             FROM businesses b
             LEFT JOIN movimientos m ON m.business_id = b.id
                 AND m.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             WHERE b.is_active = 1
             GROUP BY b.id, b.name
             ORDER BY total DESC
             LIMIT 5"
        );
        return $stmt->fetchAll();
    }

    /**
     * Distribución de planes en negocios activos.
     */
    private function distribucionPlanes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT plan, COUNT(*) AS total
             FROM businesses
             WHERE is_active = 1
             GROUP BY plan"
        );
        return $stmt->fetchAll();
    }

    /**
     * Mensajes de contacto recibidos por semana en las últimas 8 semanas.
     */
    private function mensajesPorSemana8(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DATE_FORMAT(MIN(created_at), '%d/%m') AS semana_label,
                    YEARWEEK(created_at, 1) AS semana_key,
                    COUNT(*) AS total
             FROM contact_messages
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
             GROUP BY YEARWEEK(created_at, 1)
             ORDER BY semana_key ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Resumen de negocios con días hasta vencimiento y movimientos del mes.
     */
    private function resumenNegocios(): array
    {
        $stmt = $this->pdo->query(
            "SELECT b.id, b.name, b.plan, b.is_active, b.plan_expires_at,
                    DATEDIFF(b.plan_expires_at, NOW()) AS dias_vencimiento,
                    COUNT(DISTINCT e.id)  AS empleados_activos,
                    COUNT(DISTINCT CASE
                        WHEN m.fecha >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN m.id
                    END) AS movimientos_mes
             FROM businesses b
             LEFT JOIN employees e  ON e.business_id = b.id AND e.is_active = 1
             LEFT JOIN movimientos m ON m.business_id = b.id
             GROUP BY b.id
             ORDER BY b.created_at DESC
             LIMIT 15"
        );
        return $stmt->fetchAll();
    }

    // ── Listados para tablas del dashboard ───────────────────────────────────

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
            "SELECT al.action, al.created_at, al.ip_address, al.is_critical,
                    b.name AS negocio, e.name AS empleado
             FROM activity_logs al
             LEFT JOIN businesses b ON b.id = al.business_id
             LEFT JOIN employees  e ON e.id = al.employee_id
             ORDER BY al.created_at DESC
             LIMIT 15"
        );
        return $stmt->fetchAll();
    }
}
