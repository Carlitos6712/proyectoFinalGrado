<?php
/**
 * Historial de auditoría de cambios en el inventario.
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
require_once __DIR__ . '/middleware/RoleMiddleware.php';

RoleMiddleware::requireAdmin();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$filtroTabla  = trim($_GET['tabla']       ?? '');
$filtroAccion = trim($_GET['accion']      ?? '');
$fechaDesde   = trim($_GET['fecha_desde'] ?? '');
$fechaHasta   = trim($_GET['fecha_hasta'] ?? '');
$pagina       = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina    = 25;

$registros    = [];
$total        = 0;
$totalPaginas = 1;
$error        = '';

try {
    $auditoria = new Auditoria(Database::getInstance());

    $filtroTablaVal  = $filtroTabla  !== '' ? $filtroTabla  : null;
    $filtroAccionVal = $filtroAccion !== '' ? $filtroAccion : null;
    $fechaDesdeVal   = $fechaDesde   !== '' ? $fechaDesde   : null;
    $fechaHastaVal   = $fechaHasta   !== '' ? $fechaHasta   : null;

    $total        = $auditoria->contar($filtroTablaVal, $filtroAccionVal, $fechaDesdeVal, $fechaHastaVal);
    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    $pagina       = min($pagina, $totalPaginas);
    $registros    = $auditoria->listar($filtroTablaVal, $filtroAccionVal, $fechaDesdeVal, $fechaHastaVal, $pagina, $porPagina);
} catch (\Throwable $e) {
    $error = 'Error al cargar la auditoría: ' . htmlspecialchars($e->getMessage());
}

/**
 * Devuelve los nombres de campos modificados como chips de código.
 *
 * @param string|null $anterior JSON anterior.
 * @param string|null $nuevo    JSON nuevo.
 * @return string HTML con los campos.
 * @author Carlitos6712
 */
function camposModificados(?string $anterior, ?string $nuevo): string
{
    $ant  = json_decode($anterior ?? '{}', true) ?? [];
    $nuev = json_decode($nuevo    ?? '{}', true) ?? [];
    $campos = array_unique(array_merge(array_keys($ant), array_keys($nuev)));
    if (empty($campos)) {
        return '<span style="color:#9ca3af;">—</span>';
    }
    return implode(' ', array_map(
        fn($k) => '<code class="field-chip">' . htmlspecialchars($k) . '</code>',
        $campos
    ));
}

function urlFiltros(array $overrides = []): string
{
    global $filtroTabla, $filtroAccion, $fechaDesde, $fechaHasta, $pagina;
    $params = array_filter(array_merge([
        'tabla'       => $filtroTabla,
        'accion'      => $filtroAccion,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
        'pagina'      => $pagina,
    ], $overrides), fn($v) => $v !== '' && $v !== null);
    return 'auditoria.php?' . http_build_query($params);
}

