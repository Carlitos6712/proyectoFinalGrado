<?php
/**
 * Panel de superadministración – punto de entrada.
 *
 * Redirige al dashboard principal del panel superadmin.
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.0.0
 */

header('Location: dashboard.php');
exit;

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

$nombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Super Admin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin – es21plus</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body class="login-page">
<div style="text-align:center;max-width:480px;margin:auto;padding:3rem 1.5rem;">

    <div style="margin-bottom:2rem;">
        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="1.5" style="margin:auto;">
            <circle cx="12" cy="12" r="3"/>
            <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
        </svg>
    </div>

    <h1 style="color:#fff;font-size:1.6rem;margin-bottom:.5rem;">Panel de Superadmin</h1>
    <p style="color:#94a3b8;margin-bottom:2.5rem;">Bienvenido, <?= $nombre ?>. Este panel está en desarrollo.</p>

    <?php if ($flashError !== ''): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1.5rem;font-size:.85rem;">
        <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <div style="display:flex;flex-direction:column;gap:.75rem;align-items:center;">
        <a href="../logout.php" class="btn btn-secondary" style="width:220px;justify-content:center;">
            Cerrar sesión
        </a>
    </div>

    <p style="color:#475569;font-size:.78rem;margin-top:2.5rem;">
        &copy; <?= date('Y') ?> es21plus &middot; Carlos Vico
    </p>
</div>
</body>
</html>
