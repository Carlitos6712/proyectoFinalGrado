<?php
/**
 * Controlador de seguridad para el panel de superadmin.
 *
 * Gestiona IPs bloqueadas, logs de sesión y acciones críticas.
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../core/SecurityService.php';

class SecurityController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Devuelve datos para la vista de seguridad.
     *
     * @param string|null $userTypeFilter  Filtro tipo de usuario para session_logs.
     * @param string|null $fechaDesde      Filtro fecha inicio para session_logs.
     * @return array
     */
    public function index(?string $userTypeFilter = null, ?string $fechaDesde = null): array
    {
        return [
            'blockedIps'      => SecurityService::getBlockedIps(),
            'sessionLogs'     => SecurityService::getSessionLogs(50, $userTypeFilter, $fechaDesde),
            'criticalActions' => $this->getCriticalActions(),
        ];
    }

    /**
     * Desbloquea una IP eliminando sus intentos de login.
     *
     * @param string $ip
     * @return void
     */
    public function unblockIp(string $ip): void
    {
        SecurityService::clearAttempts($ip);
    }

    /**
     * Devuelve las últimas 50 acciones críticas de activity_logs.
     *
     * @return array
     */
    private function getCriticalActions(): array
    {
        $stmt = $this->pdo->query(
            "SELECT al.*, b.name AS negocio, e.name AS empleado
             FROM activity_logs al
             LEFT JOIN businesses b ON b.id = al.business_id
             LEFT JOIN employees  e ON e.id = al.employee_id
             WHERE al.is_critical = 1
             ORDER BY al.created_at DESC
             LIMIT 50"
        );
        return $stmt->fetchAll();
    }
}
