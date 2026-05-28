<?php
/**
 * Verificación de autenticación para páginas protegidas.
 *
 * Incluir con require_once al inicio de cada página que requiera login.
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tiempo máximo de inactividad: 2 horas
define('SESSION_TIMEOUT', 7200);

/**
 * Verifica si el usuario tiene sesión activa y no ha expirado.
 * Redirige a login.php si no está autenticado.
 * Bloquea el acceso al panel normal a usuarios con rol superadmin.
 *
 * @return void
 */
function requireAuth(): void
{
    // Business user takes priority — during impersonation both sets of keys coexist
    $isBusinessUser = !empty($_SESSION['user_id']) && !empty($_SESSION['business_id']);
    $isLocalUser    = !$isBusinessUser && !empty($_SESSION['usuario_id']);

    if (!$isLocalUser && !$isBusinessUser) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    if ($isLocalUser) {
        // Usuario desactivado → destruir sesión
        if ((int)($_SESSION['usuario_activo'] ?? 1) === 0) {
            session_unset();
            session_destroy();
            header('Location: login.php?deactivated=1');
            exit;
        }

        // Verificar expiración por inactividad
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: login.php?expired=1');
            exit;
        }

        // Superadmin no tiene acceso al panel normal
        if (($_SESSION['rol'] ?? '') === 'superadmin') {
            header('Location: superadmin/index.php');
            exit;
        }
    }

    $_SESSION['last_activity'] = time();
}

requireAuth();

/**
 * Devuelve true si el usuario activo tiene rol admin o superadmin.
 * Cubre ambos tipos de sesión: login local (rol) y login de empresa (user_role).
 *
 * @return bool
 * @author Carlitos6712
 */
function isAdmin(): bool
{
    $rol = $_SESSION['user_role'] ?? $_SESSION['rol'] ?? '';
    return in_array($rol, ['admin', 'superadmin'], true);
}

/**
 * Devuelve el rol normalizado del usuario activo.
 *
 * @return string 'admin' | 'superadmin' | 'employee'
 * @author Carlitos6712
 */
function currentRole(): string
{
    return $_SESSION['user_role'] ?? $_SESSION['rol'] ?? 'employee';
}
