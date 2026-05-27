<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan vencido – es21plus</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>
<body class="login-page">
<div style="text-align:center;max-width:460px;margin:auto;padding:3rem 1.5rem;">
    <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.5" style="margin:auto;display:block;margin-bottom:1.5rem;">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <h1 style="color:#fff;font-size:1.5rem;margin-bottom:.5rem;">Plan vencido</h1>
    <p style="color:#94a3b8;margin-bottom:2rem;">
        El plan de <strong style="color:#fff;"><?= htmlspecialchars($planExpiredBusiness['name'] ?? 'tu negocio', ENT_QUOTES, 'UTF-8') ?></strong>
        venció el <?= date('d/m/Y', strtotime($planExpiredBusiness['plan_expires_at'] ?? 'now')) ?>.
        Contacta con soporte para renovarlo.
    </p>
    <a href="/api/contact_send.php" class="btn btn-primary" style="display:inline-block;padding:.75rem 1.5rem;">
        Contactar soporte
    </a>
    <br><br>
    <a href="/logout.php" style="color:#475569;font-size:.82rem;">Cerrar sesión</a>
</div>
</body>
</html>
