<?php
/**
 * Gestión de empleados del negocio.
 *
 * Accesible solo para administradores de empresa (role = admin).
 * Permite listar, crear, editar y activar/desactivar empleados
 * del negocio autenticado en la sesión.
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/middleware/RoleMiddleware.php';
require_once __DIR__ . '/core/Session.php';

RoleMiddleware::requireAdmin();

$businessId   = Session::getBusinessId();
$businessName = htmlspecialchars($_SESSION['business_name'] ?? 'Mi empresa', ENT_QUOTES, 'UTF-8');

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$empleados = [];
$error     = '';

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare(
        'SELECT id, name, email, role, is_active, last_login, created_at
         FROM employees WHERE business_id = ? ORDER BY name ASC'
    );
    $stmt->execute([$businessId]);
    $empleados = $stmt->fetchAll();
} catch (\Throwable $e) {
    $error = 'Error al cargar los empleados.';
}

$sessionUserId = (int)($_SESSION['user_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados – <?= $businessName ?></title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .badge-admin    { background:#ede9fe; color:#5b21b6; }
        .badge-employee { background:#dbeafe; color:#1e40af; }
        .badge-activo   { background:#d1fae5; color:#065f46; }
        .badge-inactivo { background:#fee2e2; color:#991b1b; }
        .badge {
            display:inline-block; padding:.2em .6em;
            border-radius:9999px; font-size:.72rem; font-weight:700;
            text-transform:uppercase; letter-spacing:.03em;
        }
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
            width:100%; max-width:480px; max-height:90vh; overflow-y:auto;
            box-shadow:0 25px 50px rgba(0,0,0,.4);
        }
        .modal-box h3 { margin:0 0 1.25rem; font-size:1.15rem; }
        .modal-actions { display:flex; gap:.75rem; justify-content:flex-end; margin-top:1.5rem; }
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
            <h1 class="topbar-title">Empleados</h1>
        </div>
        <div class="topbar-actions">
            <button class="btn btn-primary" id="btnNuevoEmpleado">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nuevo empleado
            </button>
        </div>
        <div class="topbar-right">
            <?php require_once __DIR__ . '/includes/topbar_user.php'; ?>
        </div>
    </header>

    <main class="main-content">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div id="alertMsg" style="display:none;" class="alert"></div>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Empleados de <?= $businessName ?></h2>
                <span class="badge" style="background:#f1f5f9;color:#475569;">
                    <?= count($empleados) ?> empleado<?= count($empleados) !== 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($empleados)): ?>
                    <p style="padding:2rem;text-align:center;color:#64748b;">No hay empleados registrados.</p>
                <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="data-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Último acceso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <?php
                    $adminsActivos = count(array_filter($empleados,
                        fn($e) => $e['role'] === 'admin' && (int)$e['is_active'] === 1
                    ));
                    ?>
                    <tbody id="tablaEmpleados">
                    <?php foreach ($empleados as $emp): ?>
                        <?php
                        $esYo   = (int)$emp['id'] === $sessionUserId;
                        $activo = (bool)(int)$emp['is_active'];
                        $ultimo = $emp['last_login']
                            ? date('d/m/Y H:i', strtotime($emp['last_login']))
                            : '—';
                        ?>
                        <tr id="row-<?= (int)$emp['id'] ?>">
                            <td style="color:#94a3b8;font-size:.82rem;"><?= (int)$emp['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($emp['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if ($esYo): ?>
                                    <span class="badge" style="background:#fef3c7;color:#92400e;margin-left:.4rem;">Tú</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#64748b;font-size:.88rem;"><?= htmlspecialchars($emp['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge badge-<?= $emp['role'] ?>"><?= $emp['role'] === 'admin' ? 'Admin' : 'Empleado' ?></span></td>
                            <td><span class="badge badge-<?= $activo ? 'activo' : 'inactivo' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
                            <td style="font-size:.85rem;color:#64748b;"><?= $ultimo ?></td>
                            <td>
                                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                    <button class="btn-icon" style="background:#e0e7ff;color:#3730a3;"
                                        onclick="abrirEditar(<?= htmlspecialchars(json_encode([
                                            'id'   => $emp['id'],
                                            'name' => $emp['name'],
                                            'email'=> $emp['email'],
                                            'role' => $emp['role'],
                                        ]), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        Editar
                                    </button>
                                    <button class="btn-icon"
                                        style="background:<?= $activo ? '#fee2e2' : '#d1fae5' ?>;color:<?= $activo ? '#991b1b' : '#065f46' ?>;"
                                        onclick="toggleEmpleado(<?= (int)$emp['id'] ?>, <?= $activo ? 'true' : 'false' ?>, this)"
                                        <?php if ($esYo): ?>
                                            disabled title="No puedes desactivarte a ti mismo"
                                        <?php elseif ($emp['role'] === 'admin' && $activo && $adminsActivos <= 1): ?>
                                            disabled title="No se puede desactivar al único administrador activo"
                                        <?php endif; ?>>
                                        <?php if ($activo): ?>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                            Desactivar
                                        <?php else: ?>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            Activar
                                        <?php endif; ?>
                                    </button>
                                    <?php if (!$esYo): ?>
                                    <button class="btn-icon" style="background:#fef3c7;color:#92400e;"
                                        onclick="resetPassword(<?= (int)$emp['id'] ?>, '<?= htmlspecialchars($emp['name'], ENT_QUOTES, 'UTF-8') ?>')">
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
</div>

<!-- ===== MODAL CREAR / EDITAR EMPLEADO ===== -->
<div class="modal-backdrop" id="modalEmpleado" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
    <div class="modal-box">
        <h3 id="modalTitulo">Nuevo empleado</h3>

        <form id="formEmpleado" novalidate>
            <input type="hidden" id="empleadoId" value="">

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label" for="inputNombre">Nombre <span style="color:#ef4444;">*</span></label>
                <input type="text" id="inputNombre" class="field-input" required maxlength="100"
                       placeholder="ej. María García">
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label" for="inputEmail">Email <span style="color:#ef4444;">*</span></label>
                <input type="email" id="inputEmail" class="field-input" required maxlength="150"
                       placeholder="ej. maria@taller.com">
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label" for="inputRol">Rol <span style="color:#ef4444;">*</span></label>
                <select id="inputRol" class="field-input">
                    <option value="employee">Empleado</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>

            <div id="campoPassword" class="form-field" style="margin-bottom:1rem;">
                <label class="field-label" for="inputPassword">
                    Contraseña <span style="color:#ef4444;">*</span>
                </label>
                <input type="password" id="inputPassword" class="field-input" minlength="8"
                       placeholder="Mínimo 8 caracteres">
                <p style="font-size:.78rem;color:#64748b;margin-top:.25rem;">Mínimo 8 caracteres.</p>
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
let modoEdicion = false;

/**
 * Muestra alerta global en página.
 * @param {string} msg
 * @param {'success'|'danger'} tipo
 */
