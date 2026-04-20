<?php
/**
 * Cierre de sesión del sistema.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @author   miguelrechefdez
 * @version  1.0.0
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
