<?php
/**
 * Gestión de kits / packs de productos — vista de administración.
 *
 * Permite crear, editar, activar/desactivar y consultar kits con sus líneas.
 * Solo accesible para usuarios con rol 'admin'.
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
require_once __DIR__ . '/includes/Kit.php';
require_once __DIR__ . '/includes/Producto.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$error    = '';
$kits     = [];
$productos = [];

try {
    $kitModel      = new Kit();
    $productoModel = new Producto();
    $kits          = $kitModel->listar(false);          // todos (activos + inactivos)
    $productos     = $productoModel->listar();          // para selector en el modal
} catch (AppException $e) {
    $error = $e->getMessage();
} catch (\Throwable $e) {
    $error = 'Error inesperado: ' . $e->getMessage();
}

// Contar stock bajo para la topbar
$stockBajoCount = 0;
try {
    $stockBajoCount = count((new Producto())->filtrarStockBajo());
} catch (\Throwable) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kits – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .kit-lineas-preview { font-size:.82rem; color:#64748b; }
        .linea-row { display:flex; gap:8px; align-items:center; margin-bottom:6px; }
        .linea-row select { flex:1; }
        .linea-row input[type=number] { width:70px; }
        .btn-add-linea { font-size:.8rem; }
        .badge-activo   { background:#dcfce7; color:#166534; padding:2px 8px; border-radius:999px; font-size:.78rem; }
        .badge-inactivo { background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:999px; font-size:.78rem; }
    </style>
</head>
<body class="layout">

<?php require_once __DIR__ . '/includes/_sidebar.php'; ?>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ===== MAIN WRAPPER ===== -->
<div class="main-wrapper">
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
                <span class="breadcrumb-item active">Kits</span>
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

    <main class="content">

        <?php if ($flashSuccess): ?>
        <div class="toast toast-success" data-autodismiss="4000">
            <span class="toast-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span>
            <div class="toast-content"><span class="toast-title">Éxito</span><span class="toast-message"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span></div>
            <button class="toast-close" aria-label="Cerrar">×</button>
            <div class="toast-progress"></div>
        </div>
        <?php endif; ?>

        <?php if ($flashError || $error): ?>
        <div class="toast toast-error" data-autodismiss="6000">
            <span class="toast-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
            <div class="toast-content"><span class="toast-title">Error</span><span class="toast-message"><?= htmlspecialchars($flashError ?: $error, ENT_QUOTES, 'UTF-8') ?></span></div>
            <button class="toast-close" aria-label="Cerrar">×</button>
            <div class="toast-progress"></div>
        </div>
        <?php endif; ?>

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Kits / Packs de Productos</h1>
                <p class="page-subtitle">Agrupa productos recurrentes para añadirlos en bloque a un movimiento de salida.</p>
            </div>
            <div class="page-actions">
                <button class="btn-primary" onclick="abrirModalKit()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nuevo Kit
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="data-table-wrapper">
            <?php if (empty($kits)): ?>
            <div class="td-empty" style="padding:2.5rem;text-align:center;">
                No hay kits definidos aún. Crea el primero con el botón "Nuevo Kit".
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Líneas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($kits as $kit): ?>
                    <tr id="kit-row-<?= (int)$kit['id'] ?>">
                        <td><span class="ref-code"><?= (int)$kit['id'] ?></span></td>
                        <td><strong><?= htmlspecialchars($kit['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td class="kit-lineas-preview"><?= htmlspecialchars(mb_substr($kit['descripcion'] ?? '–', 0, 80), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="ref-code"><?= (int)$kit['total_lineas'] ?></span></td>
                        <td>
                            <?php if ($kit['activo']): ?>
                                <span class="badge-activo">Activo</span>
                            <?php else: ?>
                                <span class="badge-inactivo">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button class="btn-ghost" style="padding:4px 10px;font-size:.8rem;"
                                        onclick="editarKit(<?= (int)$kit['id'] ?>)">Editar</button>
                                <button class="btn-ghost" style="padding:4px 10px;font-size:.8rem;"
                                        onclick="toggleKit(<?= (int)$kit['id'] ?>, <?= $kit['activo'] ? 1 : 0 ?>)">
                                    <?= $kit['activo'] ? 'Desactivar' : 'Activar' ?>
                                </button>
                                <button class="btn-ghost" style="padding:4px 10px;font-size:.8rem;"
                                        onclick="verLineas(<?= (int)$kit['id'] ?>, '<?= htmlspecialchars(addslashes($kit['nombre']), ENT_QUOTES, 'UTF-8') ?>')">Ver líneas</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- ===== MODAL CREAR / EDITAR KIT ===== -->
<div id="modal-kit" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px;width:min(680px,96vw);max-height:90vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.25);">
        <h2 id="modal-kit-title" style="margin:0 0 18px;font-size:1.1rem;">Nuevo Kit</h2>

        <div class="form-field" style="margin-bottom:12px;">
            <label class="field-label">Nombre <span class="field-required">*</span></label>
            <input id="kit-nombre" class="field-input" type="text" maxlength="150" placeholder="Ej: Kit Revisión Básica">
        </div>
        <div class="form-field" style="margin-bottom:18px;">
            <label class="field-label">Descripción</label>
            <textarea id="kit-descripcion" class="field-input field-textarea" rows="2" placeholder="Descripción opcional del kit"></textarea>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <strong style="font-size:.9rem;">Líneas del kit</strong>
            <button type="button" class="btn-ghost btn-add-linea" onclick="agregarLinea()">+ Añadir producto</button>
        </div>
        <div id="lineas-container" style="margin-bottom:18px;"></div>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="btn-ghost" onclick="cerrarModalKit()">Cancelar</button>
            <button class="btn-primary" onclick="guardarKit()">Guardar kit</button>
        </div>
    </div>
</div>

<!-- ===== MODAL VER LÍNEAS ===== -->
<div id="modal-lineas" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px;width:min(560px,96vw);max-height:80vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.25);">
        <h2 id="modal-lineas-title" style="margin:0 0 16px;font-size:1.1rem;">Líneas del kit</h2>
        <div id="modal-lineas-body"></div>
        <div style="text-align:right;margin-top:16px;">
            <button class="btn-ghost" onclick="cerrarModalLineas()">Cerrar</button>
        </div>
    </div>
</div>

<!-- Datos PHP → JS -->
<script>
const PRODUCTOS_DISPONIBLES = <?= json_encode(
    array_map(fn($p) => [
        'id'     => (int)$p['id'],
        'nombre' => $p['nombre'],
        'stock'  => (int)$p['stock'],
    ], $productos),
    JSON_UNESCAPED_UNICODE
) ?>;

let kitEditandoId = null;

/** Abre el modal para crear un nuevo kit. */
function abrirModalKit() {
    kitEditandoId = null;
    document.getElementById('modal-kit-title').textContent = 'Nuevo Kit';
    document.getElementById('kit-nombre').value      = '';
    document.getElementById('kit-descripcion').value = '';
    document.getElementById('lineas-container').innerHTML = '';
    agregarLinea();
    document.getElementById('modal-kit').style.display = 'flex';
}

