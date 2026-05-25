<?php
/**
 * Perfil y configuración del negocio.
 *
 * Accesible solo al admin de empresa (role='admin' con business_id en sesión).
 * Permite editar: información general, logo, color de marca y contraseña.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['business_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';

$businessId = (int)$_SESSION['business_id'];
$stockBajoCount = 0;
try { $stockBajoCount = count((new Producto())->filtrarStockBajo()); } catch (\Throwable) {}

// Cargar datos actuales del negocio
$pdo      = Database::getInstance();
$stmt     = $pdo->prepare('SELECT * FROM businesses WHERE id = ?');
$stmt->execute([$businessId]);
$business = $stmt->fetch();

if (!$business) {
    session_unset(); session_destroy();
    header('Location: login.php');
    exit;
}

$themeColor  = $business['theme_color'] ?? '#4F46E5';
$logoPath    = $business['logo_path']   ?? '';
$maxUploadMb = 5;
try {
    require_once __DIR__ . '/core/Settings.php';
    $maxUploadMb = (int)\Settings::get('max_file_upload_mb', '5');
} catch (\Throwable) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de empresa – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <?php require_once __DIR__ . '/includes/theme_inject.php'; ?>
</head>
<body class="layout">

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <?php if ($logoPath): ?>
            <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Logo" style="width:28px;height:28px;object-fit:cover;border-radius:4px;">
            <?php else: ?>
            <svg class="logo-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
            </svg>
            <?php endif; ?>
            <span class="logo-text">es21<strong>plus</strong></span>
        </div>
        <button class="sidebar-close" id="sidebarClose" aria-label="Cerrar menú">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-label">Principal</span>
            <a href="index.php" class="nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['index.php','dashboard.php']) ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="productos.php" class="nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['productos.php','nuevo_producto.php','editar_producto.php']) ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span>
                <span class="nav-label">Productos</span>
            </a>
            <a href="categorias.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                <span class="nav-label">Categorías</span>
            </a>
            <a href="marcas.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'marcas.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></span>
                <span class="nav-label">Marcas</span>
            </a>
            <a href="modelos_moto.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'modelos_moto.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="18" r="3"/><path d="M6 18H4a2 2 0 0 1-2-2v-5l2-5h13l2 5v7h-3M14 18H8"/></svg></span>
                <span class="nav-label">Modelos de Moto</span>
            </a>
            <a href="proveedores.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'proveedores.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></span>
                <span class="nav-label">Proveedores</span>
            </a>
            <a href="pedidos.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
                <span class="nav-label">Pedidos</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Operaciones</span>
            <a href="movimientos.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'movimientos.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></span>
                <span class="nav-label">Movimientos</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Administración</span>
            <a href="auditoria.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'auditoria.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                <span class="nav-label">Auditoría</span>
            </a>
            <a href="usuarios.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'usuarios.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="nav-label">Usuarios</span>
            </a>
            <a href="perfil_empresa.php" class="nav-item active">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
                <span class="nav-label">Perfil de empresa</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Ayuda</span>
            <a href="soporte/mis-tickets.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                <span class="nav-label">Soporte</span>
            </a>
        </div>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar-sm"><?= mb_strtoupper(mb_substr($_SESSION['user_name'] ?? $_SESSION['usuario_nombre'] ?? 'U', 0, 2)) ?></div>
            <div class="sidebar-user-info">
                <span class="user-name-sm"><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></span>
                <span class="user-role"><?= match($_SESSION['user_role'] ?? $_SESSION['rol'] ?? 'employee') { 'superadmin' => 'Super Admin', 'admin' => 'Administrador', default => 'Empleado' } ?></span>
            </div>
        </div>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ===== MAIN WRAPPER ===== -->
<div class="main-wrapper">

    <!-- TOPBAR -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <nav class="breadcrumb-nav">
                <a href="index.php" class="breadcrumb-item">Inicio</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active">Perfil de empresa</span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php require_once __DIR__ . '/includes/topbar_user.php'; ?>
        </div>
    </header>

    <main class="content">
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Perfil de empresa</h1>
                <p class="page-subtitle">Gestiona la información, logo, color y seguridad de tu cuenta.</p>
            </div>
        </div>

        <div id="globalToast" style="display:none;position:fixed;top:1.25rem;right:1.25rem;z-index:999;border-radius:8px;padding:12px 18px;font-size:.9rem;min-width:240px;box-shadow:var(--shadow-lg);"></div>

        <div style="display:grid;gap:1.5rem;max-width:720px;">

            <!-- ── Información general ─────────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Información general</h2>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                        <div class="form-field">
                            <label class="field-label">Nombre comercial *</label>
                            <input type="text" id="infoName" class="field-input"
                                   value="<?= htmlspecialchars($business['name'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-field">
                            <label class="field-label">Teléfono</label>
                            <input type="tel" id="infoPhone" class="field-input"
                                   value="<?= htmlspecialchars($business['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <div class="form-field" style="margin-bottom:1rem;">
                        <label class="field-label">Dirección</label>
                        <input type="text" id="infoAddress" class="field-input"
                               value="<?= htmlspecialchars($business['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-field" style="margin-bottom:1.25rem;">
                        <label class="field-label">Email de contacto</label>
                        <input type="email" id="infoEmail" class="field-input"
                               value="<?= htmlspecialchars($business['contact_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <button class="btn btn-primary" onclick="saveInfo()">Guardar información</button>
                </div>
            </div>

            <!-- ── Logo ──────────────────────────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Logo de la empresa</h2>
                </div>
                <div class="card-body">
                    <div style="display:flex;align-items:center;gap:1.5rem;margin-bottom:1.25rem;">
                        <div id="logoCurrentWrap">
                            <?php if ($logoPath): ?>
                            <img id="logoCurrentImg" src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="Logo actual" style="width:72px;height:72px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);">
                            <?php else: ?>
                            <div style="width:72px;height:72px;background:var(--surface);border-radius:var(--radius-sm);border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-muted);">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;">
                            <input type="file" id="logoInput" accept="image/jpeg,image/png,image/webp"
                                   class="field-input" style="padding:6px;" onchange="previewLogo(this)">
                            <p style="font-size:.8rem;color:var(--text-muted);margin-top:.4rem;">JPG, PNG o WebP · Máximo <?= $maxUploadMb ?> MB</p>
                        </div>
                    </div>
                    <div style="display:flex;gap:.75rem;">
                        <button class="btn btn-primary" onclick="uploadLogo()">Subir logo</button>
                        <?php if ($logoPath): ?>
                        <button class="btn btn-danger" onclick="deleteLogo()">Eliminar logo</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── Color de marca ─────────────────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Color de marca</h2>
                </div>
                <div class="card-body">
                    <p style="color:var(--text-secondary);font-size:.9rem;margin-bottom:1rem;">
                        El color se aplica al sidebar, botones y elementos principales del panel.
                    </p>
                    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
                        <input type="color" id="themeColorPicker" value="<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8') ?>"
                               style="width:48px;height:48px;border:none;border-radius:var(--radius-sm);cursor:pointer;padding:2px;"
                               oninput="applyThemePreview(this.value)">
                        <div>
                            <span id="themeColorHex" style="font-family:var(--font-mono);font-size:.9rem;"><?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8') ?></span>
                            <p style="font-size:.8rem;color:var(--text-muted);margin:2px 0 0;">Previsualización en tiempo real activa</p>
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="saveTheme()">Guardar color</button>
                </div>
            </div>

            <!-- ── Contraseña ─────────────────────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Cambiar contraseña</h2>
                </div>
                <div class="card-body">
                    <div class="form-field" style="margin-bottom:1rem;">
                        <label class="field-label">Contraseña actual</label>
                        <input type="password" id="pwdCurrent" class="field-input" placeholder="••••••••">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">
                        <div class="form-field">
                            <label class="field-label">Nueva contraseña</label>
                            <input type="password" id="pwdNew" class="field-input" placeholder="Mínimo 8 caracteres">
                        </div>
                        <div class="form-field">
                            <label class="field-label">Confirmar contraseña</label>
                            <input type="password" id="pwdConfirm" class="field-input" placeholder="Repite la nueva">
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="savePassword()">Cambiar contraseña</button>
                </div>
            </div>

        </div><!-- /grid -->
    </main>
</div>

<script>
/**
 * Muestra un toast temporal (éxito o error).
 * @param {string} msg
 * @param {'success'|'error'} type
 */
