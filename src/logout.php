<?php
/**
 * Cierre de sesión del sistema.
 *
 * Registra el logout en session_logs si es un empleado de empresa,
 * luego destruye la sesión completamente.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.1.0
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Registrar logout de empleado de empresa
if (isset($_SESSION['user_id'], $_SESSION['business_id'])) {
    try {
        require_once __DIR__ . '/includes/Database.php';
        require_once __DIR__ . '/core/SecurityService.php';
        SecurityService::logSession(
            'employee',
            (int)$_SESSION['user_id'],
            (int)$_SESSION['business_id'],
            'logout'
        );
    } catch (\Throwable) {
        // No interrumpir el logout
    }
}

session_unset();
session_destroy();

// Destruir cookie de sesión
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

header('Location: login.php');
exit;
