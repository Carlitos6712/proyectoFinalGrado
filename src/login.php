<?php
/**
 * Página de login del sistema de inventario.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @author   miguelrechefdez
 * @version  1.0.0
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está logueado, redirigir al dashboard
if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Usuario.php';

$error    = '';
$redirect = $_GET['redirect'] ?? 'index.php';
$expired  = isset($_GET['expired']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($username === '' || $password === '') {
            throw new AppException('Usuario y contraseña son obligatorios.', 400);
        }

        $usuarioModel = new Usuario();
        $usuarioModel->verificarRateLimit($ip);

        try {
            $usuario = $usuarioModel->verificarCredenciales($username, $password);
        } catch (AppException $e) {
            $usuarioModel->registrarIntentoFallido($ip);
            throw $e;
        }

        // Login exitoso
        session_regenerate_id(true);
        $_SESSION['usuario_id']       = $usuario['id'];
        $_SESSION['usuario_nombre']   = $usuario['nombre_completo'];
        $_SESSION['usuario_username'] = $usuario['username'];
        $_SESSION['last_activity']    = time();

        $usuarioModel->limpiarIntentos($ip);
        $usuarioModel->registrarLogin($usuario['id']);

        $safeRedirect = filter_var($redirect, FILTER_VALIDATE_URL) === false ? 'index.php' : $redirect;
        header('Location: ' . $safeRedirect);
        exit;

    } catch (AppException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Error inesperado. Inténtalo de nuevo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        /* Estilos específicos de la página de login */
        body.login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            padding: 1rem;
        }
        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .login-logo svg {
            color: #6366f1;
        }
        .login-subtitle {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }
        .login-card h2 {
            font-size: 1.4rem;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }
        .login-card p.desc {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1.75rem;
        }
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #94a3b8;
            font-size: 0.8rem;
        }
        .expired-notice {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="login-page">
<div class="login-wrapper">
    <div class="login-header">
        <div class="login-logo">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
            </svg>
            es21<strong>plus</strong>
        </div>
        <p class="login-subtitle">Sistema de Inventario para Mecánico de Motos</p>
    </div>

    <div class="login-card">
        <h2>Iniciar sesión</h2>
        <p class="desc">Introduce tus credenciales para acceder al panel.</p>

        <?php if ($expired): ?>
        <div class="expired-notice">Tu sesión ha expirado por inactividad. Por favor, inicia sesión de nuevo.</div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-banner alert-banner-error" style="margin-bottom:1.25rem;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-field" style="margin-bottom:1.25rem;">
                <label class="field-label">Usuario</label>
                <input type="text" name="username" class="field-input" required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Introduce tu usuario">
            </div>
            <div class="form-field" style="margin-bottom:1.75rem;">
                <label class="field-label">Contraseña</label>
                <input type="password" name="password" class="field-input" required
                       placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
                Entrar al sistema
            </button>
        </form>
    </div>

    <div class="login-footer">
        <a href="landing.php" style="color:#6366f1;text-decoration:none;">&larr; Volver al inicio</a>
        &nbsp;&middot;&nbsp;
        &copy; <?= date('Y') ?> es21plus · Carlos Vico
    </div>
</div>
</body>
</html>
