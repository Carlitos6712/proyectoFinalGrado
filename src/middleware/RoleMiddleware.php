<?php
/**
 * Middleware de control de acceso basado en roles.
 *
 * Uso en páginas del panel:
 *   require_once __DIR__ . '/../middleware/RoleMiddleware.php';
 *   RoleMiddleware::requireEmployee(); // cualquier rol de panel
 *   RoleMiddleware::requireAdmin();    // solo admin / superadmin
 *
 * @package  Es21Plus\Middleware
 * @author   Carlos Vico
 * @version  1.0.0
 */

class RoleMiddleware
{
    /** Roles con acceso al panel normal (no superadmin). */
    private const PANEL_ROLES = ['admin', 'employee'];

    /**
     * Verifica que el usuario tenga uno de los roles permitidos.
     *
     * Comprueba también que el usuario siga activo (usuario_activo === 1).
     * Redirige a login con mensaje de error si el acceso no está permitido.
     *
     * @param string[] $allowedRoles Roles que tienen acceso a la ruta.
     * @return void
     */
    public static function requireRole(array $allowedRoles): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Sin sesión → login
        if (empty($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit;
        }

        // Usuario desactivado → destruir sesión y redirigir
        if ((int)($_SESSION['usuario_activo'] ?? 1) === 0) {
            session_unset();
            session_destroy();
            header('Location: login.php?deactivated=1');
            exit;
        }

        $rol = $_SESSION['rol'] ?? '';

        if (!in_array($rol, $allowedRoles, true)) {
            $_SESSION['flash_error'] = 'No tienes permiso para acceder a esa sección.';

            // Superadmin no accede al panel normal
            if ($rol === 'superadmin') {
                header('Location: superadmin/index.php');
                exit;
            }

            // Employee intenta acceder a ruta de admin
            header('Location: index.php');
            exit;
        }
    }

    /**
     * Permite acceso solo a admin y superadmin.
     *
     * @return void
     */
    public static function requireAdmin(): void
    {
        self::requireRole(['admin', 'superadmin']);
    }

    /**
     * Permite acceso a cualquier rol del panel normal (admin + employee).
     *
     * @return void
     */
    public static function requireEmployee(): void
    {
        self::requireRole(self::PANEL_ROLES);
    }

    /**
     * Permite acceso solo a superadmin (panel de superadministración).
     *
     * @return void
     */
    public static function requireSuperadmin(): void
    {
        self::requireRole(['superadmin']);
    }

    /**
     * Devuelve true si el usuario de la sesión tiene alguno de los roles dados.
     *
     * @param string[] $roles
     * @return bool
     */
    public static function hasRole(array $roles): bool
    {
        return in_array($_SESSION['rol'] ?? '', $roles, true);
    }
}
