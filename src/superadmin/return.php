<?php
/**
 * Retorno de sesión impersonada al panel de superadmin.
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/csrf.php';

// Solo admite POST con CSRF válido
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !validateCsrfToken('sa_return', $_POST['csrf_token'] ?? '')) {
    header('Location: /login.php');
    exit;
}

// Verificar que realmente hay una sesión impersonada
if (empty($_SESSION['superadmin_impersonating'])) {
    header('Location: /login.php');
    exit;
}

// Restaurar sesión de superadmin
$uid      = $_SESSION['superadmin_return_uid']      ?? null;
$nombre   = $_SESSION['superadmin_return_nombre']   ?? 'Super Administrador';
$username = $_SESSION['superadmin_return_username'] ?? 'superadmin';

// Limpiar toda la sesión y reconstruir con datos del superadmin
session_unset();
session_regenerate_id(true);

$_SESSION['usuario_id']       = $uid;
$_SESSION['usuario_nombre']   = $nombre;
$_SESSION['usuario_username'] = $username;
$_SESSION['rol']              = 'superadmin';
$_SESSION['usuario_activo']   = 1;
$_SESSION['last_activity']    = time();

header('Location: /superadmin/dashboard.php');
exit;
