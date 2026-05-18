<?php
/**
 * Middleware exclusivo para el panel de superadministración.
 *
 * Verifica sesión de superadmin, aplica timeout de inactividad,
 * y comprueba planes de empresa próximos a vencer para generar notificaciones.
 *
 * @package  Es21Plus\Superadmin\Middleware
 * @author   Carlos Vico
 * @version  1.1.0
 */
class SuperadminMiddleware
{
    /**
     * Garantiza acceso exclusivo al rol superadmin.
     *
     * @return void
     */
    public static function require(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login.php');
            exit;
        }

        if (($_SESSION['rol'] ?? '') !== 'superadmin') {
            session_unset();
            session_destroy();
            header('Location: /login.php?error=acceso_denegado');
            exit;
        }

        // Inactividad: 4 horas para superadmin
        $timeout = 14400;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
            session_unset();
            session_destroy();
            header('Location: /login.php?expired=1');
            exit;
        }

        $_SESSION['last_activity'] = time();

        // Verificar planes próximos a vencer (una vez por sesión, máx. cada 5 min)
        $lastPlanCheck = $_SESSION['sa_last_plan_check'] ?? 0;
        if ((time() - $lastPlanCheck) > 300) {
            $_SESSION['sa_last_plan_check'] = time();
            try {
                if (!class_exists('NotificationService')) {
                    require_once __DIR__ . '/../../core/NotificationService.php';
                }
                NotificationService::checkExpiringPlans();
            } catch (\Throwable) {
                // No interrumpir flujo si falla
            }
        }
    }

    /**
     * Devuelve el nombre del superadmin en sesión.
     *
     * @return string
     */
    public static function nombre(): string
    {
        return htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Superadmin', ENT_QUOTES, 'UTF-8');
    }
}
