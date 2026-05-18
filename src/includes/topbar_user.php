<?php
/**
 * Componente topbar: dropdown del usuario autenticado.
 *
 * Soporta sesiones locales (tabla usuarios) y multi-tenant (tabla employees).
 * Muestra banner de impersonación cuando el superadmin accede como empresa.
 *
 * Include en la sección <div class="topbar-right"> de cada página del panel.
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../includes/csrf.php';

// Resolución de datos de display — compatible con sesiones locales y multi-tenant
$_tbNombre  = htmlspecialchars(
    $_SESSION['user_name'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario',
    ENT_QUOTES, 'UTF-8'
);
$_tbRol      = $_SESSION['user_role'] ?? $_SESSION['rol'] ?? 'employee';
$_tbRolLabel = match ($_tbRol) {
    'superadmin' => 'Super Admin',
    'admin'      => 'Administrador',
    default      => 'Empleado',
};
$_tbInitials = mb_strtoupper(mb_substr(
    $_SESSION['user_name'] ?? $_SESSION['usuario_nombre'] ?? 'U', 0, 2
));

$_tbBizName  = htmlspecialchars($_SESSION['business_name'] ?? '', ENT_QUOTES, 'UTF-8');
$_tbBizPlan  = $_SESSION['business_plan'] ?? '';
$_tbImperson = Session::isImpersonating();

$_tbPlanMeta = match ($_tbBizPlan) {
    'pro'   => ['label' => 'Pro',   'color' => '#7c3aed'],
    'basic' => ['label' => 'Basic', 'color' => '#0284c7'],
    default => ['label' => 'Free',  'color' => '#64748b'],
};

$_tbCsrfReturn = $_tbImperson ? generateCsrfToken('sa_return') : '';
?>
<?php if ($_tbImperson): ?>
<style>
    :root { --ib-h: 2.5rem; }
    body                  { padding-top: var(--ib-h) !important; }
    .sidebar              { top: var(--ib-h) !important; height: calc(100vh - var(--ib-h)) !important; }
    .sidebar-overlay      { top: var(--ib-h) !important; }
    .topbar               { top: var(--ib-h) !important; }
</style>
<div id="impersonationBar" style="position:fixed;top:0;left:0;right:0;z-index:9999;
     height:2.5rem;box-sizing:border-box;
     background:#f97316;color:#fff;display:flex;align-items:center;
     justify-content:space-between;padding:0 1.25rem;font-size:.84rem;font-weight:600;
     box-shadow:0 2px 8px rgba(249,115,22,.35);">
    <span>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" style="vertical-align:-.2em;margin-right:.4em;">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>
        Estás viendo el panel como administrador de <strong>&nbsp;<?= $_tbBizName ?></strong>
    </span>
    <form method="POST" action="/superadmin/return.php" style="margin:0;">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_tbCsrfReturn, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit"
                style="background:rgba(0,0,0,.2);border:none;color:#fff;padding:.3rem .85rem;
                       border-radius:.4rem;cursor:pointer;font-size:.8rem;font-weight:600;
                       letter-spacing:.01em;transition:.1s;"
                onmouseover="this.style.background='rgba(0,0,0,.35)'"
                onmouseout="this.style.background='rgba(0,0,0,.2)'">
            ← Volver al panel superadmin
        </button>
    </form>
</div>
<?php endif; ?>

<div class="topbar-user" style="position:relative;">

    <!-- Avatar + nombre (activa el dropdown) -->
    <button
        type="button"
        id="userDropdownBtn"
        class="topbar-user-link"
        style="display:flex;align-items:center;gap:.6rem;background:none;border:none;cursor:pointer;color:inherit;padding:.25rem .5rem;border-radius:.5rem;"
        aria-haspopup="true"
        aria-expanded="false"
        onclick="toggleUserDropdown()"
    >
        <div class="user-avatar"><?= $_tbInitials ?></div>
        <div class="user-info">
            <span class="user-fullname"><?= $_tbNombre ?></span>
            <span class="user-role-label"><?= $_tbRolLabel ?></span>
        </div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="opacity:.6;">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>

    <!-- Dropdown panel -->
    <div
        id="userDropdownMenu"
        style="display:none;position:absolute;right:0;top:calc(100% + .5rem);min-width:230px;
               background:#fff;border:1px solid #e2e8f0;border-radius:10px;
               box-shadow:0 10px 30px rgba(0,0,0,.12);z-index:500;overflow:hidden;"
    >
        <!-- Información del usuario -->
        <div style="padding:.85rem 1rem;border-bottom:1px solid #f1f5f9;">
            <p style="margin:0;font-size:.82rem;font-weight:700;color:#0f172a;"><?= $_tbNombre ?></p>
            <p style="margin:.15rem 0 0;font-size:.75rem;color:#64748b;"><?= $_tbRolLabel ?></p>
            <?php if ($_tbBizName): ?>
            <div style="margin-top:.55rem;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
                <span style="font-size:.75rem;color:#334155;font-weight:600;"><?= $_tbBizName ?></span>
                <span style="font-size:.68rem;background:<?= $_tbPlanMeta['color'] ?>;color:#fff;
                             padding:.1rem .4rem;border-radius:.3rem;font-weight:700;letter-spacing:.03em;">
                    <?= $_tbPlanMeta['label'] ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Cambiar contraseña -->
        <button
            type="button"
            onclick="abrirModalPassword(); closeUserDropdown();"
            style="display:flex;align-items:center;gap:.6rem;width:100%;padding:.7rem 1rem;
                   background:none;border:none;cursor:pointer;font-size:.85rem;color:#334155;
                   text-align:left;transition:.1s;"
            onmouseover="this.style.background='#f8fafc'"
            onmouseout="this.style.background='none'"
        >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Cambiar contraseña
        </button>

        <!-- Separador -->
        <div style="height:1px;background:#f1f5f9;margin:.15rem 0;"></div>

        <?php if ($_tbImperson): ?>
        <!-- Volver al panel superadmin (modo impersonación) -->
        <form method="POST" action="/superadmin/return.php" style="margin:0;">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_tbCsrfReturn, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit"
                    onclick="closeUserDropdown()"
                    style="display:flex;align-items:center;gap:.6rem;width:100%;padding:.7rem 1rem;
                           background:none;border:none;cursor:pointer;font-size:.85rem;color:#ea580c;
                           text-align:left;transition:.1s;"
                    onmouseover="this.style.background='#fff7ed'"
                    onmouseout="this.style.background='none'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Volver al panel superadmin
            </button>
        </form>
        <?php else: ?>
        <!-- Iniciar sesión como otro usuario -->
        <a
            href="switch_user.php"
            onclick="closeUserDropdown()"
            style="display:flex;align-items:center;gap:.6rem;padding:.7rem 1rem;
                   font-size:.85rem;color:#334155;text-decoration:none;transition:.1s;"
            onmouseover="this.style.background='#f8fafc'"
            onmouseout="this.style.background='none'"
        >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Otro usuario
        </a>
        <?php endif; ?>

        <!-- Separador -->
        <div style="height:1px;background:#f1f5f9;margin:.15rem 0;"></div>

        <!-- Cerrar sesión -->
        <a
            href="logout.php"
            onclick="closeUserDropdown()"
            style="display:flex;align-items:center;gap:.6rem;padding:.7rem 1rem;
                   font-size:.85rem;color:#dc2626;text-decoration:none;transition:.1s;"
            onmouseover="this.style.background='#fef2f2'"
            onmouseout="this.style.background='none'"
        >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Cerrar sesión
        </a>
    </div>
</div>

<!-- ===== MODAL CAMBIAR CONTRASEÑA ===== -->
<div id="modalPasswordBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:2rem;width:100%;max-width:420px;box-shadow:0 25px 50px rgba(0,0,0,.4);">
        <h3 style="margin:0 0 1.25rem;font-size:1.1rem;color:#0f172a;">Cambiar contraseña</h3>

        <div id="pwdMsg" style="display:none;padding:.75rem 1rem;border-radius:.5rem;font-size:.85rem;margin-bottom:1rem;"></div>

        <form id="formCambiarPassword" onsubmit="submitCambiarPassword(event)">
            <div class="form-field" style="margin-bottom:.9rem;">
                <label class="field-label">Contraseña actual</label>
                <input type="password" id="pwdActual" name="password_actual" class="field-input" required>
            </div>
            <div class="form-field" style="margin-bottom:.9rem;">
                <label class="field-label">Nueva contraseña</label>
                <input type="password" id="pwdNueva" name="password_nueva" class="field-input" required minlength="8"
                       placeholder="Mínimo 8 caracteres">
            </div>
            <div class="form-field" style="margin-bottom:1.25rem;">
                <label class="field-label">Confirmar nueva contraseña</label>
                <input type="password" id="pwdConfirmar" name="password_confirmar" class="field-input" required>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalPassword()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnCambiarPwd">Cambiar</button>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Muestra u oculta el dropdown del usuario.
 */