function mostrarAlerta(msg, tipo) {
    const el = document.getElementById('alertMsg');
    el.className = 'alert alert-' + tipo;
    el.textContent = msg;
    el.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => { el.style.display = 'none'; }, 5000);
}

document.getElementById('btnNuevoEmpleado').addEventListener('click', () => {
    modoEdicion = false;
    document.getElementById('modalTitulo').textContent = 'Nuevo empleado';
    document.getElementById('formEmpleado').reset();
    document.getElementById('empleadoId').value = '';
    document.getElementById('campoPassword').style.display = 'block';
    document.getElementById('inputPassword').required = true;
    document.getElementById('formError').style.display = 'none';
    document.getElementById('modalEmpleado').classList.add('open');
    document.getElementById('inputNombre').focus();
});

/**
 * Abre modal en modo edición con datos precargados.
 * @param {Object} emp
 */
function abrirEditar(emp) {
    modoEdicion = true;
    document.getElementById('modalTitulo').textContent = 'Editar empleado';
    document.getElementById('empleadoId').value  = emp.id;
    document.getElementById('inputNombre').value = emp.name;
    document.getElementById('inputEmail').value  = emp.email;
    document.getElementById('inputRol').value    = emp.role;
    document.getElementById('campoPassword').style.display = 'none';
    document.getElementById('inputPassword').required = false;
    document.getElementById('inputPassword').value    = '';
    document.getElementById('formError').style.display = 'none';
    document.getElementById('modalEmpleado').classList.add('open');
    document.getElementById('inputNombre').focus();
}