function showToast(msg, type = 'success') {
    const el = document.getElementById('globalToast');
    el.textContent = msg;
    el.style.background = type === 'success' ? '#dcfce7' : '#fee2e2';
    el.style.border     = type === 'success' ? '1px solid #22c55e' : '1px solid #ef4444';
    el.style.color      = type === 'success' ? '#14532d' : '#991b1b';
    el.style.display    = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

/**
 * Wrapper genérico para peticiones JSON a los endpoints de perfil.
 * @param {string} url
 * @param {FormData|object} body
 * @param {boolean} isFormData
 */
async function apiPost(url, body, isFormData = false) {
    const opts = { method: 'POST' };
    if (isFormData) {
        opts.body = body;
    } else {
        opts.headers = { 'Content-Type': 'application/json' };
        opts.body    = JSON.stringify(body);
    }
    const res  = await fetch(url, opts);
    return res.json();
}

// ─── Información general ───────────────────────────────────────────────────
async function saveInfo() {
    try {
        const json = await apiPost('api/perfil_empresa_info.php', {
            name:          document.getElementById('infoName').value.trim(),
            phone:         document.getElementById('infoPhone').value.trim(),
            address:       document.getElementById('infoAddress').value.trim(),
            contact_email: document.getElementById('infoEmail').value.trim(),
        });
        json.success ? showToast('Información guardada.') : showToast(json.message, 'error');
    } catch { showToast('Error de conexión.', 'error'); }
}

// ─── Logo ──────────────────────────────────────────────────────────────────
function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('logoCurrentImg') || document.createElement('img');
        img.id     = 'logoCurrentImg';
        img.src    = e.target.result;
        img.alt    = 'Logo';
        img.style  = 'width:72px;height:72px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);';
        document.getElementById('logoCurrentWrap').innerHTML = '';
        document.getElementById('logoCurrentWrap').appendChild(img);
    };
    reader.readAsDataURL(input.files[0]);
}

