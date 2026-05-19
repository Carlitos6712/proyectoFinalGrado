<?php
/**
 * Registro público de empresas.
 *
 * Habilitado solo cuando registration_open = '1' en system_settings.
 * Crea el negocio, su empleado admin, siembra datos demo y envía bienvenida.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirigir solo si hay sesión de empresa activa (no bloquear a superadmin)
if (!empty($_SESSION['business_id']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/core/Settings.php';
require_once __DIR__ . '/core/Mailer.php';
require_once __DIR__ . '/core/NotificationService.php';

$registrationOpen = Settings::get('registration_open', '0') === '1';
$isSuperadmin     = !empty($_SESSION['rol']) && $_SESSION['rol'] === 'superadmin';

// ── Manejador AJAX POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$registrationOpen) {
        echo json_encode(['success' => false, 'message' => 'El registro está temporalmente cerrado.']);
        exit;
    }

    try {
        $pdo = Database::getInstance();

        $nombre       = trim($_POST['business_name'] ?? '');
        $slug         = trim($_POST['slug'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $adminName    = trim($_POST['admin_name'] ?? '');
        $password     = $_POST['password'] ?? '';
        $passwordConf = $_POST['password_confirm'] ?? '';
        $terms        = !empty($_POST['terms_accept']);

        if (!$nombre) {
            throw new AppException('El nombre de la empresa es obligatorio.', 400);
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new AppException('El slug solo puede contener letras minúsculas, números y guiones.', 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AppException('El email no es válido.', 400);
        }
        if (!$adminName) {
            throw new AppException('El nombre del administrador es obligatorio.', 400);
        }
        if (strlen($password) < 8) {
            throw new AppException('La contraseña debe tener al menos 8 caracteres.', 400);
        }
        if ($password !== $passwordConf) {
            throw new AppException('Las contraseñas no coinciden.', 400);
        }
        if (!$terms) {
            throw new AppException('Debes aceptar los términos y condiciones.', 400);
        }

        // Verificar unicidad del slug
        $chkSlug = $pdo->prepare('SELECT COUNT(*) FROM businesses WHERE slug = ?');
        $chkSlug->execute([$slug]);
        if ((int)$chkSlug->fetchColumn() > 0) {
            throw new AppException('El slug ya está en uso. Elige otro.', 409);
        }

        // Verificar unicidad del email en employees
        $chkEmail = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE email = ?');
        $chkEmail->execute([$email]);
        if ((int)$chkEmail->fetchColumn() > 0) {
            throw new AppException('Ya existe una cuenta con ese email.', 409);
        }

        $defaultPlan = Settings::get('default_plan', 'free');
        $trialDays   = (int)Settings::get('trial_days', '14');
        $expiresAt   = date('Y-m-d', strtotime("+{$trialDays} days"));

        // Crear negocio
        $stmtBiz = $pdo->prepare(
            'INSERT INTO businesses (name, slug, contact_email, plan, plan_expires_at, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmtBiz->execute([$nombre, $slug, $email, $defaultPlan, $expiresAt]);
        $businessId = (int)$pdo->lastInsertId();

        // Crear empleado admin
        $stmtEmp = $pdo->prepare(
            'INSERT INTO employees (business_id, name, email, password, role, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmtEmp->execute([
            $businessId,
            $adminName,
            $email,
            password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'admin',
        ]);

        // Enviar email de bienvenida
        try {
            $subject = '¡Bienvenido a es21plus! Tu empresa está lista';
            $body    = "
<h2>¡Bienvenido, {$adminName}!</h2>
<p>Tu empresa <strong>" . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "</strong> ha sido creada correctamente en es21plus.</p>
<h3>Tus credenciales de acceso</h3>
<ul>
  <li><strong>Empresa:</strong> " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "</li>
  <li><strong>Email:</strong> " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</li>
</ul>
<p>Tienes <strong>{$trialDays} días de prueba</strong> gratuita con acceso completo.</p>
<p><a href='login.php' style='background:#4F46E5;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;margin-top:10px;'>Acceder al panel</a></p>
<p style='margin-top:1.5rem;color:#64748b;font-size:.9rem;'>Equipo es21plus</p>";
            Mailer::send($email, $subject, $body);
        } catch (\Throwable) {
            // Email falla en silencio — no bloquea el registro
        }

        // Notificar al superadmin
        NotificationService::notify(
            'new_business',
            'Nuevo registro público: ' . $nombre,
            "La empresa \"{$nombre}\" se registró con plan {$defaultPlan}.",
            '/superadmin/businesses.php?action=show&id=' . $businessId
        );

        echo json_encode(['success' => true, 'business_id' => $businessId]);
    } catch (AppException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error interno. Inténtalo de nuevo.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        body.reg-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .reg-wrapper { width: 100%; max-width: 520px; }
        .reg-header { text-align: center; margin-bottom: 2rem; }
        .reg-logo { display: inline-flex; align-items: center; gap: .75rem; color: #fff; font-size: 1.5rem; font-weight: 700; margin-bottom: .5rem; }
        .reg-logo svg { color: #6366f1; }
        .reg-subtitle { color: #94a3b8; font-size: .9rem; }
        .reg-card { background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 25px 50px rgba(0,0,0,.4); }
        .reg-card h2 { font-size: 1.4rem; color: #0f172a; margin-bottom: .25rem; }
        .reg-card p.desc { color: #64748b; font-size: .9rem; margin-bottom: 1.75rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .slug-wrap { position: relative; }
        .slug-status { font-size: .78rem; margin-top: .35rem; }
        .slug-status.ok  { color: #22c55e; }
        .slug-status.err { color: #ef4444; }
        .slug-status.chk { color: #64748b; }
        .step-error { background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; border-radius: 8px; padding: 12px 16px; margin-bottom: 1.25rem; font-size: .9rem; display: none; }
        .closed-box { text-align: center; padding: 3rem 2rem; }
        .closed-box h2 { font-size: 1.3rem; margin-bottom: .75rem; color: #0f172a; }
        .closed-box p { color: #64748b; }
        .reg-footer { text-align: center; margin-top: 1.5rem; color: #94a3b8; font-size: .8rem; }
        @media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="reg-page">
<div class="reg-wrapper">
    <div class="reg-header">
        <div class="reg-logo">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
            </svg>
            es21<strong>plus</strong>
        </div>
        <p class="reg-subtitle">Sistema de Inventario para Mecánico de Motos</p>
    </div>

    <div class="reg-card">
        <?php if (!$registrationOpen): ?>
        <div class="closed-box">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5" style="margin-bottom:1rem;">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <h2>Registro temporalmente cerrado</h2>
            <p>El registro de nuevas empresas está cerrado en este momento. Contacta con nosotros para solicitar acceso.</p>
            <a href="landing.php" style="display:inline-block;margin-top:1.5rem;color:#6366f1;text-decoration:none;">&larr; Volver al inicio</a>
        </div>
        <?php else: ?>
        <h2>Crear cuenta</h2>
        <p class="desc">Registra tu empresa y empieza con <?= (int)Settings::get('trial_days', '14') ?> días de prueba gratuita.</p>

        <div id="regError" class="step-error"></div>

        <form id="regForm" autocomplete="off" novalidate>
            <div class="form-row" style="margin-bottom:1rem;">
                <div class="form-field">
                    <label class="field-label">Nombre de la empresa *</label>
                    <input type="text" name="business_name" id="fName" class="field-input" required
                           placeholder="Mi Taller S.L.">
                </div>
                <div class="form-field">
                    <label class="field-label">Slug (URL) *</label>
                    <div class="slug-wrap">
                        <input type="text" name="slug" id="fSlug" class="field-input" required
                               placeholder="mi-taller">
                        <div id="slugStatus" class="slug-status chk"></div>
                    </div>
                </div>
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label">Email de contacto *</label>
                <input type="email" name="email" id="fEmail" class="field-input" required
                       placeholder="admin@mitaller.com">
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label">Tu nombre (administrador) *</label>
                <input type="text" name="admin_name" id="fAdmin" class="field-input" required
                       placeholder="Juan García">
            </div>

            <div class="form-row" style="margin-bottom:1rem;">
                <div class="form-field">
                    <label class="field-label">Contraseña *</label>
                    <input type="password" name="password" id="fPwd" class="field-input" required
                           placeholder="Mínimo 8 caracteres" minlength="8">
                </div>
                <div class="form-field">
                    <label class="field-label">Confirmar contraseña *</label>
                    <input type="password" name="password_confirm" id="fPwdC" class="field-input" required
                           placeholder="Repite la contraseña">
                </div>
            </div>

            <div class="form-field" style="margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:.6rem;">
                <input type="checkbox" name="terms_accept" id="fTerms" value="1" style="margin-top:3px;accent-color:#6366f1;">
                <label for="fTerms" style="font-size:.88rem;color:#475569;cursor:pointer;">
                    Acepto los <a href="landing.php" style="color:#6366f1;">términos y condiciones</a> del servicio.
                </label>
            </div>

            <button type="submit" id="btnReg" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
                Crear empresa
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="reg-footer">
        ¿Ya tienes cuenta? <a href="login.php" style="color:#6366f1;">Inicia sesión</a>
        &nbsp;&middot;&nbsp;
        &copy; <?= date('Y') ?> es21plus · Carlos Vico
    </div>
</div>

<?php if ($registrationOpen): ?>
<script>
/**
 * Gestión del formulario de registro público.
 *
 * Auto-genera slug, valida disponibilidad en tiempo real,
 * envía formulario vía fetch y redirige al login.
 */
