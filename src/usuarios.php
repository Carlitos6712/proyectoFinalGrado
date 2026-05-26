<?php
/**
 * Panel de gestión de usuarios del sistema.
 *
 * Acceso restringido a administradores. Permite listar, crear, editar
 * y activar/desactivar usuarios. La contraseña nunca se muestra ni
 * se edita desde este panel (existe un endpoint de cambio de contraseña aparte).
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Usuario.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/middleware/RoleMiddleware.php';
require_once __DIR__ . '/includes/csrf.php';

// Solo administradores pueden gestionar usuarios
RoleMiddleware::requireAdmin();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$usuarios = [];
$error    = '';

try {
    $model    = new Usuario();
    $usuarios = $model->listarSinSuperadmin();
} catch (\Throwable $e) {
    $error = 'Error al cargar los usuarios: ' . $e->getMessage();
}

// Iniciales del usuario de sesión para el avatar — compatible con sesiones locales y multi-tenant
$nombreSesion = $_SESSION['user_name'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario';
$iniciales    = mb_strtoupper(mb_substr($nombreSesion, 0, 2)) ?: 'U';

// Token CSRF para las operaciones AJAX del formulario de usuarios
$csrfTokenUsuarios = generateCsrfToken('gestionar_usuarios');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        /* ── Estilos específicos del panel de usuarios ── */
        .badge-superadmin { background:#fef3c7; color:#92400e; }
        .badge-admin      { background:#ede9fe; color:#5b21b6; }
        .badge-employee   { background:#dbeafe; color:#1e40af; }
        .badge-operario   { background:#dbeafe; color:#1e40af; }
        .badge-activo     { background:#d1fae5; color:#065f46; }
        .badge-inactivo   { background:#fee2e2; color:#991b1b; }
        .badge {
            display:inline-block; padding:.2em .6em;
            border-radius:9999px; font-size:.72rem; font-weight:700;
            text-transform:uppercase; letter-spacing:.03em;
        }
        .user-table th { white-space: nowrap; }
        .user-table td { vertical-align: middle; }
        .btn-icon {
            display:inline-flex; align-items:center; gap:.35rem;
            padding:.3rem .65rem; border-radius:.375rem; font-size:.8rem;
            font-weight:600; border:none; cursor:pointer; transition:.15s;
        }
        .btn-icon:disabled { opacity:.45; cursor:not-allowed; }
        .modal-backdrop {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,.5); z-index:1000;
            align-items:center; justify-content:center;
        }
        .modal-backdrop.open { display:flex; }
        .modal-box {
            background:#fff; border-radius:12px; padding:2rem;
            width:100%; max-width:520px; max-height:90vh; overflow-y:auto;
            box-shadow:0 25px 50px rgba(0,0,0,.4);
        }
        .modal-box h3 { margin:0 0 1.25rem; font-size:1.15rem; }
        .modal-actions { display:flex; gap:.75rem; justify-content:flex-end; margin-top:1.5rem; }
        .field-hint { font-size:.78rem; color:#64748b; margin-top:.25rem; }
    </style>
</head>
<body class="layout">

<?php require_once __DIR__ . '/includes/_sidebar.php'; ?>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="main-wrapper">
    <header class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <h1 class="topbar-title">Gestión de usuarios</h1>
        </div>
        <div class="topbar-actions">
            <button class="btn btn-primary" id="btnNuevoUsuario">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nuevo usuario
            </button>
        </div>
        <div class="topbar-right">
            <?php require_once __DIR__ . '/includes/topbar_user.php'; ?>
        </div>
    </header>

    <main class="main-content">

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($flashSuccess !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($flashError !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <!-- Alerta JS (respuesta AJAX) -->
        <div id="alertMsg" style="display:none;" class="alert"></div>

        <!-- ── Tabla de usuarios ─────────────────────────────────────── -->
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Usuarios del sistema</h2>
                <span class="badge" style="background:#f1f5f9;color:#475569;">
                    <?= count($usuarios) ?> usuario<?= count($usuarios) !== 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($usuarios)): ?>
                    <p style="padding:2rem;text-align:center;color:#64748b;">No hay usuarios registrados.</p>
                <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="data-table user-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Usuario</th>
                            <th>Nombre completo</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Último acceso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <?php
                    $adminsActivosTotales = count(array_filter($usuarios, fn($x) => $x['rol'] === 'admin' && (int)$x['activo'] === 1));
                    $sessionUserId        = (int)($_SESSION['usuario_id'] ?? 0);
                    ?>
                    <tbody id="tablaUsuarios">
                    <?php foreach ($usuarios as $u): ?>
                        <?php
                            $esYo         = (int)$u['id'] === $sessionUserId;
                            $esAdmin      = $u['rol'] === 'admin';
                            $activo       = (bool)(int)$u['activo'];
                            $ultimoAcceso = $u['last_login']
                                ? date('d/m/Y H:i', strtotime($u['last_login']))
                                : '—';
                        ?>
                        <tr id="row-<?= (int)$u['id'] ?>">
                            <td style="color:#94a3b8;font-size:.82rem;"><?= (int)$u['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if ($esYo): ?>
                                    <span class="badge" style="background:#fef3c7;color:#92400e;margin-left:.4rem;">Tú</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="color:#64748b;font-size:.88rem;">
                                <?= $u['email'] !== '' ? htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') : '—' ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $u['rol'] ?>">
                                    <?= htmlspecialchars($u['rol'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $activo ? 'activo' : 'inactivo' ?>">
                                    <?= $activo ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td style="font-size:.85rem;color:#64748b;"><?= $ultimoAcceso ?></td>
                            <td>
                                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                    <button
                                        class="btn-icon"
                                        style="background:#e0e7ff;color:#3730a3;"
                                        onclick="abrirEditar(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)"
                                        title="Editar usuario">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        Editar
                                    </button>
                                    <button
                                        class="btn-icon"
                                        style="background:<?= $activo ? '#fee2e2' : '#d1fae5' ?>;color:<?= $activo ? '#991b1b' : '#065f46' ?>;"
                                        onclick="toggleUsuario(<?= (int)$u['id'] ?>, <?= $activo ? 'true' : 'false' ?>, this)"
                                        <?php if ($esYo): ?>
                                            disabled title="No puedes desactivarte a ti mismo"
                                        <?php elseif ($esAdmin && $activo && $adminsActivosTotales <= 1): ?>
                                            disabled title="No se puede desactivar al único administrador activo"
                                        <?php endif; ?>
                                    >
                                        <?php if ($activo): ?>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                            Desactivar
                                        <?php else: ?>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            Activar
                                        <?php endif; ?>
                                    </button>
                                    <?php if (!$esYo): ?>
                                    <button
                                        class="btn-icon"
                                        style="background:#fef3c7;color:#92400e;"
                                        onclick="resetPassword(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>')"
                                        title="Resetear contraseña">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                        Reset pwd
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </section>

    </main>
</div><!-- /.main-wrapper -->

<!-- ===== MODAL CREAR / EDITAR USUARIO ===== -->
<div class="modal-backdrop" id="modalUsuario" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
    <div class="modal-box">
        <h3 id="modalTitulo">Nuevo usuario</h3>

        <form id="formUsuario" novalidate>
            <input type="hidden" id="usuarioId" name="id" value="">
            <input type="hidden" id="csrfTokenUsuarios" name="csrf_token"
                   value="<?= htmlspecialchars($csrfTokenUsuarios, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label" for="inputUsername">Username <span style="color:#ef4444;">*</span></label>
                <input type="text" id="inputUsername" name="username" class="field-input"
                       required maxlength="50" placeholder="ej. jlopez">
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label" for="inputNombre">Nombre completo <span style="color:#ef4444;">*</span></label>
                <input type="text" id="inputNombre" name="nombre_completo" class="field-input"
                       required maxlength="100" placeholder="ej. Juan López García">
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label" for="inputEmail">Email</label>
                <input type="email" id="inputEmail" name="email" class="field-input"
                       maxlength="150" placeholder="ej. juan@es21plus.local">
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label" for="inputRol">Rol <span style="color:#ef4444;">*</span></label>
                <select id="inputRol" name="rol" class="field-input">
                    <option value="employee">Empleado</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>

            <!-- Contraseña solo para crear -->
            <div id="campoPassword" class="form-field" style="margin-bottom:1rem;">
                <label class="field-label" for="inputPassword">
                    Contraseña <span style="color:#ef4444;">*</span>
                </label>
                <input type="password" id="inputPassword" name="password" class="field-input"
                       minlength="8" placeholder="Mínimo 8 caracteres">
                <p class="field-hint" id="hintPassword">Mínimo 8 caracteres.</p>
            </div>

            <div id="formError" class="alert alert-danger" style="display:none;margin-top:.5rem;"></div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
/** Referencia al modelo de usuario que está siendo editado actualmente. */
let modoEdicion = false;

/**
 * Muestra un mensaje de alerta global en la página.
 *
 * @param {string}  msg     Texto del mensaje.
 * @param {'success'|'danger'} tipo Tipo visual.
 */
function mostrarAlerta(msg, tipo) {
    const el = document.getElementById('alertMsg');
    el.className = 'alert alert-' + tipo;
    el.textContent = msg;
    el.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => { el.style.display = 'none'; }, 5000);
}

/**
 * Abre el modal en modo "Nuevo usuario".
 */
document.getElementById('btnNuevoUsuario').addEventListener('click', () => {
    modoEdicion = false;
    document.getElementById('modalTitulo').textContent = 'Nuevo usuario';
    document.getElementById('formUsuario').reset();
    document.getElementById('usuarioId').value = '';
    document.getElementById('campoPassword').style.display = 'block';
    document.getElementById('inputPassword').required = true;
    document.getElementById('hintPassword').textContent = 'Mínimo 8 caracteres.';
    document.getElementById('formError').style.display = 'none';
    document.getElementById('modalUsuario').classList.add('open');
    document.getElementById('inputUsername').focus();
});

/**
 * Abre el modal en modo "Editar usuario" con los datos precargados.
 *
 * @param {Object} usuario Objeto con los datos del usuario (sin password_hash).
 */
function abrirEditar(usuario) {
    modoEdicion = true;
    document.getElementById('modalTitulo').textContent = 'Editar usuario';
    document.getElementById('usuarioId').value         = usuario.id;
    document.getElementById('inputUsername').value     = usuario.username;
    document.getElementById('inputNombre').value       = usuario.nombre_completo;
    document.getElementById('inputEmail').value        = usuario.email ?? '';
    document.getElementById('inputRol').value          = usuario.rol;
    document.getElementById('campoPassword').style.display = 'none';
    document.getElementById('inputPassword').required  = false;
    document.getElementById('inputPassword').value     = '';
    document.getElementById('formError').style.display = 'none';
    document.getElementById('modalUsuario').classList.add('open');
    document.getElementById('inputUsername').focus();
}

/**
 * Cierra el modal y resetea el formulario.
 */
function cerrarModal() {
    document.getElementById('modalUsuario').classList.remove('open');
    document.getElementById('formUsuario').reset();
}

// Cerrar modal al hacer clic fuera del modal-box
document.getElementById('modalUsuario').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

/**
 * Envío del formulario de crear/editar usuario via fetch.
 */
document.getElementById('formUsuario').addEventListener('submit', async function(e) {
    e.preventDefault();

    const errorEl  = document.getElementById('formError');
    const btnGuard = document.getElementById('btnGuardar');
    errorEl.style.display = 'none';
    btnGuard.disabled = true;
    btnGuard.textContent = 'Guardando…';

    const id            = document.getElementById('usuarioId').value;
    const username      = document.getElementById('inputUsername').value.trim();
    const nombreCompleto = document.getElementById('inputNombre').value.trim();
    const email         = document.getElementById('inputEmail').value.trim();
    const rol           = document.getElementById('inputRol').value;
    const password      = document.getElementById('inputPassword').value;

    const csrfToken = document.getElementById('csrfTokenUsuarios').value;
    const body = { username, nombre_completo: nombreCompleto, email, rol, csrf_token: csrfToken };
    if (!modoEdicion) body.password = password;

    const url    = modoEdicion ? `api/usuarios.php?id=${id}` : 'api/usuarios.php';
    const method = modoEdicion ? 'PUT' : 'POST';

    try {
        const res  = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const json = await res.json();

        if (!json.success) {
            errorEl.textContent     = json.message;
            errorEl.style.display   = 'block';
        } else {
            cerrarModal();
            mostrarAlerta(
                modoEdicion ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.',
                'success'
            );
            setTimeout(() => location.reload(), 800);
        }
    } catch (err) {
        errorEl.textContent   = 'Error de red. Inténtalo de nuevo.';
        errorEl.style.display = 'block';
    } finally {
        btnGuard.disabled = false;
        btnGuard.textContent = 'Guardar';
    }
});

/**
 * Activa o desactiva un usuario.
 *
 * @param {number}  id      ID del usuario.
 * @param {boolean} activo  Estado actual (true = activo).
 * @param {HTMLElement} btn Botón que disparó la acción.
 */
async function toggleUsuario(id, activo, btn) {
    const accion = activo ? 'desactivar' : 'activar';
    if (!confirm(`¿Seguro que deseas ${accion} este usuario?`)) return;

    btn.disabled = true;

    const csrfToken = document.getElementById('csrfTokenUsuarios').value;
    try {
        const res  = await fetch(`api/usuarios.php?id=${id}&accion=toggle`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken }),
        });
        const json = await res.json();

        if (!json.success) {
            mostrarAlerta(json.message, 'danger');
        } else {
            mostrarAlerta(json.message, 'success');
            setTimeout(() => location.reload(), 600);
        }
    } catch (err) {
        mostrarAlerta('Error de red. Inténtalo de nuevo.', 'danger');
    } finally {
        btn.disabled = false;
    }
}

/**
 * Resetea la contraseña de un usuario y muestra la nueva contraseña.
 *
 * @param {number} id       ID del usuario.
 * @param {string} username Nombre de usuario para el mensaje de confirmación.
 */
async function resetPassword(id, username) {
    if (!confirm(`¿Resetear la contraseña de "${username}"? Se generará una contraseña aleatoria.`)) return;

    const csrfToken = document.getElementById('csrfTokenUsuarios').value;
    try {
        const res  = await fetch(`api/usuarios.php?id=${id}&accion=reset-password`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken }),
        });
        const json = await res.json();

        if (!json.success) {
            mostrarAlerta(json.message, 'danger');
        } else {
            const pwd = json.data?.password ?? '';
            alert(`Nueva contraseña temporal para "${username}":\n\n${pwd}\n\nCópiala ahora — no se volverá a mostrar.`);
            mostrarAlerta('Contraseña reseteada correctamente.', 'success');
        }
    } catch {
        mostrarAlerta('Error de red. Inténtalo de nuevo.', 'danger');
    }
}

// ── Sidebar toggle (igual que el resto de páginas) ────────────────────────────
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