async function uploadLogo() {
    const input = document.getElementById('logoInput');
    if (!input.files || !input.files[0]) { showToast('Selecciona un archivo primero.', 'error'); return; }

    const fd = new FormData();
    fd.append('logo', input.files[0]);

    try {
        const json = await apiPost('api/perfil_empresa_logo.php', fd, true);
        if (json.success) {
            showToast('Logo actualizado.');
            if (json.logo_path) {
                const img = document.createElement('img');
                img.id    = 'logoCurrentImg';
                img.src   = json.logo_path + '?t=' + Date.now();
                img.alt   = 'Logo';
                img.style = 'width:72px;height:72px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);';
                document.getElementById('logoCurrentWrap').innerHTML = '';
                document.getElementById('logoCurrentWrap').appendChild(img);
            }
        } else { showToast(json.message, 'error'); }
    } catch { showToast('Error de conexión.', 'error'); }
}

async function deleteLogo() {
    if (!confirm('¿Eliminar el logo actual?')) return;
    try {
        const json = await apiPost('api/perfil_empresa_logo.php', { action: 'delete' });
        if (json.success) {
            showToast('Logo eliminado.');
            document.getElementById('logoCurrentWrap').innerHTML =
                '<div style="width:72px;height:72px;background:var(--surface);border-radius:var(--radius-sm);border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-muted);"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>';
        } else { showToast(json.message, 'error'); }
    } catch { showToast('Error de conexión.', 'error'); }
}

// ─── Color de marca ────────────────────────────────────────────────────────
/**
 * Aplica el color en tiempo real usando la variable CSS --primary.
 * @param {string} color  Hex color (#rrggbb)
 */
function applyThemePreview(color) {
    document.getElementById('themeColorHex').textContent = color;
    document.documentElement.style.setProperty('--primary',     color);
    document.documentElement.style.setProperty('--accent',      color);
    // Versión oscura aproximada
    const dark = darkenHex(color);
    document.documentElement.style.setProperty('--primary-dark', dark);
    document.documentElement.style.setProperty('--accent-dark',  dark);
    document.documentElement.style.setProperty('--sidebar-active-border', color);
    document.documentElement.style.setProperty('--border-focus', color);
}

function darkenHex(hex) {
    const r = Math.max(0, Math.round(parseInt(hex.slice(1,3),16) * 0.82));
    const g = Math.max(0, Math.round(parseInt(hex.slice(3,5),16) * 0.82));
    const b = Math.max(0, Math.round(parseInt(hex.slice(5,7),16) * 0.82));
    return '#' + [r,g,b].map(v => v.toString(16).padStart(2,'0')).join('');
}

async function saveTheme() {
    const color = document.getElementById('themeColorPicker').value;
    try {
        const json = await apiPost('api/perfil_empresa_theme.php', { theme_color: color });
        json.success ? showToast('Color guardado.') : showToast(json.message, 'error');
    } catch { showToast('Error de conexión.', 'error'); }
}

// ─── Contraseña ────────────────────────────────────────────────────────────
async function savePassword() {
    const curr = document.getElementById('pwdCurrent').value;
    const nw   = document.getElementById('pwdNew').value;
    const conf = document.getElementById('pwdConfirm').value;

    if (!curr) { showToast('Introduce tu contraseña actual.', 'error'); return; }
    if (nw.length < 8) { showToast('La nueva contraseña debe tener al menos 8 caracteres.', 'error'); return; }
    if (nw !== conf)   { showToast('Las contraseñas no coinciden.', 'error'); return; }

    try {
        const json = await apiPost('api/perfil_empresa_password.php', {
            current_password: curr,
            new_password:     nw,
        });
        if (json.success) {
            showToast('Contraseña cambiada.');
            document.getElementById('pwdCurrent').value = '';
            document.getElementById('pwdNew').value     = '';
            document.getElementById('pwdConfirm').value = '';
        } else { showToast(json.message, 'error'); }
    } catch { showToast('Error de conexión.', 'error'); }
}
</script>
<script src="js/app.js"></script>
</body>
</html>
