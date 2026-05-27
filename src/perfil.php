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

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <svg class="logo-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
            </svg>
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
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="productos.php" class="nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['productos.php','nuevo_producto.php','editar_producto.php','eliminar_producto.php']) ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                </span>
                <span class="nav-label">Productos</span>
            </a>
            <a href="categorias.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
                    </svg>
                </span>
                <span class="nav-label">Categorías</span>
            </a>
            <a href="marcas.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'marcas.php' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>
                    </svg>
                </span>
                <span class="nav-label">Marcas</span>
            </a>
            <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
            <a href="modelos_moto.php"
               class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'modelos_moto.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="18" r="3"/><path d="M6 18H4a2 2 0 0 1-2-2v-5l2-5h13l2 5v7h-3M14 18H8"/></svg></span>
                <span class="nav-label">Modelos de Moto</span>
            </a>
            <a href="proveedores.php"
               class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'proveedores.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></span>
                <span class="nav-label">Proveedores</span>
            </a>
            <a href="pedidos.php"
               class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
                <span class="nav-label">Pedidos</span>
            </a>

            <?php endif; ?>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Operaciones</span>
            <a href="movimientos.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'movimientos.php' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                    </svg>
                </span>
                <span class="nav-label">Movimientos</span>
            </a>
            <a href="kits.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'kits.php' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                </span>
                <span class="nav-label">Kits</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Administración</span>
            <a href="auditoria.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'auditoria.php' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg>
                </span>
                <span class="nav-label">Auditoría</span>
            </a>
            <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
            <a href="usuarios.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'usuarios.php' ? 'active' : '' ?>">
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="nav-label">Usuarios</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar-sm"><?= htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sidebar-user-info">
                <span class="user-name-sm"><?= htmlspecialchars($nombreSesion, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="user-role"><?= htmlspecialchars($rolLabel, ENT_QUOTES, 'UTF-8') ?></span>
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