/** Cierra el modal de kit. */
function cerrarModalKit() {
    document.getElementById('modal-kit').style.display = 'none';
}

/** Carga datos del kit y abre el modal en modo edición. */
async function editarKit(id) {
    try {
        const res  = await fetch(`api/kits.php?id=${id}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        const kit  = json.data;

        kitEditandoId = id;
        document.getElementById('modal-kit-title').textContent = 'Editar Kit';
        document.getElementById('kit-nombre').value      = kit.nombre;
        document.getElementById('kit-descripcion').value = kit.descripcion ?? '';
        document.getElementById('lineas-container').innerHTML = '';

        (kit.lineas ?? []).forEach(l => agregarLinea(l.producto_id, l.cantidad));
        if ((kit.lineas ?? []).length === 0) agregarLinea();

        document.getElementById('modal-kit').style.display = 'flex';
    } catch (err) {
        alert('Error cargando kit: ' + err.message);
    }
}

/** Añade una fila de línea al formulario del modal. */
function agregarLinea(productoId = null, cantidad = 1) {
    const container = document.getElementById('lineas-container');
    const row       = document.createElement('div');
    row.className   = 'linea-row';

    const select = document.createElement('select');
    select.className = 'field-input field-select';
    select.innerHTML = '<option value="">— Seleccionar producto —</option>';
    PRODUCTOS_DISPONIBLES.forEach(p => {
        const opt   = document.createElement('option');
        opt.value   = p.id;
        opt.dataset.stock = p.stock;
        opt.textContent   = `${p.nombre} (stock: ${p.stock})`;
        if (p.id === productoId) opt.selected = true;
        select.appendChild(opt);
    });

    const inputCant = document.createElement('input');
    inputCant.type  = 'number';
    inputCant.min   = '1';
    inputCant.value = cantidad;
    inputCant.className = 'field-input';
    inputCant.style.width = '80px';

    const btnDel = document.createElement('button');
    btnDel.type  = 'button';
    btnDel.textContent = '✕';
    btnDel.className   = 'btn-ghost';
    btnDel.style.cssText = 'padding:4px 8px;color:#ef4444;';
    btnDel.onclick = () => row.remove();

    row.appendChild(select);
    row.appendChild(inputCant);
    row.appendChild(btnDel);
    container.appendChild(row);
}

/** Recoge las líneas del formulario. */
function recogerLineas() {
    const rows   = document.querySelectorAll('#lineas-container .linea-row');
    const lineas = [];
    let ok = true;
    rows.forEach(row => {
        const sel  = row.querySelector('select');
        const cant = row.querySelector('input[type=number]');
        if (!sel.value) return;
        const prodId  = parseInt(sel.value);
        const cantVal = parseInt(cant.value);
        if (cantVal <= 0) { ok = false; return; }
        lineas.push({ producto_id: prodId, cantidad: cantVal });
    });
    if (!ok) { alert('Las cantidades deben ser mayores a 0.'); return null; }
    return lineas;
}

/** Envía la petición para crear o actualizar el kit. */
async function guardarKit() {
    const nombre      = document.getElementById('kit-nombre').value.trim();
    const descripcion = document.getElementById('kit-descripcion').value.trim();
    if (!nombre) { alert('El nombre del kit es obligatorio.'); return; }

    const lineas = recogerLineas();
    if (lineas === null) return;

    const body   = { nombre, descripcion, lineas };
    const isEdit = kitEditandoId !== null;
    const url    = isEdit ? `api/kits.php?id=${kitEditandoId}` : 'api/kits.php';
    const method = isEdit ? 'PUT' : 'POST';

    try {
        const res  = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        cerrarModalKit();
        window.location.reload();
    } catch (err) {
        alert('Error al guardar: ' + err.message);
    }
}

/** Activa o desactiva un kit. */
async function toggleKit(id, estadoActual) {
    const accion = estadoActual ? 'desactivar' : 'activar';
    if (!confirm(`¿Seguro que quieres ${accion} este kit?`)) return;
    try {
        const res  = await fetch(`api/kits.php?id=${id}&toggle`, { method: 'PATCH' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        window.location.reload();
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

/** Muestra las líneas de un kit en el modal. */
async function verLineas(id, nombre) {
    document.getElementById('modal-lineas-title').textContent = `Líneas del kit: ${nombre}`;
    document.getElementById('modal-lineas-body').innerHTML    = 'Cargando…';
    document.getElementById('modal-lineas').style.display     = 'flex';
    try {
        const res  = await fetch(`api/kits.php?id=${id}&lineas`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        const lineas = json.data;
        if (!lineas.length) {
            document.getElementById('modal-lineas-body').innerHTML = '<em>Este kit no tiene líneas.</em>';
            return;
        }
        let html = '<table class="data-table"><thead><tr><th>Producto</th><th>Ref</th><th>Cantidad</th><th>Stock actual</th></tr></thead><tbody>';
        lineas.forEach(l => {
            const alerta = l.stock_actual < l.cantidad
                ? ' <span style="color:#ef4444;font-size:.78rem;">⚠ stock insuficiente</span>'
                : '';
            html += `<tr>
                <td>${escHtml(l.producto_nombre)}</td>
                <td><span class="ref-code">${escHtml(l.codigo_ref ?? '–')}</span></td>
                <td><strong>${l.cantidad}</strong></td>
                <td>${l.stock_actual}${alerta}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        document.getElementById('modal-lineas-body').innerHTML = html;
    } catch (err) {
        document.getElementById('modal-lineas-body').innerHTML = `<span style="color:#ef4444">Error: ${err.message}</span>`;
    }
}

function cerrarModalLineas() {
    document.getElementById('modal-lineas').style.display = 'none';
}

/** Escapa HTML para prevenir XSS. */
function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<script src="js/app.js"></script>
</body>
</html>
