<?php
/**
 * Verificación de autenticación para páginas protegidas.
 *
 * Incluir con require_once al inicio de cada página que requiera login.
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
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
 *
 * @return void
 */
function requireAuth(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    // Verificar expiración por inactividad
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

requireAuth();
