<?php
/**
 * Página de login del sistema de inventario.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está logueado, redirigir al dashboard
if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Usuario.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/AuthController.php';
require_once __DIR__ . '/core/Settings.php';

$error       = '';
$redirect    = $_GET['redirect'] ?? 'index.php';
$expired     = isset($_GET['expired']);
$switched    = isset($_GET['switch']);
$deactivated = isset($_GET['deactivated']);
$registered  = isset($_GET['registered']);
$negocioInactivo = isset($_GET['error']) && $_GET['error'] === 'negocio_inactivo';

// Detección de dominio personalizado: pre-rellenar empresa y ocultarla
$prefilledBusiness = '';
$hideBusiness      = false;
try {
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($httpHost) {
        $pdo = Database::getInstance();
        $domStmt = $pdo->prepare("SELECT name FROM businesses WHERE custom_domain = ? AND is_active = 1 LIMIT 1");
        $domStmt->execute([$httpHost]);
        $domBiz = $domStmt->fetchColumn();
        if ($domBiz) {
            $prefilledBusiness = $domBiz;
            $hideBusiness      = true;
        }
    }
} catch (\Throwable) {}

// Modo login: 'business' cuando se rellena el campo empresa, 'local' en caso contrario
$loginMode  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business = trim($_POST['business'] ?? '');
    $loginMode = $business !== '' ? 'business' : 'local';

    try {
        $password = $_POST['password'] ?? '';
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($loginMode === 'business') {
            // ── Login de empleado de empresa (multi-tenant) ──────────────────
            $email = trim($_POST['email'] ?? '');
            $auth  = new AuthController();
            $auth->login($business, $email, $password);
            // Redirigir a onboarding si el admin aún no lo completó
            if (
                ($_SESSION['user_role'] ?? '') === 'admin' &&
                (int)($_SESSION['business_onboarding_completed'] ?? 0) === 0
            ) {
                header('Location: onboarding.php');
            } else {
                header('Location: index.php');
            }
            exit;

        } else {
            // ── Login local (tabla usuarios — admin / employee) ───────────────
            $username = trim($_POST['username'] ?? '');
            if ($username === '' || $password === '') {
                throw new AppException('Usuario y contraseña son obligatorios.', 400);
            }

            $usuarioModel = new Usuario();
            $usuarioModel->verificarRateLimit($ip);

            try {
                $usuario = $usuarioModel->verificarCredenciales($username, $password);
            } catch (AppException $e) {
                $usuarioModel->registrarIntentoFallido($ip);
                throw $e;
            }

            session_regenerate_id(true);
            $_SESSION['usuario_id']       = $usuario['id'];
            $_SESSION['usuario_nombre']   = $usuario['nombre_completo'];
            $_SESSION['usuario_username'] = $usuario['username'];
            $_SESSION['rol']              = $usuario['rol'] ?? 'employee';
            $_SESSION['usuario_activo']   = (int)($usuario['activo'] ?? 1);
            $_SESSION['last_activity']    = time();

            $usuarioModel->limpiarIntentos($ip);
            $usuarioModel->registrarLogin($usuario['id']);

            if ($usuario['rol'] === 'superadmin') {
                header('Location: superadmin/index.php');
                exit;
            }

            $safeRedirect = (preg_match('#^[a-zA-Z0-9_./-]+\.php#', $redirect) && !str_contains($redirect, '://'))
                ? $redirect
                : 'index.php';
            header('Location: ' . $safeRedirect);
            exit;
        }

    } catch (AppException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Error inesperado. Inténtalo de nuevo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        /* Estilos específicos de la página de login */
        body.login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            padding: 1rem;
        }
        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .login-logo svg {
            color: #6366f1;
        }
        .login-subtitle {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }
        .login-card h2 {
            font-size: 1.4rem;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }
        .login-card p.desc {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1.75rem;
        }
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #94a3b8;
            font-size: 0.8rem;
        }
        .expired-notice {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="login-page">
<div class="login-wrapper">
    <div class="login-header">
        <div class="login-logo">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
            </svg>
            es21<strong>plus</strong>
        </div>
        <p class="login-subtitle">Sistema de Inventario para Mecánico de Motos</p>
    </div>

    <div class="login-card">
        <h2>Iniciar sesión</h2>
        <p class="desc">Introduce tus credenciales para acceder al panel.</p>

        <?php if ($registered): ?>
        <div class="expired-notice" style="background:#dcfce7;border-color:#22c55e;color:#14532d;">
            Empresa creada correctamente. Ya puedes iniciar sesión.
        </div>
        <?php elseif ($expired): ?>
        <div class="expired-notice">Tu sesión ha expirado por inactividad. Por favor, inicia sesión de nuevo.</div>
        <?php elseif ($switched): ?>
        <div class="expired-notice" style="background:#e0f2fe;border-color:#0284c7;color:#075985;">
            Inicia sesión con otra cuenta.
        </div>
        <?php elseif ($deactivated): ?>
        <div class="expired-notice" style="background:#fee2e2;border-color:#ef4444;color:#991b1b;">
            Tu cuenta ha sido desactivada. Contacta con el administrador.
        </div>
        <?php elseif ($negocioInactivo): ?>
        <div class="expired-notice" style="background:#fee2e2;border-color:#ef4444;color:#991b1b;">
            Esta empresa ha sido desactivada. Contacta con soporte.
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-banner alert-banner-error" style="margin-bottom:1.25rem;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <!-- Formulario unificado: empresa (opcional) + selector empleado + contraseña -->
        <form method="POST" autocomplete="off" id="loginForm">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">

            <!-- Campo empresa -->
            <?php if ($hideBusiness): ?>
            <input type="hidden" name="business" value="<?= htmlspecialchars($prefilledBusiness, ENT_QUOTES, 'UTF-8') ?>">
            <?php else: ?>
            <div class="form-field" style="margin-bottom:1.25rem;">
                <label class="field-label">Empresa <span style="color:#94a3b8;font-weight:400;">(opcional — solo empleados de empresa)</span></label>
                <input type="text" name="business" id="fieldBusiness" class="field-input"
                       value="<?= htmlspecialchars($_POST['business'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Nombre o código de tu empresa"
                       autocomplete="off">
            </div>
            <?php endif; ?>

            <!-- Selector de empleado (modo empresa) -->
            <div class="form-field" style="margin-bottom:1.25rem;display:none;" id="wrapEmpleado">
                <label class="field-label">¿Quién eres?</label>
                <select id="fieldEmpleado" class="field-input" style="cursor:pointer;">
                    <option value="">— Selecciona tu nombre —</option>
                </select>
                <!-- email oculto que se envía al servidor -->
                <input type="hidden" name="email" id="fieldEmail"
                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <div id="bizStatus" style="font-size:.78rem;margin-top:.4rem;color:#64748b;"></div>
            </div>

            <!-- Username (modo local, sin empresa) -->
            <div class="form-field" style="margin-bottom:1.25rem;" id="wrapUsername">
                <label class="field-label">Usuario</label>
                <input type="text" name="username" id="fieldUsername" class="field-input"
                       value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Introduce tu usuario" autofocus>
            </div>

            <div class="form-field" style="margin-bottom:1.75rem;">
                <label class="field-label">Contraseña</label>
                <input type="password" name="password" class="field-input" required
                       placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
                Entrar al sistema
            </button>
        </form>
        <script>
        /**
         * Alterna entre modo empresa (selector empleado) y modo local (username).
         * Carga empleados del negocio por AJAX cuando hay texto en el campo empresa.
         * En modo dominio personalizado, se carga automáticamente la empresa pre-rellena.
         */
        let _bizDebounce = null;
        let _empleadoMap = {}; // id → email

        const fieldBusiness  = document.getElementById('fieldBusiness');
        const wrapEmpleado   = document.getElementById('wrapEmpleado');
        const wrapUsername   = document.getElementById('wrapUsername');
        const fieldEmpleado  = document.getElementById('fieldEmpleado');
        const fieldEmail     = document.getElementById('fieldEmail');
        const fieldUsername  = document.getElementById('fieldUsername');
        const bizStatus      = document.getElementById('bizStatus');

        if (fieldBusiness) fieldBusiness.addEventListener('input', () => {
            clearTimeout(_bizDebounce);
            const biz = fieldBusiness.value.trim();

            if (biz === '') {
                mostrarModoLocal();
                return;
            }

            // Debounce 400 ms antes de hacer fetch
            _bizDebounce = setTimeout(() => cargarEmpleados(biz), 400);
        });

        /**
         * Carga empleados activos de la empresa indicada.
         * @param {string} biz
         */
        async function cargarEmpleados(biz) {
            bizStatus.textContent = 'Buscando empresa…';
            bizStatus.style.color = '#64748b';
            wrapEmpleado.style.display = 'block';
            wrapUsername.style.display = 'none';
            fieldUsername.required = false;

            try {
                const res  = await fetch(`api/empleados_negocio.php?business=${encodeURIComponent(biz)}`);
                const json = await res.json();

                if (!json.success || !json.data.length) {
                    bizStatus.textContent = json.success ? 'No hay empleados activos en esta empresa.' : 'Empresa no encontrada.';
                    bizStatus.style.color = '#ef4444';
                    fieldEmpleado.innerHTML = '<option value="">— Sin empleados —</option>';
                    fieldEmail.value = '';
                    fieldEmpleado.required = false;
                    return;
                }

                bizStatus.textContent = `${json.data.length} empleado${json.data.length !== 1 ? 's' : ''} encontrado${json.data.length !== 1 ? 's' : ''}.`;
                bizStatus.style.color = '#22c55e';

                _empleadoMap = {};
                fieldEmpleado.innerHTML = '<option value="">— Selecciona tu nombre —</option>';
                json.data.forEach(emp => {
                    _empleadoMap[emp.id] = emp.email;
                    const opt  = document.createElement('option');
                    opt.value  = emp.id;
                    const role = emp.role === 'admin' ? ' (Admin)' : ' (Empleado)';
                    opt.textContent = emp.name + role;
                    fieldEmpleado.appendChild(opt);
                });
                fieldEmpleado.required = true;

                // Si solo hay un empleado, preseleccionar
                if (json.data.length === 1) {
                    fieldEmpleado.value = json.data[0].id;
                    fieldEmail.value    = json.data[0].email;
                }

            } catch {
                bizStatus.textContent = 'Error al conectar. Inténtalo de nuevo.';
                bizStatus.style.color = '#ef4444';
            }
        }

        /** Sincroniza el email oculto cuando se selecciona un empleado. */
        fieldEmpleado.addEventListener('change', () => {
            const id = fieldEmpleado.value;
            fieldEmail.value = id ? (_empleadoMap[id] ?? '') : '';
        });

        /** Activa el modo login local (sin empresa). */
        function mostrarModoLocal() {
            wrapEmpleado.style.display = 'none';
            wrapUsername.style.display = 'block';
            fieldUsername.required     = true;
            fieldEmpleado.required     = false;
            fieldEmail.value           = '';
            bizStatus.textContent      = '';
        }

        // Estado inicial: dominio personalizado → cargar empleados automáticamente
        <?php if ($hideBusiness): ?>
        (function() {
            const wrapEmpleado = document.getElementById('wrapEmpleado');
            const wrapUsername = document.getElementById('wrapUsername');
            if (wrapEmpleado) wrapEmpleado.style.display = 'block';
            if (wrapUsername) wrapUsername.style.display = 'none';
            cargarEmpleados(<?= json_encode($prefilledBusiness) ?>);
        })();
        <?php else: ?>
        if (fieldBusiness) {
            (function init() {
                const bizVal = fieldBusiness.value.trim();
                if (bizVal !== '') {
                    cargarEmpleados(bizVal);
                } else {
                    mostrarModoLocal();
                }
            })();
        }
        <?php endif; ?>
        </script>
    </div>

    <div class="login-footer">
        <a href="landing.php" style="color:#6366f1;text-decoration:none;">&larr; Volver al inicio</a>
        &nbsp;&middot;&nbsp;
        <?php if (Settings::get('registration_open', '0') === '1'): ?>
        <a href="registro.php" style="color:#6366f1;text-decoration:none;">Crear cuenta</a>
        &nbsp;&middot;&nbsp;
        <?php endif; ?>
        &copy; <?= date('Y') ?> es21plus · Carlos Vico
    </div>
</div>
</body>
</html>
