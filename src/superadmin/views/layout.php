<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Superadmin', ENT_QUOTES, 'UTF-8') ?> — es21plus</title>
    <link rel="stylesheet" href="<?= $cssBase ?? '../' ?>css/estilos.css">
    <style>
        :root {
            --sa-sidebar-bg:    #0a0f1e;
            --sa-sidebar-hover: #13182e;
            --sa-accent:        #818cf8;
            --sa-accent-dark:   #6366f1;
        }
        body { margin: 0; font-family: var(--font-sans); background: var(--body-bg); }

        .sa-wrapper { display: flex; min-height: 100vh; }

        /* ── Sidebar ── */
        .sa-sidebar {
            width: 240px; min-width: 240px;
            background: var(--sa-sidebar-bg);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; bottom: 0; z-index: 100;
        }
        .sa-sidebar-brand { padding: 1.25rem 1.25rem 1rem; border-bottom: 1px solid rgba(255,255,255,.06); }
        .sa-brand-badge {
            display: inline-block; background: var(--sa-accent-dark); color: #fff;
            font-size: .65rem; font-weight: 700; padding: .15rem .5rem; border-radius: 20px;
            letter-spacing: .05em; text-transform: uppercase; margin-bottom: .4rem;
        }
        .sa-brand-name { color: #fff; font-weight: 700; font-size: 1.05rem; margin: 0; }
        .sa-nav { flex: 1; padding: .75rem 0; overflow-y: auto; }
        .sa-nav-section {
            padding: .5rem 1.25rem .25rem; font-size: .68rem; font-weight: 600;
            color: rgba(255,255,255,.3); letter-spacing: .08em; text-transform: uppercase;
        }
        .sa-nav a {
            display: flex; align-items: center; gap: .6rem; padding: .55rem 1.25rem;
            color: rgba(255,255,255,.6); text-decoration: none; font-size: .84rem;
            transition: background .15s, color .15s; border-left: 3px solid transparent;
        }
        .sa-nav a:hover { background: var(--sa-sidebar-hover); color: #fff; }
        .sa-nav a.active { background: rgba(99,102,241,.15); color: var(--sa-accent); border-left-color: var(--sa-accent); }
        .sa-nav a svg { opacity: .7; flex-shrink: 0; }
        .sa-nav a.active svg { opacity: 1; }
        .sa-badge-count {
            margin-left: auto; background: #ef4444; color: #fff;
            font-size: .65rem; font-weight: 700; padding: .1rem .4rem;
            border-radius: 20px; min-width: 18px; text-align: center;
        }
        .sa-sidebar-footer { padding: 1rem 1.25rem; border-top: 1px solid rgba(255,255,255,.06); }
        .sa-user-mini { display: flex; align-items: center; gap: .6rem; color: rgba(255,255,255,.7); font-size: .82rem; margin-bottom: .75rem; }
        .sa-avatar-mini {
            width: 30px; height: 30px; background: var(--sa-accent-dark); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; color: #fff; flex-shrink: 0;
        }
        .sa-logout {
            display: flex; align-items: center; gap: .5rem; color: #f87171;
            text-decoration: none; font-size: .82rem; padding: .4rem .5rem;
            border-radius: .4rem; transition: background .15s;
        }
        .sa-logout:hover { background: rgba(239,68,68,.1); }

        /* ── Main area ── */
        .sa-main { margin-left: 240px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .sa-topbar {
            height: 56px; background: #fff; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 1.5rem; gap: 1rem;
            position: sticky; top: 0; z-index: 50;
        }
        .sa-topbar-title { font-weight: 600; font-size: .95rem; color: var(--text-primary); flex: 1; }
        .sa-content { padding: 1.75rem; flex: 1; }

        /* ── Cards métricas ── */
        .sa-metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .sa-metric-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.25rem; }
        .sa-metric-label { font-size: .78rem; color: var(--text-muted); margin-bottom: .4rem; }
        .sa-metric-value { font-size: 2rem; font-weight: 700; color: var(--text-primary); line-height: 1; }
        .sa-metric-icon { float: right; opacity: .15; }
        .sa-metric-variation { margin-top: .5rem; font-size: .75rem; display: inline-flex; align-items: center; gap: .25rem; }
        .sa-var-up   { color: #16a34a; background: #dcfce7; padding: .1rem .4rem; border-radius: 20px; }
        .sa-var-down { color: #dc2626; background: #fee2e2; padding: .1rem .4rem; border-radius: 20px; }
        .sa-var-same { color: #64748b; background: #f1f5f9; padding: .1rem .4rem; border-radius: 20px; }

        /* ── Table styles ── */
        .sa-table-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 1.5rem; }
        .sa-table-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .sa-table-header h3 { margin: 0; font-size: .9rem; font-weight: 600; }
        .sa-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
        .sa-table th { padding: .65rem 1rem; background: var(--surface); color: var(--text-muted); font-weight: 500; font-size: .75rem; text-align: left; border-bottom: 1px solid var(--border); }
        .sa-table td { padding: .7rem 1rem; border-bottom: 1px solid #f1f5f9; color: var(--text-secondary); vertical-align: middle; }
        .sa-table tr:last-child td { border-bottom: none; }
        .sa-table tr:hover td { background: var(--surface); }
        .sa-table a { color: var(--accent); text-decoration: none; }
        .sa-table a:hover { text-decoration: underline; }
        .sa-row-danger td { background: #fff1f2 !important; }
        .sa-row-warn  td { background: #fffbeb !important; }

        /* ── Badge ── */
        .badge-active   { background: var(--success-light); color: #15803d; padding: .2rem .6rem; border-radius: 20px; font-size: .72rem; font-weight: 600; }
        .badge-inactive { background: var(--danger-light);  color: #b91c1c; padding: .2rem .6rem; border-radius: 20px; font-size: .72rem; font-weight: 600; }
        .badge-free     { background: var(--surface); color: var(--text-muted); padding: .2rem .6rem; border-radius: 20px; font-size: .72rem; }
        .badge-basic    { background: var(--info-light); color: #1d4ed8; padding: .2rem .6rem; border-radius: 20px; font-size: .72rem; font-weight: 600; }
        .badge-pro      { background: var(--purple-light); color: #7c3aed; padding: .2rem .6rem; border-radius: 20px; font-size: .72rem; font-weight: 600; }
        .badge-unread   { background: var(--warning-light); color: #92400e; padding: .2rem .6rem; border-radius: 20px; font-size: .72rem; font-weight: 600; }

        /* ── Flash messages ── */
        .sa-alert { padding: .75rem 1rem; border-radius: .5rem; font-size: .85rem; margin-bottom: 1rem; }
        .sa-alert-success { background: var(--success-light); color: #15803d; }
        .sa-alert-error   { background: var(--danger-light);  color: #b91c1c; }

        /* ── Form ── */
        .sa-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
        .sa-form-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.5rem; }
        .sa-form-card h2 { margin: 0 0 1.25rem; font-size: 1rem; font-weight: 600; }

        /* ── Bell notification ── */
        .sa-bell-wrap { position: relative; }
        .sa-bell-btn {
            background: none; border: none; cursor: pointer; padding: .4rem;
            color: var(--text-muted); display: flex; align-items: center; border-radius: .4rem;
            transition: background .15s, color .15s;
        }
        .sa-bell-btn:hover { background: var(--surface); color: var(--text-primary); }
        .sa-bell-badge {
            position: absolute; top: 0; right: 0;
            background: #ef4444; color: #fff;
            font-size: .6rem; font-weight: 700;
            min-width: 16px; height: 16px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 .25rem;
        }
        .sa-bell-badge.hidden { display: none; }
        .sa-notif-dropdown {
            position: absolute; right: 0; top: calc(100% + 8px);
            width: 340px; background: #fff; border: 1px solid var(--border);
            border-radius: .75rem; box-shadow: 0 10px 30px rgba(0,0,0,.12);
            z-index: 200; display: none;
        }
        .sa-notif-dropdown.open { display: block; }
        .sa-notif-header { padding: .75rem 1rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .sa-notif-header span { font-weight: 600; font-size: .85rem; }
        .sa-notif-read-all { background: none; border: none; color: var(--accent); font-size: .78rem; cursor: pointer; }
        .sa-notif-read-all:hover { text-decoration: underline; }
        .sa-notif-list { max-height: 360px; overflow-y: auto; }
        .sa-notif-item { display: flex; gap: .75rem; padding: .75rem 1rem; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background .1s; text-decoration: none; color: inherit; }
        .sa-notif-item:hover { background: var(--surface); }
        .sa-notif-item:last-child { border-bottom: none; }
        .sa-notif-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--sa-accent); flex-shrink: 0; margin-top: 5px; }
        .sa-notif-body { flex: 1; min-width: 0; }
        .sa-notif-title { font-size: .82rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sa-notif-desc  { font-size: .75rem; color: var(--text-muted); margin-top: .15rem; }
        .sa-notif-time  { font-size: .7rem; color: var(--text-muted); white-space: nowrap; }
        .sa-notif-empty { padding: 1.5rem 1rem; text-align: center; color: var(--text-muted); font-size: .84rem; }

        /* ── Charts grid ── */
        .sa-charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        @media (max-width: 900px) { .sa-charts-grid { grid-template-columns: 1fr; } }

        /* ── Settings tabs ── */
        .sa-tabs-nav { display: flex; gap: .25rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border); }
        .sa-tab-btn {
            background: none; border: none; cursor: pointer;
            padding: .6rem 1rem; font-size: .85rem; color: var(--text-muted);
            border-bottom: 2px solid transparent; margin-bottom: -1px;
            transition: color .15s, border-color .15s;
        }
        .sa-tab-btn.active { color: #6366f1; border-bottom-color: #6366f1; font-weight: 600; }
        .sa-tab-btn:hover:not(.active) { color: var(--text-primary); }
        .sa-tab-panel { display: none; }
        .sa-tab-panel.active { display: block; }

        /* ── Toggle switch — specificity > input[type="checkbox"] en estilos.css ── */
        .toggle-wrap { display: flex; align-items: center; gap: .75rem; }
        input.sa-toggle {
            position: relative; display: inline-block;
            width: 44px !important; height: 24px !important; flex-shrink: 0;
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            background: #cbd5e1; border-radius: 12px; cursor: pointer;
            transition: background .2s; margin: 0; padding: 0; vertical-align: middle;
            overflow: visible;
        }
        input.sa-toggle:checked { background: #6366f1; }
        input.sa-toggle::after {
            content: ''; position: absolute;
            top: 3px; left: 3px;
            width: 18px; height: 18px; border-radius: 50%;
            background: #fff; transition: transform .2s; box-shadow: 0 1px 3px rgba(0,0,0,.2);
        }
        input.sa-toggle:checked::after { transform: translateX(20px); }

        /* ── Extra page styles injected per-page ── */
        <?= $pageStyles ?? '' ?>
    </style>
</head>
<body>
<div class="sa-wrapper">

    <!-- Sidebar -->
    <aside class="sa-sidebar">
        <div class="sa-sidebar-brand">
            <span class="sa-brand-badge">Superadmin</span>
            <p class="sa-brand-name">es21plus</p>
        </div>
        <nav class="sa-nav">
            <div class="sa-nav-section">Panel</div>
            <a href="<?= $base ?? './' ?>dashboard.php" class="<?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>

            <div class="sa-nav-section">Gestión</div>
            <a href="<?= $base ?? './' ?>businesses.php" class="<?= ($activeMenu ?? '') === 'businesses' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Negocios
            </a>
            <a href="<?= $base ?? './' ?>employees.php" class="<?= ($activeMenu ?? '') === 'employees' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                Empleados
            </a>

            <div class="sa-nav-section">Comunicación</div>
            <a href="<?= $base ?? './' ?>contact.php" class="<?= ($activeMenu ?? '') === 'contact' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Mensajes
                <?php if (($mensajesSinLeer ?? 0) > 0): ?>
                    <span class="sa-badge-count"><?= (int)$mensajesSinLeer ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $base ?? './' ?>tickets.php" class="<?= ($activeMenu ?? '') === 'tickets' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                Tickets
            </a>
            <a href="<?= $base ?? './' ?>announcements.php" class="<?= ($activeMenu ?? '') === 'announcements' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 17H2a3 3 0 000 6h20a3 3 0 000-6z"/><path d="M6 17V3a2 2 0 012-2h8a2 2 0 012 2v14"/></svg>
                Anuncios
            </a>

            <a href="<?= $base ?? './' ?>campaigns.php" class="<?= ($activeMenu ?? '') === 'campaigns' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Campañas email
            </a>

            <div class="sa-nav-section">Finanzas</div>
            <a href="<?= $base ?? './' ?>billing.php" class="<?= ($activeMenu ?? '') === 'billing' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                Facturación
            </a>
            <a href="<?= $base ?? './' ?>reports.php" class="<?= ($activeMenu ?? '') === 'reports' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Reportes
            </a>

            <div class="sa-nav-section">Auditoría</div>
            <a href="<?= $base ?? './' ?>logs.php" class="<?= ($activeMenu ?? '') === 'logs' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Actividad
            </a>

            <div class="sa-nav-section">Sistema</div>
            <a href="<?= $base ?? './' ?>settings.php" class="<?= ($activeMenu ?? '') === 'settings' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Configuración
            </a>
        </nav>

        <div class="sa-sidebar-footer">
            <?php
            $saName = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Superadmin', ENT_QUOTES, 'UTF-8');
            $saInit = mb_strtoupper(mb_substr($_SESSION['usuario_nombre'] ?? 'S', 0, 2));
            ?>
            <div class="sa-user-mini">
                <div class="sa-avatar-mini"><?= $saInit ?></div>
                <div>
                    <div style="font-weight:600;color:#fff;font-size:.8rem;"><?= $saName ?></div>
                    <div style="font-size:.72rem;color:rgba(255,255,255,.4);">Superadmin</div>
                </div>
            </div>
            <a href="<?= $base ?? './' ?>../logout.php" class="sa-logout">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Cerrar sesión
            </a>
        </div>
    </aside>

    <!-- Main -->
    <main class="sa-main">
        <div class="sa-topbar">
            <span class="sa-topbar-title"><?= htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?></span>

            <?php if (!empty($topbarActions)): ?>
                <?= $topbarActions ?>
            <?php endif; ?>

            <!-- Campana de notificaciones -->
            <?php
            if (!isset($notifCount)) {
                $notifCount = 0;
                try {
                    require_once __DIR__ . '/../../core/NotificationService.php';
                    $notifCount = NotificationService::unreadCount();
                } catch (\Throwable) {}
            }
            ?>
            <div class="sa-bell-wrap" id="bellWrap">
                <button class="sa-bell-btn" id="bellBtn" aria-label="Notificaciones" onclick="toggleNotifDropdown(event)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
                    </svg>
                    <span class="sa-bell-badge <?= $notifCount === 0 ? 'hidden' : '' ?>" id="bellBadge">
                        <?= $notifCount > 99 ? '99+' : $notifCount ?>
                    </span>
                </button>

                <div class="sa-notif-dropdown" id="notifDropdown">
                    <div class="sa-notif-header">
                        <span>Notificaciones</span>
                        <button class="sa-notif-read-all" onclick="markAllRead()">Marcar todas leídas</button>
                    </div>
                    <div class="sa-notif-list" id="notifList">
                        <div class="sa-notif-empty">Cargando…</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sa-content">
            <?php if (!empty($flashSuccess)): ?>
                <div class="sa-alert sa-alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (!empty($flashError)): ?>
                <div class="sa-alert sa-alert-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?= $content ?? '' ?>
        </div>
    </main>
</div>

<script>
/**
 * Gestión del dropdown de notificaciones y auto-actualización del badge.
 */
(function () {
    const BASE = '<?= rtrim($base ?? './', '/') ?>';

    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', (e) => {
        const wrap = document.getElementById('bellWrap');
        if (wrap && !wrap.contains(e.target)) {
            document.getElementById('notifDropdown').classList.remove('open');
        }
    });

    window.toggleNotifDropdown = function (e) {
        e.stopPropagation();
        const dd = document.getElementById('notifDropdown');
        const isOpen = dd.classList.toggle('open');
        if (isOpen) loadNotifications();
    };

    /**
     * Carga las últimas notificaciones no leídas y renderiza el dropdown.
     */
    async function loadNotifications() {
        const list = document.getElementById('notifList');
        try {
            const res  = await fetch(BASE + '/notifications/unread-count.php');
            const json = await res.json();
            updateBadge(json?.data?.count ?? 0);
        } catch {}

        // Cargar lista de notifs
        try {
            const res   = await fetch(BASE + '/notifications/list.php');
            const json  = await res.json();
            if (!json.success || !json.data?.length) {
                list.innerHTML = '<div class="sa-notif-empty">Sin notificaciones pendientes.</div>';
                return;
            }
            list.innerHTML = json.data.map(n => `
                <a href="${n.link || '#'}" class="sa-notif-item" onclick="handleNotifClick(event, '${n.link || '#'}')">
                    <div class="sa-notif-dot" style="background:${notifColor(n.type)}"></div>
                    <div class="sa-notif-body">
                        <div class="sa-notif-title">${escHtml(n.title)}</div>
                        <div class="sa-notif-desc">${escHtml(n.body || '')}</div>
                    </div>
                    <div class="sa-notif-time">${timeAgo(n.created_at)}</div>
                </a>
            `).join('');
        } catch {
            list.innerHTML = '<div class="sa-notif-empty">Error al cargar notificaciones.</div>';
        }
    }

    function notifColor(type) {
        const colors = {
            new_business: '#818cf8', plan_expiring: '#f59e0b',
            critical_ticket: '#ef4444', new_message: '#22c55e',
            business_deactivated: '#ef4444',
        };
        return colors[type] || '#818cf8';
    }

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)   return 'ahora';
        if (diff < 3600) return Math.floor(diff/60) + 'm';
        if (diff < 86400) return Math.floor(diff/3600) + 'h';
        return Math.floor(diff/86400) + 'd';
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    window.handleNotifClick = function(e, link) {
        document.getElementById('notifDropdown').classList.remove('open');
    };

    /**
     * Marca todas las notificaciones como leídas.
     */
    window.markAllRead = async function () {
        try {
            await fetch(BASE + '/notifications/read-all.php', { method: 'POST' });
            updateBadge(0);
            document.getElementById('notifList').innerHTML =
                '<div class="sa-notif-empty">Sin notificaciones pendientes.</div>';
            document.getElementById('notifDropdown').classList.remove('open');
        } catch {}
    };

    function updateBadge(count) {
        const badge = document.getElementById('bellBadge');
        if (!badge) return;
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.toggle('hidden', count === 0);
    }

    // Auto-actualizar badge cada 60 segundos
    async function refreshBadge() {
        try {
            const res  = await fetch(BASE + '/notifications/unread-count.php');
            const json = await res.json();
            updateBadge(json?.data?.count ?? 0);
        } catch {}
    }

    setInterval(refreshBadge, 60000);
})();
</script>
</body>
</html>