const fName    = document.getElementById('fName');
const fSlug    = document.getElementById('fSlug');
const slugStat = document.getElementById('slugStatus');
const regForm  = document.getElementById('regForm');
const regError = document.getElementById('regError');
const btnReg   = document.getElementById('btnReg');

let slugValid  = false;
let slugTimer  = null;

/**
 * Convierte un nombre en slug: minúsculas, guiones, sin especiales.
 * @param {string} name
 * @returns {string}
 */
function toSlug(name) {
    return name.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .trim().replace(/[\s]+/g, '-')
        .replace(/-+/g, '-');
}

fName.addEventListener('input', () => {
    if (!fSlug.dataset.edited) {
        fSlug.value = toSlug(fName.value);
        checkSlug();
    }
});

fSlug.addEventListener('input', () => {
    fSlug.dataset.edited = '1';
    fSlug.value = toSlug(fSlug.value);
    clearTimeout(slugTimer);
    slugTimer = setTimeout(checkSlug, 400);
});

/**
 * Verifica disponibilidad del slug via API.
 */
async function checkSlug() {
    const slug = fSlug.value.trim();
    if (!slug) {
        slugStat.className = 'slug-status chk';
        slugStat.textContent = '';
        slugValid = false;
        return;
    }
    slugStat.className = 'slug-status chk';
    slugStat.textContent = 'Comprobando…';
    try {
        const res  = await fetch(`api/check_slug.php?slug=${encodeURIComponent(slug)}`);
        const json = await res.json();
        if (json.available) {
            slugStat.className = 'slug-status ok';
            slugStat.textContent = '✓ Disponible';
            slugValid = true;
        } else {
            slugStat.className = 'slug-status err';
            slugStat.textContent = '✗ No disponible — elige otro';
            slugValid = false;
        }
    } catch {
        slugStat.className = 'slug-status chk';
        slugStat.textContent = 'No se pudo comprobar';
        slugValid = true; // El servidor lo validará igualmente
    }
}

regForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    regError.style.display = 'none';

    const pwd  = document.getElementById('fPwd').value;
    const pwdC = document.getElementById('fPwdC').value;

    if (pwd.length < 8) {
        showError('La contraseña debe tener al menos 8 caracteres.');
        return;
    }
    if (pwd !== pwdC) {
        showError('Las contraseñas no coinciden.');
        return;
    }
    if (!slugValid) {
        showError('El slug no está disponible o no ha sido validado.');
        return;
    }
    if (!document.getElementById('fTerms').checked) {
        showError('Debes aceptar los términos y condiciones.');
        return;
    }

    btnReg.disabled = true;
    btnReg.textContent = 'Creando empresa…';

    try {
        const data = new FormData(regForm);
        const res  = await fetch('registro.php', { method: 'POST', body: data });
        const json = await res.json();

        if (json.success) {
            <?php if ($isSuperadmin): ?>
            // Superadmin: ir directamente a la ficha del negocio creado
            window.location.href = 'superadmin/businesses.php?action=show&id=' + json.business_id;
            <?php else: ?>
            window.location.href = 'login.php?registered=1';
            <?php endif; ?>
        } else {
            showError(json.message || 'Error al crear la empresa.');
        }
    } catch {
        showError('Error de conexión. Inténtalo de nuevo.');
    } finally {
        btnReg.disabled = false;
        btnReg.textContent = 'Crear empresa';
    }
});

/**
 * Muestra un error inline en el formulario.
 * @param {string} msg
 */
function showError(msg) {
    regError.textContent = msg;
    regError.style.display = 'block';
    regError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Comprobar slug al cargar si ya tiene valor
if (fSlug.value) checkSlug();
</script>
<?php endif; ?>
</body>
</html>
