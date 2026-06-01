<?php
/**
 * Importación masiva de productos mediante CSV.
 *
 * Flujo en tres pasos:
 *   1. Subida del archivo CSV.
 *   2. Mapeo de columnas CSV → campos de producto.
 *   3. Importación y resumen de resultados.
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
require_once __DIR__ . '/includes/Categoria.php';
require_once __DIR__ . '/core/Session.php';

// ── Constantes ────────────────────────────────────────────────────────────────
const CSV_MAX_BYTES  = 5 * 1024 * 1024; // 5 MB
const CSV_TMP_PREFIX = 'es21csv_';

// Campos de la tabla productos y su etiqueta legible
$DB_FIELDS = [
    ''                  => '— Ignorar columna —',
    'nombre'            => 'Nombre del producto *',
    'descripcion'       => 'Descripción corta',
    'descripcion_larga' => 'Descripción larga',
    'precio'            => 'Precio (€) *',
    'stock'             => 'Stock actual *',
    'stock_minimo'      => 'Stock mínimo',
    'codigo_ref'        => 'Código de referencia',
    'marca'             => 'Marca (texto)',
    'codigo_barras'     => 'Código de barras',
    'url_proveedor'     => 'URL del proveedor',
    'proveedor'         => 'Proveedor',
    'ubicacion'         => 'Ubicación en almacén',
    'peso'              => 'Peso (gramos)',
    'capacidad'         => 'Capacidad (mL)',
    'longitud'          => 'Longitud (mm)',
    'anchura'           => 'Anchura (mm)',
    'diametro'          => 'Diámetro (mm)',
    'alertas_email'     => 'Alertas email (0 ó 1)',
    'categoria_id'      => 'ID Categoría',
    'categoria_nombre'  => 'Nombre Categoría (se resuelve a ID)',
];

$REQUIRED_FIELDS = ['nombre', 'precio', 'stock'];

// ── Estado de la petición ─────────────────────────────────────────────────────
$step      = (int) ($_POST['step'] ?? 0);
$error     = '';
$categorias = [];

try {
    $categorias = (new Categoria())->listar();
} catch (\Throwable $e) {
    $error = 'No se pudo conectar a la base de datos.';
}

// ── PASO 1: Recibir archivo ───────────────────────────────────────────────────
$tmpPath     = '';
$headers     = [];
$preview     = [];
$csvDelim    = ',';

if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['csv_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error al subir el archivo. Verifica que el tamaño no supere 5 MB.';
        $step  = 0;
    } elseif ($file['size'] > CSV_MAX_BYTES) {
        $error = 'El archivo supera el límite de 5 MB.';
        $step  = 0;
    } else {
        // Detectar delimitador (coma o punto y coma)
        $sample = file_get_contents($file['tmp_name'], false, null, 0, 4096);
        $csvDelim = (substr_count($sample, ';') >= substr_count($sample, ',')) ? ';' : ',';

        // Leer cabeceras y preview
        $fh = fopen($file['tmp_name'], 'r');
        if ($fh) {
            // Detectar y quitar BOM UTF-8
            $bom = fread($fh, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($fh);
            }
            $headers = fgetcsv($fh, 0, $csvDelim, '"', '\\') ?: [];
            $count   = 0;
            while (($row = fgetcsv($fh, 0, $csvDelim, '"', '\\')) !== false && $count < 6) {
                $preview[] = $row;
                $count++;
            }
            fclose($fh);

            if (empty($headers)) {
                $error = 'El archivo CSV no tiene cabeceras o está vacío.';
                $step  = 0;
            } else {
                // Guardar en directorio temporal con nombre controlado
                $safeName = CSV_TMP_PREFIX . bin2hex(random_bytes(8)) . '.csv';
                $dest     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $safeName;
                move_uploaded_file($file['tmp_name'], $dest);
                $tmpPath = $safeName; // solo el nombre, sin ruta
            }
        } else {
            $error = 'No se pudo leer el archivo subido.';
            $step  = 0;
        }
    }
}

// ── PASO 2: Importar con el mapeo ─────────────────────────────────────────────
$importResult = null;

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido. Recarga la página.';
        $step  = 0;
    } else {
        $rawName  = basename($_POST['tmp_file'] ?? '');
        $tmpPath  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $rawName;
        $csvDelim = in_array($_POST['delimitador'] ?? ',', [',', ';'], true) ? $_POST['delimitador'] : ',';
        $mapping  = $_POST['mapping'] ?? []; // ['csv_col_idx' => 'db_field']

        if (!str_starts_with(basename($rawName), CSV_TMP_PREFIX) || !file_exists($tmpPath)) {
            $error = 'Archivo temporal no encontrado. Por favor, vuelve a subir el CSV.';
            $step  = 0;
        } else {
            // Verificar que los campos requeridos estén mapeados
            $mappedDbFields = array_values($mapping);
            $missingRequired = [];
            foreach ($REQUIRED_FIELDS as $req) {
                if (!in_array($req, $mappedDbFields, true)) {
                    $missingRequired[] = $DB_FIELDS[$req];
                }
            }
            if ($missingRequired) {
                $error = 'Faltan columnas obligatorias: ' . implode(', ', $missingRequired);
                $step  = 1;
                // Re-leer cabeceras/preview para mostrar el formulario de mapeo de nuevo
                $fh = fopen($tmpPath, 'r');
                if ($fh) {
                    $bom = fread($fh, 3);
                    if ($bom !== "\xEF\xBB\xBF") rewind($fh);
                    $headers = fgetcsv($fh, 0, $csvDelim, '"', '\\') ?: [];
                    $count = 0;
                    while (($row = fgetcsv($fh, 0, $csvDelim, '"', '\\')) !== false && $count < 6) {
                        $preview[] = $row;
                        $count++;
                    }
                    fclose($fh);
                }
                $tmpPath = $rawName;
            } else {
                // Resolver nombres de categoría a IDs
                $catMap = [];
                foreach ($categorias as $cat) {
                    $catMap[mb_strtolower(trim($cat['nombre']))] = (int) $cat['id'];
                }

                $pdo        = Database::getInstance();
                $businessId = Session::getBusinessId();
                $bizCol     = $businessId !== null ? ', business_id' : '';
                $bizVal     = $businessId !== null ? ', :biz_id'     : '';
                $sql = "INSERT INTO productos
                            (nombre, descripcion, descripcion_larga, precio, stock, stock_minimo,
                             codigo_ref, marca, codigo_barras, url_proveedor, proveedor, ubicacion,
                             peso, capacidad, longitud, anchura, diametro, alertas_email, categoria_id{$bizCol})
                        VALUES
                            (:nombre, :descripcion, :descripcion_larga, :precio, :stock, :stock_minimo,
                             :codigo_ref, :marca, :codigo_barras, :url_proveedor, :proveedor, :ubicacion,
                             :peso, :capacidad, :longitud, :anchura, :diametro, :alertas_email, :categoria_id{$bizVal})";
                $stmt = $pdo->prepare($sql);

                $inserted = 0;
                $skipped  = 0;
                $errors   = [];
                $lineNum  = 1; // después de cabecera

                $fh = fopen($tmpPath, 'r');
                // Quitar BOM
                $bom = fread($fh, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($fh);
                fgetcsv($fh, 0, $csvDelim, '"', '\\'); // saltar cabecera

                while (($row = fgetcsv($fh, 0, $csvDelim, '"', '\\')) !== false) {
                    $lineNum++;
                    $data = [
                        'nombre'            => '',
                        'descripcion'       => '',
                        'descripcion_larga' => '',
                        'precio'            => 0.00,
                        'stock'             => 0,
                        'stock_minimo'      => 0,
                        'codigo_ref'        => null,
                        'marca'             => null,
                        'codigo_barras'     => null,
                        'url_proveedor'     => null,
                        'proveedor'         => null,
                        'ubicacion'         => null,
                        'peso'              => null,
                        'capacidad'         => null,
                        'longitud'          => null,
                        'anchura'           => null,
                        'diametro'          => null,
                        'alertas_email'     => 1,
                        'categoria_id'      => null,
                    ];

                    foreach ($mapping as $colIdx => $dbField) {
                        if ($dbField === '' || !isset($row[(int)$colIdx])) {
                            continue;
                        }
                        $val = trim($row[(int)$colIdx]);
                        if ($dbField === 'categoria_nombre') {
                            $data['categoria_id'] = $catMap[mb_strtolower($val)] ?? null;
                        } else {
                            $data[$dbField] = $val !== '' ? $val : null;
                        }
                    }

                    // Validaciones básicas
                    $rowErrors = [];
                    if (empty($data['nombre'])) {
                        $rowErrors[] = 'nombre vacío';
                    }
                    $precio = filter_var($data['precio'], FILTER_VALIDATE_FLOAT);
                    if ($precio === false || $precio < 0) {
                        $rowErrors[] = 'precio inválido';
                    }
                    $stock = filter_var($data['stock'], FILTER_VALIDATE_INT);
                    if ($stock === false || $stock < 0) {
                        $rowErrors[] = 'stock inválido';
                    }

                    if ($rowErrors) {
                        $errors[] = "Fila {$lineNum}: " . implode(', ', $rowErrors);
                        $skipped++;
                        continue;
                    }

                    $data['precio']        = (float) $precio;
                    $data['stock']         = (int)   $stock;
                    $data['stock_minimo']  = max(0, (int) ($data['stock_minimo'] ?? 0));
                    $data['alertas_email'] = in_array($data['alertas_email'], ['1', 'true', 'si', 'yes', 1], false) ? 1 : 0;
                    foreach (['peso','capacidad','longitud','anchura'] as $intCol) {
                        $data[$intCol] = ($data[$intCol] !== null && $data[$intCol] !== '')
                            ? (int) $data[$intCol] : null;
                    }
                    $data['diametro'] = ($data['diametro'] !== null && $data['diametro'] !== '')
                        ? (float) $data['diametro'] : null;

                    try {
                        $params = [];
                        foreach ($data as $k => $v) {
                            $params[":$k"] = $v;
                        }
                        if ($businessId !== null) {
                            $params[':biz_id'] = $businessId;
                        }
                        $stmt->execute($params);
                        $inserted++;
                    } catch (\Throwable $e) {
                        $errors[] = "Fila {$lineNum}: " . $e->getMessage();
                        $skipped++;
                    }
                }
                fclose($fh);
                @unlink($tmpPath);

                $importResult = compact('inserted', 'skipped', 'errors');
                $step = 3;
            }
        }
    }
}

// ── CSRF token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar CSV — es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        body { background:#f1f5f9; min-height:100vh; }
        .csv-page   { max-width:960px; margin:0 auto; padding:2rem 1rem; }
        .csv-topbar { display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; }
        .csv-topbar h1 { font-size:1.25rem; font-weight:700; color:#1e293b; margin:0; }
        .csv-card   { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.1); padding:2rem; }
        .csv-steps  { display:flex; gap:.5rem; margin-bottom:1.5rem; }
        .csv-step   { flex:1; text-align:center; padding:.4rem .6rem; border-radius:4px; background:#f1f5f9; font-size:.8rem; font-weight:600; color:#64748b; }
        .csv-step.active { background:#3b82f6; color:#fff; }
        .csv-step.done   { background:#22c55e; color:#fff; }
        .map-table  { width:100%; border-collapse:collapse; font-size:.875rem; }
        .map-table th, .map-table td { padding:.5rem .75rem; border:1px solid #e2e8f0; vertical-align:middle; }
        .map-table thead th { background:#f8fafc; font-weight:600; }
        .map-table select   { width:100%; padding:.3rem; border:1px solid #cbd5e1; border-radius:4px; }
        .preview-wrap { overflow-x:auto; max-height:220px; overflow-y:auto; margin-bottom:1rem; }
        .preview-tbl  { font-size:.78rem; border-collapse:collapse; }
        .preview-tbl th, .preview-tbl td { padding:.3rem .5rem; border:1px solid #e2e8f0; white-space:nowrap; }
        .preview-tbl thead th { background:#f8fafc; position:sticky; top:0; }
        .result-ok   { color:#16a34a; font-weight:700; font-size:1.25rem; }
        .result-skip { color:#dc2626; }
        .error-list  { max-height:200px; overflow-y:auto; font-size:.8rem; background:#fef2f2; border:1px solid #fecaca; border-radius:4px; padding:.75rem 1rem; margin-top:.75rem; }
        .upload-zone { border:2px dashed #cbd5e1; border-radius:8px; padding:2.5rem; text-align:center; cursor:pointer; transition:border-color .2s; }
        .upload-zone:hover { border-color:#3b82f6; }
        .upload-zone input[type=file] { display:none; }
        .upload-zone label { cursor:pointer; display:block; }
        .upload-zone svg  { display:block; margin:0 auto .75rem; }
        .alert-warning { background:#fffbeb; border:1px solid #fcd34d; color:#92400e; border-radius:6px; padding:.75rem 1rem; margin-bottom:1rem; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/topbar_user.php'; ?>

<div class="csv-page">

    <div class="csv-topbar">
        <a href="productos.php" class="btn-secondary" style="display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;font-size:.875rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Volver a Productos
        </a>
        <h1>Importación masiva de productos</h1>
    </div>

        <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:1rem;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <div class="csv-card">

            <!-- Indicador de pasos -->
            <div class="csv-steps">
                <div class="csv-step <?= $step === 0 ? 'active' : ($step > 0 ? 'done' : '') ?>">1. Subir archivo</div>
                <div class="csv-step <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">2. Mapear columnas</div>
                <div class="csv-step <?= $step >= 3 ? 'active' : '' ?>">3. Resultado</div>
            </div>

            <?php if ($step === 0): ?>
            <!-- ── PASO 0: Formulario de subida ─────────────────────────────── -->
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="step" value="1">

                <div class="upload-zone" id="dropZone">
                    <label for="csv_file">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5">
                            <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/>
                            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                        </svg>
                        <p style="margin:.5rem 0;font-weight:600;color:#374151;">Arrastra tu CSV aquí o haz clic para seleccionar</p>
                        <p style="font-size:.85rem;color:#6b7280;">Formatos: .csv · Tamaño máximo: 5 MB · Codificación: UTF-8</p>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv,.txt" required>
                    </label>
                    <div id="fileSelected" style="margin-top:.75rem;font-size:.85rem;color:#3b82f6;display:none;"></div>
                </div>

                <div style="margin-top:1.5rem;">
                    <h3 style="font-size:.9rem;margin-bottom:.5rem;">Plantilla de columnas recomendadas</h3>
                    <p style="font-size:.8rem;color:#6b7280;margin-bottom:.5rem;">
                        El CSV puede contener cualquier nombre de columna — los mapearás en el siguiente paso.
                        Columnas obligatorias: <strong>nombre, precio, stock</strong>.
                    </p>
                    <a href="?descargar_plantilla=1" class="btn btn-secondary btn-sm">
                        Descargar plantilla CSV de ejemplo
                    </a>
                </div>

                <div style="margin-top:1.5rem;text-align:right;">
                    <button type="submit" class="btn btn-primary">Cargar CSV →</button>
                </div>
            </form>

            <?php elseif ($step === 1): ?>
            <!-- ── PASO 1: Mapeo de columnas ────────────────────────────────── -->
            <div class="alert-warning">
                Se encontraron <strong><?= count($headers) ?></strong> columnas y
                <strong><?= count($preview) ?>+</strong> filas de datos.
                Asigna cada columna del CSV al campo correspondiente del inventario.
            </div>

            <?php if ($preview): ?>
            <div class="preview-wrap">
                <table class="preview-tbl">
                    <thead><tr>
                        <?php foreach ($headers as $h): ?>
                        <th><?= htmlspecialchars((string)$h, ENT_QUOTES, 'UTF-8') ?></th>
                        <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($preview as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                            <td><?= htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <form method="post" id="mapForm">
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="tmp_file" value="<?= htmlspecialchars(basename($tmpPath), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="delimitador" value="<?= htmlspecialchars($csvDelim, ENT_QUOTES, 'UTF-8') ?>">

                <table class="map-table">
                    <thead>
                        <tr>
                            <th>Columna en CSV</th>
                            <th>Ejemplo de valor</th>
                            <th>Campo del inventario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($headers as $idx => $header):
                            $example = $preview[0][$idx] ?? '';
                            // Auto-sugerir campo
                            $suggestion = '';
                            $normalized = strtolower(trim((string)$header));
                            foreach (array_keys($DB_FIELDS) as $dbField) {
                                if ($dbField !== '' && (str_contains($normalized, $dbField) || $normalized === $dbField)) {
                                    $suggestion = $dbField;
                                    break;
                                }
                            }
                            // Sugerencias adicionales
                            $aliases = [
                                'product'  => 'nombre', 'name' => 'nombre', 'producto' => 'nombre',
                                'price'    => 'precio', 'coste' => 'precio', 'pvp' => 'precio',
                                'qty'      => 'stock',  'cantidad' => 'stock', 'quantity' => 'stock',
                                'ref'      => 'codigo_ref', 'sku' => 'codigo_ref', 'referencia' => 'codigo_ref',
                                'brand'    => 'marca',  'fabricante' => 'marca',
                                'barcode'  => 'codigo_barras', 'ean' => 'codigo_barras',
                                'cat'      => 'categoria_nombre', 'category' => 'categoria_nombre', 'categoria' => 'categoria_nombre',
                                'weight'   => 'peso', 'supplier' => 'proveedor',
                                'location' => 'ubicacion', 'shelf' => 'ubicacion',
                            ];
                            if ($suggestion === '' && isset($aliases[$normalized])) {
                                $suggestion = $aliases[$normalized];
                            }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td style="color:#6b7280;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= htmlspecialchars((string)$example, ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <select name="mapping[<?= (int)$idx ?>]">
                                    <?php foreach ($DB_FIELDS as $dbField => $label): ?>
                                    <option value="<?= htmlspecialchars($dbField, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $suggestion === $dbField ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($categorias): ?>
                <div style="margin-top:1rem;font-size:.82rem;color:#6b7280;">
                    <strong>IDs de categorías disponibles:</strong>
                    <?php foreach ($categorias as $cat): ?>
                        <?= (int)$cat['id'] ?> = <?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>&nbsp;&nbsp;
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div style="margin-top:1.5rem;display:flex;justify-content:space-between;">
                    <a href="importar_csv.php" class="btn btn-secondary">← Volver</a>
                    <button type="submit" class="btn btn-primary">Importar productos →</button>
                </div>
            </form>

            <?php elseif ($step === 3 && $importResult !== null): ?>
            <!-- ── PASO 3: Resultado ────────────────────────────────────────── -->
            <div style="text-align:center;padding:1rem 0 .5rem;">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="1.5" style="display:inline-block;">
                    <circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/>
                </svg>
                <p class="result-ok"><?= (int)$importResult['inserted'] ?> productos importados</p>
                <?php if ($importResult['skipped'] > 0): ?>
                <p class="result-skip"><?= (int)$importResult['skipped'] ?> filas omitidas por errores</p>
                <?php endif; ?>
            </div>

            <?php if ($importResult['errors']): ?>
            <div class="error-list">
                <strong>Detalle de errores:</strong><br>
                <?php foreach ($importResult['errors'] as $e): ?>
                — <?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?><br>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:center;">
                <a href="productos.php" class="btn btn-primary">Ver productos</a>
                <a href="importar_csv.php" class="btn btn-secondary">Importar otro CSV</a>
            </div>
            <?php endif; ?>

        </div><!-- .csv-card -->
    </main>
</div>

<script src="js/app.js"></script>
<script>
// Drag & drop visual feedback
(function () {
    const zone = document.getElementById('dropZone');
    const input = document.getElementById('csv_file');
    const info  = document.getElementById('fileSelected');
    if (!zone || !input) return;

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = '#3b82f6'; });
    zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.borderColor = '';
        const dt = e.dataTransfer;
        if (dt.files.length) {
            input.files = dt.files;
            showName(dt.files[0].name);
        }
    });
    input.addEventListener('change', () => { if (input.files.length) showName(input.files[0].name); });

    function showName(n) {
        info.textContent = '✔ ' + n;
        info.style.display = 'block';
    }
}());
</script>
</body>
</html>
<?php
// ── Descargar plantilla CSV ───────────────────────────────────────────────────
if (isset($_GET['descargar_plantilla'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla_productos.csv"');
    // BOM UTF-8 para compatibilidad Excel
    echo "\xEF\xBB\xBF";
    $cols = ['nombre','descripcion','descripcion_larga','precio','stock','stock_minimo',
             'codigo_ref','marca','codigo_barras','proveedor','ubicacion','categoria_nombre',
             'peso','capacidad','longitud','anchura','diametro','alertas_email'];
    echo implode(',', $cols) . "\n";
    echo implode(',', [
        'Pastilla Brembo SC delantera',
        'Pastilla sinterizada de alto rendimiento',
        'Descripción larga detallada aquí',
        '38.90', '10', '5',
        'FRE-XXX', 'Brembo', '8020584046904',
        'MiProveedor SL', 'Estante A-1', 'Frenos',
        '185', '', '110', '52', '',  '1'
    ]) . "\n";
    exit;
}
