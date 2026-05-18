<?php
/**
 * Middleware de verificación de negocio y plan.
 *
 * Aplicar en cada request del panel de negocios.
 * Verifica: negocio activo, plan vigente, funcionalidades según plan.
 *
 * @package  Es21Plus\Middleware
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';

class BusinessMiddleware
{
    /**
     * Verifica acceso del negocio. Bloquea si inactivo o plan vencido.
     *
     * @param int $businessId
     * @return array Datos del negocio verificado.
     */
    public static function require(int $businessId): array
    {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare('SELECT * FROM businesses WHERE id = ?');
            $stmt->execute([$businessId]);
            $business = $stmt->fetch();
        } catch (\Throwable) {
            return [];
        }

        if (!$business || !$business['is_active']) {
            self::bloqueado('Tu negocio está desactivado. Contacta con soporte.', $businessId);
        }

        if ($business['plan_expires_at'] && strtotime($business['plan_expires_at']) < time()) {
            self::planVencido($business);
        }

        return $business;
    }

    /**
     * Indica si el plan permite una funcionalidad premium.
     *
     * @param string $feature 'csv_export' | 'advanced_reports'
     * @param string $plan    'free' | 'basic' | 'pro'
     * @return bool
     */
    public static function puedeUsar(string $feature, string $plan): bool
    {
        $premiumFeatures = ['csv_export', 'advanced_reports', 'pdf_export'];
        if (!in_array($feature, $premiumFeatures, true)) {
            return true;
        }
        return in_array($plan, ['basic', 'pro'], true);
    }

    private static function bloqueado(string $mensaje, int $businessId): never
    {
        http_response_code(403);
        include __DIR__ . '/../superadmin/views/plan_blocked.php';
        exit;
    }

    private static function planVencido(array $business): never
    {
        $planExpiredBusiness = $business;
        http_response_code(402);
        include __DIR__ . '/../superadmin/views/plan_expired.php';
        exit;
    }
}