function toggleUserDropdown() {
    const menu = document.getElementById('userDropdownMenu');
    const btn  = document.getElementById('userDropdownBtn');
    const open = menu.style.display === 'block';
    menu.style.display = open ? 'none' : 'block';
    btn.setAttribute('aria-expanded', String(!open));
}

function closeUserDropdown() {
    const menu = document.getElementById('userDropdownMenu');
    if (menu) menu.style.display = 'none';
}

// Cerrar al hacer clic fuera
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('userDropdownBtn')?.closest('.topbar-user');
    if (wrapper && !wrapper.contains(e.target)) closeUserDropdown();
});

/**
 * Abre el modal de cambio de contraseña.
 */
function abrirModalPassword() {
    document.getElementById('formCambiarPassword').reset();
    document.getElementById('pwdMsg').style.display = 'none';
    const modal = document.getElementById('modalPasswordBackdrop');
    modal.style.display = 'flex';
}

function cerrarModalPassword() {
    document.getElementById('modalPasswordBackdrop').style.display = 'none';
}

// Cerrar modal al hacer clic fuera del cuadro
document.getElementById('modalPasswordBackdrop')?.addEventListener('click', function(e) {
    if (e.target === this) cerrarModalPassword();
});

/**
 * Envía el formulario de cambio de contraseña via fetch.
 *
 * @param {SubmitEvent} e
 */
