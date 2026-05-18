<?php
/**
 * Middleware de acceso al panel de empresa (multi-tenant).
 *
 * Verifica en orden:
 *   1. Sesión de empleado activa (business_id + user_id)
 *   2. Negocio activo en BD
 *   3. Plan no vencido
 *   4. Rol válido (admin | employee, nunca superadmin)
 *
 * @package  Es21Plus\Middleware
 * @author   Carlos Vico
 * @version  2.0.0
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../core/Session.php';

class BusinessMiddleware
{
    /**
     * Aplica todas las verificaciones de acceso al panel de empresa.
     *
     * Llama exit si el acceso no está permitido.
     *
     * @return array Datos del negocio verificado (para uso en la vista).
     */
    public static function require(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Verificar sesión de empleado
        if (!Session::isBusinessUser()) {
            session_unset();
            session_destroy();
            header('Location: /login.php');
            exit;
        }

        // 2. Rol válido — superadmin no accede al panel de empresa
        $role = $_SESSION['user_role'] ?? '';
        if (!in_array($role, ['admin', 'employee'], true)) {
            session_unset();
            session_destroy();
            header('Location: /login.php');
            exit;
        }

        // 3. Inactividad: 4 horas
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 14400) {
            session_unset();
            session_destroy();
            header('Location: /login.php?expired=1');
            exit;
        }
        $_SESSION['last_activity'] = time();

        // 4. Verificar negocio en BD
        $businessId = Session::getBusinessId();
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare('SELECT * FROM businesses WHERE id = ?');
            $stmt->execute([$businessId]);
            $business = $stmt->fetch();
        } catch (\Throwable) {
            $business = null;
        }

        if (!$business || !(int)$business['is_active']) {
            session_unset();
            session_destroy();
            header('Location: /login.php?error=negocio_inactivo');
            exit;
        }

        // 5. Plan vencido → pantalla de bloqueo (sin destruir sesión)
        if ($business['plan_expires_at'] && strtotime($business['plan_expires_at']) < time()) {
            $planExpiredBusiness = $business;
            http_response_code(402);
            include __DIR__ . '/../superadmin/views/plan_expired.php';
            exit;
        }

        return $business;
    }

    /**
     * Indica si el plan del negocio permite una funcionalidad premium.
     *
     * @param string $feature  'csv_export' | 'advanced_reports' | 'pdf_export'
     * @param string $plan     'free' | 'basic' | 'pro'
     * @return bool
     */
    public static function puedeUsar(string $feature, string $plan): bool
    {
        $premium = ['csv_export', 'advanced_reports', 'pdf_export'];
        if (!in_array($feature, $premium, true)) {
            return true;
        }
        return in_array($plan, ['basic', 'pro'], true);
    }
}
