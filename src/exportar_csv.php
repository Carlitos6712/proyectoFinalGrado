<?php
/**
 * Exportación de productos a CSV con filtros y selección de columnas.
 *
 * Si se recibe ?exportar=1 genera y descarga el CSV directamente.
 * En caso contrario muestra el formulario de configuración con preview.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/middleware/RoleMiddleware.php';
RoleMiddleware::requireAdmin();
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';
require_once __DIR__ . '/includes/Categoria.php';

/** Columnas exportables: clave DB → etiqueta legible */
const COLUMNAS_EXPORT = [
    'nombre'          => 'Nombre',
    'codigo_ref'      => 'Código ref.',
    'categoria_nombre'=> 'Categoría',
    'marca_nombre'    => 'Marca',
    'precio'          => 'Precio (€)',
    'stock'           => 'Stock',
    'stock_minimo'    => 'Stock mínimo',
    'descripcion'     => 'Descripción',
    'proveedor'       => 'Proveedor',
    'ubicacion'       => 'Ubicación',
    'codigo_barras'   => 'Código barras',
    'peso'            => 'Peso (g)',
    'created_at'      => 'Fecha creación',
];

/** Columnas seleccionadas por defecto */
const COLUMNAS_DEFAULT = ['nombre','codigo_ref','categoria_nombre','marca_nombre','precio','stock','stock_minimo'];

$productoModel = new Producto();
$categorias    = [];
try {
    $categorias = (new Categoria())->listar();
} catch (\Throwable $e) {}

// ── Recoger filtros (GET o POST) ──────────────────────────────────────────────
$termino     = trim($_REQUEST['q']            ?? '');
$categoriaId = ($_REQUEST['categoria_id']     ?? '') !== '' ? (int)$_REQUEST['categoria_id']  : null;
$precioMin   = ($_REQUEST['precio_min']       ?? '') !== '' ? (float)$_REQUEST['precio_min']  : null;
$precioMax   = ($_REQUEST['precio_max']       ?? '') !== '' ? (float)$_REQUEST['precio_max']  : null;
$stockMin    = ($_REQUEST['stock_min']        ?? '') !== '' ? (int)$_REQUEST['stock_min']      : null;
$stockMax    = ($_REQUEST['stock_max']        ?? '') !== '' ? (int)$_REQUEST['stock_max']      : null;
$soloStockBajo = isset($_REQUEST['stock_bajo']) && $_REQUEST['stock_bajo'] === '1' ? true : null;
$columnas    = $_REQUEST['columnas'] ?? COLUMNAS_DEFAULT;
$columnas    = array_intersect($columnas, array_keys(COLUMNAS_EXPORT)); // whitelist
if (empty($columnas)) {
    $columnas = COLUMNAS_DEFAULT;
}

// ── Contar registros que coinciden ────────────────────────────────────────────
$total = 0;
try {
    $total = $productoModel->contarFiltrados($termino ?: null, $categoriaId, $precioMin, $precioMax, $stockMin, $stockMax, $soloStockBajo);
} catch (\Throwable $e) {}

