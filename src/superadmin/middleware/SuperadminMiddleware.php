<?php
/**
 * Middleware exclusivo para el panel de superadministración.
 *
 * Verifica que la sesión activa corresponde a un usuario con rol superadmin.
 * Destruye la sesión y redirige al login si el acceso no está autorizado.
 *
 * @package  Es21Plus\Superadmin\Middleware
 * @author   Carlos Vico
 * @version  1.0.0
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
