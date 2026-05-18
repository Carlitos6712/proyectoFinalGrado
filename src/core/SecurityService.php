<?php
/**
 * Servicio de seguridad: protección fuerza bruta y registro de sesiones.
 *
 * Métodos estáticos sin dependencias de constructor para usarse
 * en cualquier punto del flujo de autenticación.
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';

class SecurityService
{
    private const MAX_ATTEMPTS    = 5;
    private const WINDOW_MINUTES  = 15;

    /**
     * Verifica si una IP está bloqueada por exceso de intentos fallidos.
     *
     * @param string $ip
     * @return bool
     */
    public static function isIpBlocked(string $ip): bool
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM login_attempts
                 WHERE ip_address = ?
                   AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
            );
            $stmt->execute([$ip, self::WINDOW_MINUTES]);
            return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Registra un intento de login fallido para la IP dada.
     *
     * @param string $ip
     * @param string $email
     * @return void
     */
    public static function registerFailedAttempt(string $ip, string $email): void
    {
        try {
            $pdo = Database::getInstance();
            $pdo->prepare(
                'INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)'
            )->execute([$ip, $email]);
        } catch (\Throwable) {
            // No interrumpir flujo principal
        }
    }

    /**
     * Elimina todos los intentos de login registrados para una IP.
     *
     * @param string $ip
     * @return void
     */
    public static function clearAttempts(string $ip): void
    {
        try {
            $pdo = Database::getInstance();
            $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$ip]);
        } catch (\Throwable) {
            // No interrumpir flujo principal
        }
    }

    /**
     * Registra un evento de sesión (login / logout / expired).
     *
     * @param string   $userType   'superadmin' | 'employee'
     * @param int      $userId     ID del usuario o empleado.
     * @param int|null $businessId ID del negocio (null para superadmin).
     * @param string   $action     'login' | 'logout' | 'expired'
     * @return void
     */
    public static function logSession(
        string $userType,
        int    $userId,
        ?int   $businessId,
        string $action
    ): void {
        try {
            $pdo = Database::getInstance();
            $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua  = isset($_SERVER['HTTP_USER_AGENT'])
                ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
                : null;
            $pdo->prepare(
                'INSERT INTO session_logs
                    (user_type, user_id, business_id, ip_address, user_agent, action)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$userType, $userId, $businessId, $ip, $ua, $action]);
        } catch (\Throwable) {
            // No interrumpir flujo principal
        }
    }

    /**
     * Devuelve las IPs bloqueadas actualmente con su conteo de intentos.
     *
     * @return array
     */
    public static function getBlockedIps(): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare(
                "SELECT ip_address, COUNT(*) AS intentos,
                        MIN(attempted_at) AS primer_intento,
                        MAX(attempted_at) AS ultimo_intento
                 FROM login_attempts
                 WHERE attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 GROUP BY ip_address
                 HAVING COUNT(*) >= ?
                 ORDER BY intentos DESC"
            );
            $stmt->execute([self::WINDOW_MINUTES, self::MAX_ATTEMPTS]);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Devuelve las últimas N sesiones registradas con filtros opcionales.
     *
     * @param int         $limit
     * @param string|null $userType
     * @param string|null $fechaDesde  'Y-m-d'
     * @return array
     */
    public static function getSessionLogs(
        int     $limit    = 50,
        ?string $userType = null,
        ?string $fechaDesde = null
    ): array {
        try {
            $pdo    = Database::getInstance();
            $where  = ['1=1'];
            $params = [];

            if ($userType) {
                $where[]  = 'user_type = ?';
                $params[] = $userType;
            }
            if ($fechaDesde) {
                $where[]  = 'created_at >= ?';
                $params[] = $fechaDesde . ' 00:00:00';
            }

            $params[] = $limit;
            $stmt = $pdo->prepare(
                'SELECT * FROM session_logs WHERE ' . implode(' AND ', $where)
                . ' ORDER BY created_at DESC LIMIT ?'
            );
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }
}
