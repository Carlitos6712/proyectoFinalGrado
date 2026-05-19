<?php
/**
 * Gestión centralizada de sesión para el sistema multi-tenant.
 *
 * Abstrae los dos contextos de sesión:
 *   - Usuario local (tabla `usuarios`): claves usuario_id / rol
 *   - Empleado de empresa (tabla `employees`): claves user_id / business_id / user_role
 *
 * @package  Es21Plus\Core
 * @author   Carlos Vico
 * @version  1.0.0
 */
class Session
{
    /**
     * Rellena la sesión completa para un empleado de empresa.
     *
     * @param array $employee  Fila de `employees`.
     * @param array $business  Fila de `businesses`.
     * @return void
     */
    public static function setBusinessUser(array $employee, array $business): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        $_SESSION['user_id']                    = (int)$employee['id'];
        $_SESSION['user_name']                   = $employee['name'];
        $_SESSION['user_email']                  = $employee['email'];
        $_SESSION['user_role']                   = $employee['role'];
        $_SESSION['business_id']                 = (int)$business['id'];
        $_SESSION['business_name']               = $business['name'];
        $_SESSION['business_slug']               = $business['slug'];
        $_SESSION['business_active']             = (int)$business['is_active'];
        $_SESSION['business_plan']               = $business['plan'];
        $_SESSION['business_logo']               = $business['logo_path'] ?? null;
        $_SESSION['business_theme_color']        = $business['theme_color'] ?? '#4F46E5';
        $_SESSION['business_onboarding_completed'] = (int)($business['onboarding_completed'] ?? 0);
        $_SESSION['last_activity']               = time();
    }

    /**
     * Devuelve el business_id de la sesión, o null si no existe.
     *
     * @return int|null
     */
    public static function getBusinessId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            return null;
        }
        return isset($_SESSION['business_id']) ? (int)$_SESSION['business_id'] : null;
    }

    /**
     * Devuelve el business_id o lanza excepción si no hay sesión de empresa activa.
     *
     * @throws \RuntimeException
     * @return int
     */
    public static function requireBusinessId(): int
    {
        $id = self::getBusinessId();
        if ($id === null) {
            throw new \RuntimeException('No hay sesión de empresa activa.');
        }
        return $id;
    }

    /**
     * Indica si la sesión activa es de superadmin.
     *
     * @return bool
     */
    public static function isSuperadmin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) return false;
        return ($_SESSION['rol'] ?? '') === 'superadmin';
    }

    /**
     * Indica si la sesión activa es de empleado de empresa (multi-tenant).
     *
     * @return bool
     */
    public static function isBusinessUser(): bool
    {
        if (session_status() === PHP_SESSION_NONE) return false;
        return isset($_SESSION['business_id']) && isset($_SESSION['user_id']);
    }

    /**
     * Destruye la sesión completamente.
     *
     * @return void
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
    }

    /**
     * Devuelve el nombre del usuario en sesión (ambos contextos).
     *
     * @return string
     */
    public static function getUserName(): string
    {
        if (session_status() === PHP_SESSION_NONE) return '';
        return (string)($_SESSION['user_name'] ?? $_SESSION['usuario_nombre'] ?? '');
    }

    /**
     * Indica si el superadmin está impersonando una empresa.
     *
     * @return bool
     */
    public static function isImpersonating(): bool
    {
        if (session_status() === PHP_SESSION_NONE) return false;
        return !empty($_SESSION['superadmin_impersonating']);
    }
}
