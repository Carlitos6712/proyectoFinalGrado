<?php
/**
 * Página de perfil del usuario autenticado.
 *
 * Permite a cualquier usuario logueado:
 *   - Ver y editar su nombre completo y email.
 *   - Cambiar su contraseña (con verificación de la contraseña actual).
 *
 * Accesible para todos los roles (admin y operario).
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auditoria.php';
require_once __DIR__ . '/includes/Usuario.php';
require_once __DIR__ . '/includes/Producto.php';

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$error    = '';
$stockBajoCount = 0;

try { $stockBajoCount = count((new Producto())->filtrarStockBajo()); } catch (\Throwable $e) {}

$userId    = (int) ($_SESSION['usuario_id'] ?? 0);
$model     = new Usuario();
$auditoria = Auditoria::instancia();

// Cargar datos actuales del usuario
try {
    $usuario = $model->obtenerPorId($userId);
} catch (AppException $e) {
    // Sesión inconsistente — redirigir al login
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Procesamiento del formulario ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accionPost = $_POST['accion'] ?? '';

    if ($accionPost === 'perfil') {
        // ── Actualizar nombre y email ─────────────────────────────────────────
        try {
            $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
            $email          = trim($_POST['email']           ?? '');

            if ($nombreCompleto === '') {
                throw new AppException('El nombre completo es obligatorio.', 400);
            }

            $model->actualizarPerfil($userId, $nombreCompleto, $email, $auditoria);

            // Actualizar nombre en sesión para que el topbar refleje el cambio
            $_SESSION['usuario_nombre'] = $nombreCompleto;

            $_SESSION['flash_success'] = 'Perfil actualizado correctamente.';
            header('Location: perfil.php');
            exit;

        } catch (AppException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = 'Error inesperado al actualizar el perfil.';
        }

    } elseif ($accionPost === 'password') {
        // ── Cambiar contraseña ────────────────────────────────────────────────
        try {
            $passwordActual   = $_POST['password_actual']    ?? '';
            $passwordNueva    = $_POST['password_nueva']     ?? '';
            $passwordConfirma = $_POST['password_confirmar'] ?? '';

            if ($passwordNueva !== $passwordConfirma) {
                throw new AppException('La nueva contraseña y la confirmación no coinciden.', 400);
            }

            $model->cambiarPassword($userId, $passwordActual, $passwordNueva, $auditoria);

            $_SESSION['flash_success'] = 'Contraseña cambiada correctamente.';
            header('Location: perfil.php');
            exit;

        } catch (AppException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = 'Error inesperado al cambiar la contraseña.';
        }
    }

    // Recargar datos actuales tras un intento fallido
    try { $usuario = $model->obtenerPorId($userId); } catch (\Throwable $e) {}
}

// Iniciales para el avatar
$nombreSesion = $_SESSION['usuario_nombre'] ?? 'Usuario';
$iniciales    = mb_strtoupper(mb_substr($nombreSesion, 0, 2));
$rolSesion    = $_SESSION['rol'] ?? 'operario';
$rolLabel     = $rolSesion === 'admin' ? 'Administrador' : 'Operario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        /* ── Estilos específicos del perfil ── */
        .perfil-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 768px) {
            .perfil-grid { grid-template-columns: 1fr; }
        }
        .perfil-avatar-grande {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; font-weight: 700; color: #fff;
            margin-bottom: 1rem;
        }
        .perfil-meta { color: #64748b; font-size: .85rem; margin-bottom: 1.5rem; }
        .perfil-meta strong { color: #1e293b; }
        .field-hint { font-size: .78rem; color: #64748b; margin-top: .25rem; }
        .password-strength { height: 4px; border-radius: 2px; margin-top: .4rem; background: #e5e7eb; }
        .password-strength-bar { height: 100%; border-radius: 2px; transition: width .3s, background .3s; width: 0; }
    </style>
</head>
<body class="layout">

<?php require_once __DIR__ . '/includes/_sidebar.php'; ?>

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
                <span class="breadcrumb-item active">Mi perfil</span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php if ($stockBajoCount > 0): ?>
            <a href="productos.php" class="topbar-alert" title="<?= $stockBajoCount ?> producto(s) con stock bajo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="notif-badge"><?= $stockBajoCount ?></span>
            </a>
            <?php endif; ?>
            <?php require_once __DIR__ . '/includes/topbar_user.php'; ?>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="content">

        <?php if ($flashSuccess): ?>
        <div class="toast toast-success" data-autodismiss="4000">
            <span class="toast-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </span>
            <div class="toast-content">
                <span class="toast-title">Éxito</span>
                <span class="toast-message"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button class="toast-close" aria-label="Cerrar">×</button>
            <div class="toast-progress"></div>
        </div>
        <?php endif; ?>

        <?php if ($error || $flashError): ?>
        <div class="toast toast-error" data-autodismiss="6000">
            <span class="toast-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </span>
            <div class="toast-content">
                <span class="toast-title">Error</span>
                <span class="toast-message"><?= htmlspecialchars($error ?: $flashError, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button class="toast-close" aria-label="Cerrar">×</button>
            <div class="toast-progress"></div>
        </div>
        <?php endif; ?>

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Mi perfil</h1>
                <p class="page-subtitle">Gestiona tus datos personales y cambia tu contraseña</p>
            </div>
        </div>

        <div class="perfil-grid">

            <!-- ── Formulario: datos de perfil ──────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Datos personales</h2>
                    <p class="card-subtitle">Nombre completo y dirección de correo</p>
                </div>
                <div class="card-body">
                    <div class="perfil-avatar-grande"><?= htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="perfil-meta">
                        <strong><?= htmlspecialchars($usuario['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                        &nbsp;·&nbsp;
                        <?= htmlspecialchars(ucfirst($usuario['rol']), ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;·&nbsp;
                        Miembro desde <?= htmlspecialchars(date('d/m/Y', strtotime($usuario['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <form method="POST" novalidate>
                        <input type="hidden" name="accion" value="perfil">

                        <div class="form-field" style="margin-bottom:1rem;">
                            <label class="field-label" for="nombre_completo">
                                Nombre completo <span class="field-required">*</span>
                            </label>
                            <input class="field-input" type="text" id="nombre_completo" name="nombre_completo"
                                   required maxlength="100"
                                   value="<?= htmlspecialchars($_POST['nombre_completo'] ?? $usuario['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="Tu nombre completo">
                        </div>

                        <div class="form-field" style="margin-bottom:1.5rem;">
                            <label class="field-label" for="email">Email</label>
                            <input class="field-input" type="email" id="email" name="email"
                                   maxlength="150"
                                   value="<?= htmlspecialchars($_POST['email'] ?? ($usuario['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="tu@correo.com">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Guardar cambios
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── Formulario: cambio de contraseña ──────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Cambiar contraseña</h2>
                    <p class="card-subtitle">Mínimo 8 caracteres. Verifica tu contraseña actual.</p>
                </div>
                <div class="card-body">
                    <form method="POST" id="formPassword" novalidate>
                        <input type="hidden" name="accion" value="password">

                        <div class="form-field" style="margin-bottom:1rem;">
                            <label class="field-label" for="password_actual">
                                Contraseña actual <span class="field-required">*</span>
                            </label>
                            <input class="field-input" type="password" id="password_actual"
                                   name="password_actual" required autocomplete="current-password"
                                   placeholder="Tu contraseña actual">
                        </div>

                        <div class="form-field" style="margin-bottom:.5rem;">
                            <label class="field-label" for="password_nueva">
                                Nueva contraseña <span class="field-required">*</span>
                            </label>
                            <input class="field-input" type="password" id="password_nueva"
                                   name="password_nueva" required minlength="8"
                                   autocomplete="new-password"
                                   placeholder="Mínimo 8 caracteres"
                                   oninput="actualizarFortaleza(this.value)">
                            <div class="password-strength" aria-hidden="true">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <p class="field-hint" id="strengthHint">Escribe la nueva contraseña</p>
                        </div>

                        <div class="form-field" style="margin-bottom:1.5rem;">
                            <label class="field-label" for="password_confirmar">
                                Confirmar nueva contraseña <span class="field-required">*</span>
                            </label>
                            <input class="field-input" type="password" id="password_confirmar"
                                   name="password_confirmar" required minlength="8"
                                   autocomplete="new-password"
                                   placeholder="Repite la nueva contraseña">
                            <p class="field-hint" id="confirmHint" style="display:none;color:#ef4444;">
                                Las contraseñas no coinciden
                            </p>
                        </div>

                        <button type="submit" class="btn btn-primary" id="btnCambiarPass">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            Cambiar contraseña
                        </button>
                    </form>
                </div>
            </div>

        </div><!-- /.perfil-grid -->

    </main>
</div><!-- /.main-wrapper -->

<script>
/**
 * Calcula la fortaleza de la contraseña y actualiza la barra visual.
 *
 * @param {string} val Valor del campo contraseña.
 */
function actualizarFortaleza(val) {
    const bar   = document.getElementById('strengthBar');
    const hint  = document.getElementById('strengthHint');
    let score   = 0;

    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const niveles = [
        { pct: 0,   color: '#e5e7eb', label: 'Escribe la nueva contraseña' },
        { pct: 20,  color: '#ef4444', label: 'Muy débil' },
        { pct: 40,  color: '#f97316', label: 'Débil' },
        { pct: 60,  color: '#eab308', label: 'Aceptable' },
        { pct: 80,  color: '#22c55e', label: 'Fuerte' },
        { pct: 100, color: '#16a34a', label: 'Muy fuerte' },
    ];

    const nivel = val.length === 0 ? niveles[0] : niveles[Math.min(score, 5)];
    bar.style.width     = nivel.pct + '%';
    bar.style.background = nivel.color;
    hint.textContent    = nivel.label;
}

/**
 * Valida que las dos contraseñas coincidan antes de enviar el formulario.
 */
document.getElementById('formPassword').addEventListener('submit', function(e) {
    const nueva    = document.getElementById('password_nueva').value;
    const confirma = document.getElementById('password_confirmar').value;
    const hint     = document.getElementById('confirmHint');

    if (nueva !== confirma) {
        e.preventDefault();
        hint.style.display = 'block';
        document.getElementById('password_confirmar').focus();
    } else {
        hint.style.display = 'none';
    }
});

document.getElementById('password_confirmar').addEventListener('input', function() {
    const nueva    = document.getElementById('password_nueva').value;
    const hint     = document.getElementById('confirmHint');
    hint.style.display = (this.value && this.value !== nueva) ? 'block' : 'none';
});

// ── Sidebar toggle ────────────────────────────────────────────────────────────
document.getElementById('menuToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
});
document.getElementById('sidebarClose')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
});
document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
});
</script>

</body>
</html>
