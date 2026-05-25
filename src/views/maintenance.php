<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento – es21plus</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 3rem 2.5rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,.4);
        }
        .icon {
            width: 64px; height: 64px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
        }
        h1 { font-size: 1.5rem; color: #0f172a; margin-bottom: .5rem; }
        p { color: #64748b; line-height: 1.6; font-size: .95rem; }
        .msg {
            margin-top: 1.25rem;
            background: #f1f5f9;
            border-radius: .5rem;
            padding: 1rem;
            color: #334155;
            font-size: .9rem;
            text-align: left;
        }
        .footer { margin-top: 2rem; font-size: .78rem; color: #94a3b8; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
    </div>
    <h1>Sistema en mantenimiento</h1>
    <p>Estamos realizando mejoras. Vuelve en unos minutos.</p>
    <?php if (!empty($maintenanceMessage)): ?>
        <div class="msg"><?= htmlspecialchars($maintenanceMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <div class="footer">es21plus &copy; <?= date('Y') ?></div>
</div>
</body>
</html>
