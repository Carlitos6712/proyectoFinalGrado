<?php
/**
 * Historial de auditoría de cambios en el inventario.
 *
 * Muestra un log paginado y filtrable de todas las operaciones de
 * crear, actualizar y eliminar realizadas sobre productos y categorías.
 * Los registros son de solo lectura: no existe ninguna ruta de borrado.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
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

// ── Parámetros de filtro y paginación ────────────────────────────────────────
$filtroTabla  = trim($_GET['tabla']       ?? '');
$filtroAccion = trim($_GET['accion']      ?? '');
$fechaDesde   = trim($_GET['fecha_desde'] ?? '');
$fechaHasta   = trim($_GET['fecha_hasta'] ?? '');
$pagina       = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina    = 25;

$registros     = [];
$total         = 0;
$totalPaginas  = 1;
$error         = '';

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

// ── Helper: decodifica y formatea un JSON para mostrar diff ──────────────────
function formatearDiff(?string $json): string
{
    if ($json === null || $json === '') {
        return '<span class="audit-null">—</span>';
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return htmlspecialchars($json);
    }
    $lines = [];
    foreach ($data as $k => $v) {
        $val    = is_null($v) ? 'null' : htmlspecialchars((string) $v);
        $lines[] = '<span class="audit-key">' . htmlspecialchars($k) . '</span>: ' . $val;
    }
    return implode('<br>', $lines);
}

function urlFiltros(array $overrides = []): string
{
    global $filtroTabla, $filtroAccion, $fechaDesde, $fechaHasta, $pagina;
    $params = [
        'tabla'       => $filtroTabla,
        'accion'      => $filtroAccion,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
        'pagina'      => $pagina,
    ];
    $params = array_merge($params, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return 'auditoria.php?' . http_build_query($params);
}

$badgeAccion = [
    'crear'     => 'badge-success',
    'actualizar'=> 'badge-warning',
    'eliminar'  => 'badge-danger',
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
        .audit-key   { font-weight: 600; color: var(--accent, #2563eb); }
        .audit-null  { color: var(--text-muted, #9ca3af); font-style: italic; }
        .audit-diff  { font-size: .78rem; line-height: 1.6; font-family: monospace; }
        .diff-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
        .diff-col h4 { margin: 0 0 .25rem; font-size: .72rem; text-transform: uppercase; color: var(--text-muted); }
    </style>
</head>
<body class="layout">

<?php require_once __DIR__ . '/includes/_sidebar.php'; ?>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

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
                <span class="breadcrumb-item active">Auditoría</span>
            </nav>
        </div>
        <div class="topbar-right">
            <?php require_once __DIR__ . '/includes/topbar_user.php'; ?>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="content">

        <?php if ($error !== ''): ?>
        <div class="alert-banner alert-banner-error" style="margin-bottom:1.25rem;">
            <svg class="alert-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= $error ?>
        </div>
        <?php endif; ?>

        <?php if ($flashSuccess !== ''): ?>
        <div class="alert-banner alert-banner-success" style="margin-bottom:1.25rem;">
            <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">Auditoría de cambios</h1>
                <p class="page-subtitle">Registro inmutable de operaciones sobre el inventario · <?= number_format($total) ?> entradas</p>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" action="auditoria.php" class="data-toolbar" style="margin-bottom:1.25rem;flex-wrap:wrap;">
            <select name="tabla" class="filter-select" onchange="this.form.submit()">
                <option value="">Todas las tablas</option>
                <option value="productos"  <?= $filtroTabla === 'productos'   ? 'selected' : '' ?>>Productos</option>
                <option value="categorias" <?= $filtroTabla === 'categorias'  ? 'selected' : '' ?>>Categorías</option>
                <option value="marcas"     <?= $filtroTabla === 'marcas'      ? 'selected' : '' ?>>Marcas</option>
                <option value="movimientos"<?= $filtroTabla === 'movimientos' ? 'selected' : '' ?>>Movimientos</option>
            </select>
            <select name="accion" class="filter-select" onchange="this.form.submit()">
                <option value="">Todas las acciones</option>
                <option value="crear"      <?= $filtroAccion === 'crear'      ? 'selected' : '' ?>>Crear</option>
                <option value="actualizar" <?= $filtroAccion === 'actualizar' ? 'selected' : '' ?>>Actualizar</option>
                <option value="eliminar"   <?= $filtroAccion === 'eliminar'   ? 'selected' : '' ?>>Eliminar</option>
            </select>
            <input type="date" name="fecha_desde" class="filter-select" style="max-width:140px;"
                   value="<?= htmlspecialchars($fechaDesde, ENT_QUOTES, 'UTF-8') ?>"
                   title="Desde" placeholder="Desde">
            <input type="date" name="fecha_hasta" class="filter-select" style="max-width:140px;"
                   value="<?= htmlspecialchars($fechaHasta, ENT_QUOTES, 'UTF-8') ?>"
                   title="Hasta" placeholder="Hasta">
            <button type="submit" class="btn-primary" style="flex-shrink:0;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Filtrar
            </button>
            <?php if ($filtroTabla !== '' || $filtroAccion !== '' || $fechaDesde !== '' || $fechaHasta !== ''): ?>
            <a href="auditoria.php" class="btn-ghost" style="flex-shrink:0;">Limpiar</a>
            <?php endif; ?>
        </form>

        <!-- Tabla de registros -->
        <div class="card">
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <?php if (empty($registros)): ?>
                <div class="td-empty" style="padding:3rem;text-align:center;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-muted);margin:0 auto 1rem;display:block;">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <p style="color:var(--text-muted);">No hay registros con los filtros aplicados.</p>
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Tabla</th>
                            <th>ID reg.</th>
                            <th>Acción</th>
                            <th>Antes → Después</th>
                            <th>Usuario</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $r): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.78rem;"><?= (int)$r['id'] ?></td>
                            <td style="white-space:nowrap;font-size:.82rem;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['fecha'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span style="display:inline-flex;align-items:center;padding:.2em .55em;background:var(--bg-hover);border-radius:4px;font-size:.75rem;font-weight:600;color:var(--text-secondary);">
                                    <?= htmlspecialchars($r['tabla'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="color:var(--text-muted);font-size:.82rem;"><?= (int)$r['registro_id'] ?></td>
                            <td>
                                <?php
                                $accionClass = match($r['accion']) {
                                    'crear'     => 'status-pill-success',
                                    'actualizar'=> 'status-pill-warning',
                                    'eliminar'  => 'status-pill-danger',
                                    default     => ''
                                };
                                $accionLabel = match($r['accion']) {
                                    'crear'     => 'Crear',
                                    'actualizar'=> 'Actualizar',
                                    'eliminar'  => 'Eliminar',
                                    default     => htmlspecialchars($r['accion'], ENT_QUOTES, 'UTF-8')
                                };
                                ?>
                                <span class="status-pill <?= $accionClass ?>"><?= $accionLabel ?></span>
                            </td>
                            <td class="audit-diff" style="min-width:220px;max-width:340px;">
                                <div class="diff-grid">
                                    <div class="diff-col">
                                        <h4>Anterior</h4>
                                        <?= formatearDiff($r['datos_anteriores']) ?>
                                    </div>
                                    <div class="diff-col">
                                        <h4>Nuevo</h4>
                                        <?= formatearDiff($r['datos_nuevos']) ?>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:.82rem;"><?= htmlspecialchars($r['usuario'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($r['ip'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Paginación -->
            <?php if ($totalPaginas > 1): ?>
            <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;padding:.875rem 1.25rem;">
                <span style="font-size:.82rem;color:var(--text-muted);">
                    Página <?= $pagina ?> de <?= $totalPaginas ?> · <?= number_format($total) ?> registros
                </span>
                <div style="display:flex;gap:.5rem;">
                    <?php if ($pagina > 1): ?>
                        <a href="<?= htmlspecialchars(urlFiltros(['pagina' => $pagina - 1])) ?>" class="btn-ghost" style="padding:.3rem .75rem;font-size:.82rem;">← Anterior</a>
                    <?php endif; ?>
                    <?php if ($pagina < $totalPaginas): ?>
                        <a href="<?= htmlspecialchars(urlFiltros(['pagina' => $pagina + 1])) ?>" class="btn-ghost" style="padding:.3rem .75rem;font-size:.82rem;">Siguiente →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script src="js/app.js"></script>
</body>
</html>