$badgeAccion = [
    'crear'     => 'badge-estado badge-recibido',
    'actualizar'=> 'badge-estado badge-enviado',
    'eliminar'  => 'badge-estado badge-cancelado',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .field-chip {
            display: inline-block;
            padding: .1em .45em;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: .3em;
            font-size: .72rem;
            color: #475569;
            font-family: monospace;
        }
        .filters-bar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end; }
        .filters-bar label { display: flex; flex-direction: column; gap: .3rem; font-size: .82rem; font-weight: 600; color: var(--text-primary); }
        .filters-bar input,
        .filters-bar select { padding: .4rem .65rem; border: 1px solid var(--border); border-radius: .375rem; font-size: .85rem; background: var(--card-bg); color: var(--text-primary); }

        /* Modal detalle */
        .audit-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.45); z-index: 900;
            align-items: center; justify-content: center;
        }
        .audit-modal-overlay.open { display: flex; }
        .audit-modal {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            width: 100%; max-width: 680px;
            max-height: 85vh; overflow-y: auto;
            padding: 1.75rem;
        }
        .audit-modal-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 1.25rem;
        }
        .audit-modal-title { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); }
        .audit-modal-meta  { font-size: .8rem; color: var(--text-muted); margin-top: .2rem; }
        .audit-modal-close {
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); font-size: 1.4rem; line-height: 1; padding: 0;
        }
        .audit-modal-close:hover { color: var(--text-primary); }
        .diff-section { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .diff-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: .85rem 1rem;
        }
        .diff-panel-title {
            font-size: .7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .05em; color: var(--text-muted);
            margin-bottom: .6rem;
        }
        .diff-row { display: flex; gap: .5rem; padding: .25rem 0; border-bottom: 1px solid var(--border); font-size: .82rem; }
        .diff-row:last-child { border-bottom: none; }
        .diff-key   { font-weight: 600; color: #3b82f6; min-width: 90px; flex-shrink: 0; font-family: monospace; }
        .diff-val   { color: var(--text-primary); word-break: break-all; font-family: monospace; }
        .diff-null  { color: #9ca3af; font-style: italic; }
        @media (max-width: 540px) { .diff-section { grid-template-columns: 1fr; } }
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
            <nav class="breadcrumb-nav">
                <a href="index.php" class="breadcrumb-item">Inicio</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active">Auditoría</span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php require_once __DIR__ . '/includes/topbar_user.php'; ?>
        </div>
    </header>

    <main class="content">

        <?php if ($error !== ''): ?>
        <div class="toast toast-error" data-autodismiss="6000">
            <span class="toast-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
            <div class="toast-content"><span class="toast-title">Error</span><span class="toast-message"><?= $error ?></span></div>
            <button class="toast-close" aria-label="Cerrar">×</button>
            <div class="toast-progress"></div>
        </div>
        <?php endif; ?>

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Auditoría de cambios</h1>
                <p class="page-subtitle"><?= number_format($total) ?> registro<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?></p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-body">
                <form method="GET" action="auditoria.php" class="filters-bar">
                    <label>
                        Tabla
                        <select name="tabla">
                            <option value="">Todas</option>
                            <option value="productos"  <?= $filtroTabla === 'productos'   ? 'selected' : '' ?>>Productos</option>
                            <option value="categorias" <?= $filtroTabla === 'categorias'  ? 'selected' : '' ?>>Categorías</option>
                            <option value="pedidos"    <?= $filtroTabla === 'pedidos'     ? 'selected' : '' ?>>Pedidos</option>
                        </select>
                    </label>
                    <label>
                        Acción
                        <select name="accion">
                            <option value="">Todas</option>
                            <option value="crear"      <?= $filtroAccion === 'crear'      ? 'selected' : '' ?>>Crear</option>
                            <option value="actualizar" <?= $filtroAccion === 'actualizar' ? 'selected' : '' ?>>Actualizar</option>
                            <option value="eliminar"   <?= $filtroAccion === 'eliminar'   ? 'selected' : '' ?>>Eliminar</option>
                        </select>
                    </label>
                    <label>
                        Desde
                        <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fechaDesde) ?>">
                    </label>
                    <label>
                        Hasta
                        <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fechaHasta) ?>">
                    </label>
                    <button type="submit" class="btn-primary" style="align-self:flex-end;">Filtrar</button>
                    <a href="auditoria.php" class="btn-ghost" style="align-self:flex-end;">Limpiar</a>
                </form>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card">
            <div class="card-body" style="padding:0;">
                <?php if (empty($registros)): ?>
                <div class="td-empty" style="padding:2rem;text-align:center;">
                    No hay registros de auditoría con los filtros seleccionados.
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tabla</th>
                            <th>Reg.</th>
                            <th>Acción</th>
                            <th>Campos</th>
                            <th>Usuario</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registros as $r): ?>
                        <tr>
                            <td style="white-space:nowrap;font-size:.85rem;">
                                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['fecha'])), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <span class="category-pill"><?= htmlspecialchars($r['tabla'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td style="color:var(--text-muted);font-size:.85rem;">#<?= (int)$r['registro_id'] ?></td>
                            <td>
                                <span class="badge-estado <?= $badgeAccion[$r['accion']] ?? 'badge-estado badge-borrador' ?>">
                                    <?= htmlspecialchars($r['accion'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= camposModificados($r['datos_anteriores'], $r['datos_nuevos']) ?></td>
                            <td style="font-size:.85rem;"><?= htmlspecialchars($r['usuario'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="td-actions">
                                <button
                                    class="btn btn-sm btn-ghost"
                                    onclick="abrirDetalle(<?= htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)"
                                    title="Ver detalle">
                                    Ver
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <?php if ($totalPaginas > 1): ?>
            <div class="card-footer" style="display:flex;justify-content:center;align-items:center;gap:.5rem;padding:1rem;">
                <?php if ($pagina > 1): ?>
                    <a href="<?= htmlspecialchars(urlFiltros(['pagina' => $pagina - 1])) ?>" class="btn btn-sm btn-ghost">← Anterior</a>
                <?php endif; ?>
                <span style="font-size:.85rem;color:var(--text-muted);">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                <?php if ($pagina < $totalPaginas): ?>
                    <a href="<?= htmlspecialchars(urlFiltros(['pagina' => $pagina + 1])) ?>" class="btn btn-sm btn-ghost">Siguiente →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- ── Modal de detalle ──────────────────────────────────────────────────── -->
<div class="audit-modal-overlay" id="auditModal" onclick="cerrarDetalle(event)">
    <div class="audit-modal" role="dialog" aria-modal="true">
        <div class="audit-modal-header">
            <div>
                <div class="audit-modal-title" id="modalTitle">Detalle del cambio</div>
                <div class="audit-modal-meta" id="modalMeta"></div>
            </div>
            <button class="audit-modal-close" onclick="cerrarDetalle()" aria-label="Cerrar">×</button>
        </div>
        <div class="diff-section" id="modalDiff"></div>
    </div>
</div>

<script src="js/app.js"></script>
<script>
/**
 * Formatea un objeto JSON como lista de filas clave→valor.
 * @param {Object|null} obj
 * @returns {string} HTML
 */
function renderDiffPanel(obj) {
    if (!obj || typeof obj !== 'object' || !Object.keys(obj).length) {
        return '<span style="color:#9ca3af;font-style:italic;font-size:.82rem;">Sin datos</span>';
    }
    return Object.entries(obj).map(([k, v]) => {
        const val = v === null || v === undefined
            ? '<span class="diff-null">null</span>'
            : '<span class="diff-val">' + String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</span>';
        return `<div class="diff-row"><span class="diff-key">${k}</span>${val}</div>`;
    }).join('');
}

/**
 * Abre el modal de detalle con los datos del registro de auditoría.
 * @param {Object} r Fila de auditoría.
 */
function abrirDetalle(r) {
    const acciones = { crear: 'Crear', actualizar: 'Actualizar', eliminar: 'Eliminar' };
    document.getElementById('modalTitle').textContent =
        `${acciones[r.accion] ?? r.accion} en ${r.tabla} #${r.registro_id}`;
    document.getElementById('modalMeta').textContent =
        `${r.fecha}  ·  Usuario: ${r.usuario ?? 'admin'}  ·  IP: ${r.ip ?? '—'}`;

    const ant  = r.datos_anteriores ? JSON.parse(r.datos_anteriores) : null;
    const nuev = r.datos_nuevos     ? JSON.parse(r.datos_nuevos)     : null;

    document.getElementById('modalDiff').innerHTML = `
        <div class="diff-panel">
            <div class="diff-panel-title">Antes</div>
            ${renderDiffPanel(ant)}
        </div>
        <div class="diff-panel">
            <div class="diff-panel-title">Después</div>
            ${renderDiffPanel(nuev)}
        </div>`;

    document.getElementById('auditModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

/**
 * Cierra el modal de detalle.
 * @param {Event} [e]
 */
function cerrarDetalle(e) {
    if (e && e.target !== document.getElementById('auditModal')) return;
    document.getElementById('auditModal').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarDetalle();
});
</script>
</body>
</html>