async function submitCambiarPassword(e) {
    e.preventDefault();

    const msgEl  = document.getElementById('pwdMsg');
    const btn    = document.getElementById('btnCambiarPwd');
    const actual  = document.getElementById('pwdActual').value;
    const nueva   = document.getElementById('pwdNueva').value;
    const confirm = document.getElementById('pwdConfirmar').value;

    msgEl.style.display = 'none';

    if (nueva !== confirm) {
        msgEl.textContent   = 'La nueva contraseña y su confirmación no coinciden.';
        msgEl.style.cssText += ';background:#fee2e2;color:#991b1b;display:block;';
        return;
    }

    btn.disabled     = true;
    btn.textContent  = 'Cambiando…';

    try {
        const res  = await fetch('api/change_password.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ password_actual: actual, password_nueva: nueva }),
        });
        const json = await res.json();

        if (json.success) {
            msgEl.textContent   = 'Contraseña cambiada correctamente.';
            msgEl.style.cssText = 'background:#d1fae5;color:#065f46;padding:.75rem 1rem;border-radius:.5rem;font-size:.85rem;margin-bottom:1rem;display:block;';
            document.getElementById('formCambiarPassword').reset();
            setTimeout(cerrarModalPassword, 1800);
        } else {
            msgEl.textContent   = json.message ?? 'Error al cambiar la contraseña.';
            msgEl.style.cssText = 'background:#fee2e2;color:#991b1b;padding:.75rem 1rem;border-radius:.5rem;font-size:.85rem;margin-bottom:1rem;display:block;';
        }
    } catch {
        msgEl.textContent   = 'Error de red. Inténtalo de nuevo.';
        msgEl.style.cssText = 'background:#fee2e2;color:#991b1b;padding:.75rem 1rem;border-radius:.5rem;font-size:.85rem;margin-bottom:1rem;display:block;';
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Cambiar';
    }
}
</script>