function cerrarModal() {
    document.getElementById('modalEmpleado').classList.remove('open');
    document.getElementById('formEmpleado').reset();
}

document.getElementById('modalEmpleado').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

document.getElementById('formEmpleado').addEventListener('submit', async function(e) {
    e.preventDefault();

    const errorEl = document.getElementById('formError');
    const btn     = document.getElementById('btnGuardar');
    errorEl.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    const id    = document.getElementById('empleadoId').value;
    const body  = {
        name:     document.getElementById('inputNombre').value.trim(),
        email:    document.getElementById('inputEmail').value.trim(),
        role:     document.getElementById('inputRol').value,
    };
    if (!modoEdicion) body.password = document.getElementById('inputPassword').value;

    const url    = modoEdicion ? `api/empleados.php?id=${id}` : 'api/empleados.php';
    const method = modoEdicion ? 'PUT' : 'POST';

    try {
        const res  = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const json = await res.json();

        if (!json.success) {
            errorEl.textContent   = json.message;
            errorEl.style.display = 'block';
        } else {
            cerrarModal();
            mostrarAlerta(
                modoEdicion ? 'Empleado actualizado correctamente.' : 'Empleado creado correctamente.',
                'success'
            );
            setTimeout(() => location.reload(), 800);
        }
    } catch {
        errorEl.textContent   = 'Error de red. Inténtalo de nuevo.';
        errorEl.style.display = 'block';
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Guardar';
    }
});

/**
 * Activa o desactiva un empleado.
 * @param {number} id
 * @param {boolean} activo
 * @param {HTMLElement} btn
 */
async function toggleEmpleado(id, activo, btn) {
    if (!confirm(`¿${activo ? 'Desactivar' : 'Activar'} este empleado?`)) return;
    btn.disabled = true;
    try {
        const res  = await fetch(`api/empleados.php?id=${id}&accion=toggle`, { method: 'PATCH' });
        const json = await res.json();
        if (!json.success) {
            mostrarAlerta(json.message, 'danger');
        } else {
            mostrarAlerta(json.message, 'success');
            setTimeout(() => location.reload(), 600);
        }
    } catch {
        mostrarAlerta('Error de red. Inténtalo de nuevo.', 'danger');
    } finally {
        btn.disabled = false;
    }
}

/**
 * Resetea contraseña de un empleado y muestra la nueva.
 * @param {number} id
 * @param {string} name
 */
async function resetPassword(id, name) {
    if (!confirm(`¿Resetear la contraseña de "${name}"? Se generará una contraseña aleatoria.`)) return;
    try {
        const res  = await fetch(`api/empleados.php?id=${id}&accion=reset-password`, { method: 'PATCH' });
        const json = await res.json();
        if (!json.success) {
            mostrarAlerta(json.message, 'danger');
        } else {
            const pwd = json.data?.password ?? '';
            alert(`Nueva contraseña temporal para "${name}":\n\n${pwd}\n\nCópiala ahora — no se volverá a mostrar.`);
            mostrarAlerta('Contraseña reseteada correctamente.', 'success');
        }
    } catch {
        mostrarAlerta('Error de red. Inténtalo de nuevo.', 'danger');
    }
}

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