// ── DESCARGA CSV ──────────────────────────────────────────────────────────────
if (isset($_GET['exportar']) && $_GET['exportar'] === '1') {
    try {
        $productos = $productoModel->listarPaginado(
            1, 100000,
            $termino ?: null, $categoriaId, $precioMin, $precioMax,
            $stockMin, $stockMax, 'nombre_asc', $soloStockBajo
        );
    } catch (\Throwable $e) {
        $_SESSION['flash_error'] = 'Error al exportar: ' . $e->getMessage();
        header('Location: exportar_csv.php');
        exit;
    }

    $filename = 'productos_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $fh = fopen('php://output', 'w');
    // BOM UTF-8 para compatibilidad con Excel
    fwrite($fh, "\xEF\xBB\xBF");

    // Cabecera
    $cabecera = array_map(fn($k) => COLUMNAS_EXPORT[$k], $columnas);
    fputcsv($fh, $cabecera, ',', '"', '\\');

    foreach ($productos as $p) {
        $fila = [];
        foreach ($columnas as $col) {
            $val = $p[$col] ?? '';
            // Formatear precio con 2 decimales
            if ($col === 'precio') {
                $val = number_format((float)$val, 2, '.', '');
            }
            // Recortar timestamp a solo fecha
            if ($col === 'created_at' && $val) {
                $val = substr($val, 0, 10);
            }
            $fila[] = $val;
        }
        fputcsv($fh, $fila, ',', '"', '\\');
    }
    fclose($fh);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar CSV — es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        body { background: #f1f5f9; min-height: 100vh; }
        .csv-page    { max-width: 860px; margin: 0 auto; padding: 2rem 1rem; }
        .csv-topbar  { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        .csv-topbar h1 { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0; }
        .csv-card    { background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.1); padding: 2rem; }
        .section-title { font-size: .9rem; font-weight: 700; color: #374151; margin: 0 0 .75rem; padding-bottom: .4rem; border-bottom: 1px solid #e5e7eb; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .75rem; }
        .field-group  { display: flex; flex-direction: column; gap: .3rem; }
        .field-group label { font-size: .8rem; font-weight: 600; color: #374151; }
        .field-group input,
        .field-group select { padding: .4rem .65rem; border: 1px solid #d1d5db; border-radius: .375rem; font-size: .85rem; }
        .cols-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: .4rem .75rem; margin-top: .5rem; }
        .col-check { display: flex; align-items: center; gap: .4rem; font-size: .85rem; cursor: pointer; }
        .col-check input { width: 15px; height: 15px; cursor: pointer; }
        .preview-badge { display: inline-flex; align-items: center; gap: .4rem; background: #dbeafe; color: #1d4ed8; padding: .35rem .75rem; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; flex-wrap: wrap; gap: .75rem; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/topbar_user.php'; ?>

<div class="csv-page">

    <div class="csv-topbar">
        <a href="productos.php" style="display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;font-size:.875rem;color:#374151;font-weight:600;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Volver a Productos
        </a>
        <h1>Exportar productos a CSV</h1>
    </div>

    <div class="csv-card">
        <form method="GET" action="exportar_csv.php" id="exportForm">

            <!-- ── Filtros ──────────────────────────────────────── -->
            <p class="section-title">Filtros</p>
            <div class="filters-grid">
                <div class="field-group" style="grid-column:span 2;">
                    <label for="q">Búsqueda (nombre / ref.)</label>
                    <input type="text" id="q" name="q" value="<?= htmlspecialchars($termino, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: pastilla, brembo…">
                </div>
                <div class="field-group">
                    <label for="categoria_id">Categoría</label>
                    <select id="categoria_id" name="categoria_id">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $categoriaId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-group">
                    <label>
                        <input type="checkbox" name="stock_bajo" value="1" <?= $soloStockBajo ? 'checked' : '' ?>>
                        Solo stock bajo
                    </label>
                </div>
                <div class="field-group">
                    <label for="precio_min">Precio mín. (€)</label>
                    <input type="number" id="precio_min" name="precio_min" step="0.01" min="0" value="<?= $precioMin !== null ? $precioMin : '' ?>">
                </div>
                <div class="field-group">
                    <label for="precio_max">Precio máx. (€)</label>
                    <input type="number" id="precio_max" name="precio_max" step="0.01" min="0" value="<?= $precioMax !== null ? $precioMax : '' ?>">
                </div>
                <div class="field-group">
                    <label for="stock_min">Stock mín.</label>
                    <input type="number" id="stock_min" name="stock_min" min="0" value="<?= $stockMin !== null ? $stockMin : '' ?>">
                </div>
                <div class="field-group">
                    <label for="stock_max">Stock máx.</label>
                    <input type="number" id="stock_max" name="stock_max" min="0" value="<?= $stockMax !== null ? $stockMax : '' ?>">
                </div>
            </div>

            <!-- ── Columnas ─────────────────────────────────────── -->
            <p class="section-title" style="margin-top:1.5rem;">Columnas a exportar</p>
            <div style="display:flex;gap:.5rem;margin-bottom:.6rem;">
                <button type="button" onclick="toggleAll(true)"  style="font-size:.78rem;background:none;border:none;color:#3b82f6;cursor:pointer;padding:0;">Seleccionar todas</button>
                <span style="color:#d1d5db;">|</span>
                <button type="button" onclick="toggleAll(false)" style="font-size:.78rem;background:none;border:none;color:#6b7280;cursor:pointer;padding:0;">Deseleccionar</button>
            </div>
            <div class="cols-grid">
                <?php foreach (COLUMNAS_EXPORT as $key => $label): ?>
                <label class="col-check">
                    <input type="checkbox" name="columnas[]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                        <?= in_array($key, $columnas, true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- ── Barra de acción ──────────────────────────────── -->
            <div class="action-bar">
                <div>
                    <span class="preview-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span id="previewCount"><?= number_format($total) ?></span> producto<?= $total !== 1 ? 's' : '' ?> coinciden
                    </span>
                </div>
                <div style="display:flex;gap:.75rem;align-items:center;">
                    <button type="submit" name="noop" class="btn btn-secondary btn-sm" style="font-size:.82rem;">
                        Actualizar filtros
                    </button>
                    <button type="submit" name="exportar" value="1" class="btn-primary" <?= $total === 0 ? 'disabled' : '' ?>>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Descargar CSV (<?= number_format($total) ?>)
                    </button>
                </div>
            </div>

        </form>
    </div><!-- .csv-card -->

</div><!-- .csv-page -->

<script>
/**
 * Marca o desmarca todas las casillas de columnas.
 * @param {boolean} estado
 */
function toggleAll(estado) {
    document.querySelectorAll('.cols-grid input[type=checkbox]').forEach(cb => {
        cb.checked = estado;
    });
}
</script>
</body>
</html>
