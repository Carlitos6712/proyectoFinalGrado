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
            <a href="index.php" class="nav-item"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span><span class="nav-label">Dashboard</span></a>
            <a href="productos.php" class="nav-item"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span><span class="nav-label">Productos</span></a>
            <a href="categorias.php" class="nav-item"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span><span class="nav-label">Categorías</span></a>
            <a href="movimientos.php" class="nav-item"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></span><span class="nav-label">Movimientos</span></a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Administración</span>
            <a href="perfil_empresa.php" class="nav-item active"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span><span class="nav-label">Perfil de empresa</span></a>
            <?php if ($stockBajoCount > 0): ?>
            <a href="index.php" class="nav-item">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg></span>
                <span class="nav-label">Stock bajo <span class="badge" style="background:var(--danger);color:#fff;border-radius:9999px;padding:1px 6px;font-size:.7rem;"><?= $stockBajoCount ?></span></span>
            </a>
            <?php endif; ?>
        </div>
        <div class="nav-section" style="margin-top:auto;">
            <a href="logout.php" class="nav-item"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span><span class="nav-label">Salir</span></a>
        </div>
    </nav>
</aside>

<div class="main-wrapper">
    <?php require_once __DIR__ . '/includes/topbar_user.php'; ?>

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
