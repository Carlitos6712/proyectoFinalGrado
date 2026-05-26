<?php
/**
 * Formulario de edición de un producto existente.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';
require_once __DIR__ . '/includes/Categoria.php';
require_once __DIR__ . '/includes/Marca.php';
require_once __DIR__ . '/includes/ModeloMoto.php';
require_once __DIR__ . '/includes/Proveedor.php';

$error          = '';
$success        = '';
$stockBajoCount = 0;

try {
    $productoModel  = new Producto();
    $categoriaModel = new Categoria();
    $marcaModel     = new Marca();
    $modeloMotoModel = new ModeloMoto();
    $stockBajoCount = count($productoModel->filtrarStockBajo());

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: productos.php');
        exit;
    }

    $pdo                    = Database::getInstance();
    $producto               = $productoModel->obtener($id);
    $categorias             = $categoriaModel->listar();
    $marcas                 = $marcaModel->listar();
    $modelos                = $modeloMotoModel->listarParaSelect();
    $proveedorModel         = new Proveedor($pdo);
    $proveedoresDisponibles = $proveedorModel->listar(true);
    $proveedorIdActual      = (int)($producto['proveedor_id'] ?? 0);

    // Cargar compatibilidades actuales del producto
    $stmtCompat = $pdo->prepare("SELECT modelo_id, notas FROM compatibilidades WHERE producto_id = :pid");
    $stmtCompat->execute([':pid' => $id]);
    $compatActuales  = $stmtCompat->fetchAll(PDO::FETCH_ASSOC);
    $modelosActuales = array_column($compatActuales, 'modelo_id');
    $notasActuales   = array_column($compatActuales, 'notas', 'modelo_id');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre           = trim($_POST['nombre']            ?? '');
        $descripcion      = trim($_POST['descripcion']      ?? '');
        $descripcionLarga = trim($_POST['descripcion_larga'] ?? '') ?: null;
        $precio           = (float) ($_POST['precio']       ?? 0);
        $categoriaId      = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT) ?: null;
        $stockMinimo      = (int)   ($_POST['stock_minimo']  ?? 5);
        $codigoRef        = trim($_POST['codigo_ref']    ?? '') ?: null;
        $marcaId          = filter_input(INPUT_POST, 'marca_id', FILTER_VALIDATE_INT) ?: null;
        $codigoBarras     = trim($_POST['codigo_barras'] ?? '') ?: null;
        $urlProveedor     = trim($_POST['url_proveedor'] ?? '') ?: null;
        $proveedor        = trim($_POST['proveedor']     ?? '') ?: null;
        $ubicacion        = trim($_POST['ubicacion']     ?? '') ?: null;
        $peso             = ($_POST['peso']      ?? '') !== '' ? (int)$_POST['peso']       : null;
        $capacidad        = ($_POST['capacidad'] ?? '') !== '' ? (int)$_POST['capacidad']  : null;
        $longitud         = ($_POST['longitud']  ?? '') !== '' ? (int)$_POST['longitud']   : null;
        $anchura          = ($_POST['anchura']   ?? '') !== '' ? (int)$_POST['anchura']    : null;
        $diametro         = ($_POST['diametro']  ?? '') !== '' ? (float)$_POST['diametro'] : null;
        $proveedorId      = (int)($_POST['proveedor_id'] ?? 0) ?: null;

        if ($nombre === '') {
            throw new AppException('El nombre del producto es obligatorio.', 400);
        }
        if ($precio <= 0) {
            throw new AppException('El precio debe ser mayor que cero.', 400);
        }
        if ($stockMinimo < 0) {
            throw new AppException('El stock mínimo no puede ser negativo.', 400);
        }

        $productoModel->actualizar(
            $id, $nombre, $descripcion, $precio, $categoriaId, $stockMinimo,
            $codigoRef, $descripcionLarga, $marcaId, $codigoBarras, $urlProveedor, $proveedor, $ubicacion,
            $peso, $capacidad, $longitud, $anchura, $diametro, $proveedorId
        );

        // Sincronizar compatibilidades
        $modelosSeleccionados = array_map('intval', $_POST['modelos_compatibles'] ?? []);
        $stmt = $pdo->prepare("DELETE FROM compatibilidades WHERE producto_id = :pid");
        $stmt->execute([':pid' => $id]);
        foreach ($modelosSeleccionados as $modeloId) {
            $notas = strip_tags($_POST['notas_compatibilidad'][$modeloId] ?? '');
            $stmtIns = $pdo->prepare(
                "INSERT INTO compatibilidades (producto_id, modelo_id, notas) VALUES (:pid, :mid, :notas)"
            );
            $stmtIns->execute([':pid' => $id, ':mid' => $modeloId, ':notas' => $notas]);
        }

        if (isset($_POST['eliminar_imagen']) && $_POST['eliminar_imagen'] === '1') {
            $productoModel->eliminarImagen($id);
        } elseif (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            $productoModel->subirImagen($_FILES['imagen'], $id);
        }

        $_SESSION['flash_success'] = 'Producto actualizado correctamente.';
        header('Location: productos.php');
        exit;
    }
} catch (AppException $e) {
    if (!isset($producto)) {
        $_SESSION['flash_error'] = $e->getMessage();
        header('Location: productos.php');
        exit;
    }
    $error = $e->getMessage();
} catch (\Throwable $e) {
    if (!isset($producto)) {
        $_SESSION['flash_error'] = 'Error inesperado al cargar el producto.';
        header('Location: productos.php');
        exit;
    }
    $error = 'Error inesperado: ' . $e->getMessage();
}

$esBajo  = isset($producto) && (int)$producto['stock'] <= (int)($producto['stock_minimo'] ?? 5);
$inicial = isset($producto) ? mb_strtoupper(mb_substr($producto['nombre'], 0, 1, 'UTF-8'), 'UTF-8') : '?';
$imgRuta = isset($producto) ? Producto::rutaImagen($producto['imagen'] ?? null) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar <?= isset($producto) ? htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') : 'Producto' ?> – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        /* ── Layout de dos columnas igual que ver_producto ── */
        .product-detail-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        .product-detail-image-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            position: sticky;
            top: 1.5rem;
        }
        .product-detail-img {
            width: 220px;
            height: 220px;
            object-fit: cover;
            border-radius: var(--radius-md);
            background: var(--bg-hover);
        }
        .product-detail-avatar-lg {
            width: 220px;
            height: 220px;
            border-radius: var(--radius-md);
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            font-weight: 700;
            color: #fff;
        }
        .product-detail-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            text-align: center;
        }
        .product-detail-price {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--accent);
        }
        .product-detail-actions {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            width: 100%;
        }
        /* ── Campos dentro de los detail-cards ── */
        .detail-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .detail-field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
        }
        .detail-field-full {
            grid-column: 1 / -1;
        }
        .detail-label {
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--text-muted);
        }
        .detail-section-title {
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text-muted);
            padding-bottom: .5rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: .5rem;
            grid-column: 1 / -1;
        }
        /* Inputs dentro de tarjetas de detalle */
        .detail-field .field-input,
        .detail-field textarea.field-input {
            font-size: .875rem;
            padding: .4rem .65rem;
        }
        .detail-field textarea.field-input {
            resize: vertical;
        }
        .field-hint-sm {
            font-size: .72rem;
            color: var(--text-muted);
        }
        /* Upload block dentro del panel izquierdo */
        .left-upload-block {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: .5rem;
            padding-top: .5rem;
            border-top: 1px solid var(--border-color);
        }
        .left-upload-label {
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--text-muted);
            text-align: center;
        }
        @media (max-width: 900px) {
            .product-detail-grid { grid-template-columns: 1fr; }
            .product-detail-image-card {
                position: static;
                flex-direction: row;
                align-items: flex-start;
                flex-wrap: wrap;
            }
            .product-detail-img,
            .product-detail-avatar-lg {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
            .detail-fields { grid-template-columns: 1fr; }
        }
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
                <a href="productos.php" class="breadcrumb-item">Productos</a>
                <span class="breadcrumb-sep">›</span>
                <?php if (isset($producto)): ?>
                <a href="ver_producto.php?id=<?= (int)$producto['id'] ?>" class="breadcrumb-item"><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></a>
                <span class="breadcrumb-sep">›</span>
                <?php endif; ?>
                <span class="breadcrumb-item active">Editar</span>
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

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-info">
                <h1 class="page-title">
                    <?php if (isset($producto)): ?>
                        Editando: <?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    <?php else: ?>
                        Editar Producto
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">Modifica los datos y pulsa <strong>Guardar cambios</strong></p>
            </div>
            <div class="page-actions">
                <a href="productos.php" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Volver
                </a>
                <?php if (isset($producto)): ?>
                <a href="ver_producto.php?id=<?= (int)$producto['id'] ?>" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    Ver detalle
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert-banner alert-banner-error" style="margin-bottom:1.25rem;">
            <svg class="alert-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <?php if (isset($producto)): ?>
        <form method="POST" enctype="multipart/form-data">

        <div class="product-detail-grid">

            <!-- ══════════════════════════════════════
                 COLUMNA IZQUIERDA – preview + acciones
                 ══════════════════════════════════════ -->
            <div class="product-detail-image-card">

                <!-- Avatar / imagen actual -->
                <div id="imgPreviewWrap">
                    <?php if (!empty($producto['imagen'])): ?>
                        <img src="<?= htmlspecialchars($imgRuta, ENT_QUOTES, 'UTF-8') ?>"
                             alt="Imagen del producto"
                             class="product-detail-img"
                             id="imgPreviewEl">
                    <?php else: ?>
                        <div class="product-detail-avatar-lg" id="imgPreviewEl"><?= $inicial ?></div>
                    <?php endif; ?>
                </div>

                <div class="product-detail-name"><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="product-detail-price"><?= number_format((float)$producto['precio'], 2, ',', '.') ?> €</div>

                <div style="width:100%;text-align:center;">
                    <?php if ($esBajo): ?>
                        <span class="status-pill status-pill-warning">Stock bajo</span>
                    <?php else: ?>
                        <span class="status-pill status-pill-success">Disponible</span>
                    <?php endif; ?>
                    <div style="font-size:.78rem;color:var(--text-muted);margin-top:.35rem;">
                        Stock: <strong><?= (int)$producto['stock'] ?></strong> uds.
                    </div>
                </div>

                <!-- Upload de imagen directamente en panel izquierdo -->
                <div class="left-upload-block">
                    <span class="left-upload-label">Cambiar imagen</span>
                    <input class="field-input" type="file" id="imagen" name="imagen"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           style="cursor:pointer;font-size:.8rem;">
                    <span class="field-hint-sm" style="text-align:center;">JPG, PNG o WebP · Máx. 2 MB</span>
                    <?php if (!empty($producto['imagen'])): ?>
                    <label style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;cursor:pointer;justify-content:center;">
                        <input type="checkbox" name="eliminar_imagen" value="1"> Eliminar imagen actual
                    </label>
                    <?php endif; ?>
                </div>

                <!-- Acciones -->
                <div class="product-detail-actions">
                    <button type="submit" class="btn-primary" style="text-align:center;justify-content:center;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Guardar cambios
                    </button>
                    <a href="ver_producto.php?id=<?= (int)$producto['id'] ?>" class="btn-ghost" style="text-align:center;justify-content:center;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                        Ver detalle
                    </a>
                    <a href="movimientos.php?producto_id=<?= (int)$producto['id'] ?>" class="btn-ghost" style="text-align:center;justify-content:center;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                        </svg>
                        Ver movimientos
                    </a>
                    <a href="eliminar_producto.php?id=<?= (int)$producto['id'] ?>" class="btn-danger"
                       style="text-align:center;justify-content:center;"
                       onclick="return confirm('¿Eliminar este producto? Esta acción no se puede deshacer.')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>
                        </svg>
                        Eliminar producto
                    </a>
                </div>
            </div>

            <!-- ══════════════════════════════════════
                 COLUMNA DERECHA – secciones del form
                 ══════════════════════════════════════ -->
            <div style="display:flex;flex-direction:column;gap:1.5rem;">

                <!-- Datos básicos -->
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Datos básicos</div>

                            <div class="detail-field detail-field-full">
                                <label class="detail-label" for="nombre">Nombre <span style="color:var(--danger,#ef4444)">*</span></label>
                                <input class="field-input" type="text" id="nombre" name="nombre" required
                                       placeholder="Nombre del producto"
                                       value="<?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="detail-field detail-field-full">
                                <label class="detail-label" for="descripcion">Descripción corta</label>
                                <textarea class="field-input" id="descripcion" name="descripcion" rows="2"
                                          placeholder="Resumen breve del producto…"><?= htmlspecialchars($producto['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="detail-field detail-field-full">
                                <label class="detail-label" for="descripcion_larga">Descripción larga</label>
                                <textarea class="field-input" id="descripcion_larga" name="descripcion_larga" rows="4"
                                          placeholder="Descripción técnica detallada, especificaciones…"><?= htmlspecialchars($producto['descripcion_larga'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Precio y stock -->
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Precio y stock</div>

                            <div class="detail-field">
                                <label class="detail-label" for="precio">Precio (€) <span style="color:var(--danger,#ef4444)">*</span></label>
                                <input class="field-input" type="number" id="precio" name="precio"
                                       step="0.01" min="0.01" required placeholder="0.00"
                                       value="<?= htmlspecialchars((string)$producto['precio'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="field-hint-sm">Precio de venta al público</span>
                            </div>

                            <div class="detail-field">
                                <label class="detail-label" for="stock_minimo">Stock mínimo</label>
                                <input class="field-input" type="number" id="stock_minimo" name="stock_minimo"
                                       min="0" placeholder="5"
                                       value="<?= (int)($producto['stock_minimo'] ?? 5) ?>">
                                <span class="field-hint-sm">Alerta cuando baje de este valor</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Identificación -->
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Identificación</div>

                            <div class="detail-field">
                                <label class="detail-label" for="categoria_id">Categoría</label>
                                <select class="field-input" id="categoria_id" name="categoria_id">
                                    <option value="">Sin categoría</option>
                                    <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)$producto['categoria_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="detail-field">
                                <label class="detail-label" for="marca_id">Marca</label>
                                <select class="field-input" id="marca_id" name="marca_id">
                                    <option value="">Sin marca</option>
                                    <?php foreach ($marcas as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>" <?= (int)$m['id'] === (int)($producto['marca_id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="detail-field">
                                <label class="detail-label" for="codigo_ref">Código de referencia</label>
                                <input class="field-input" type="text" id="codigo_ref" name="codigo_ref"
                                       placeholder="Ej. REF-001"
                                       value="<?= htmlspecialchars($producto['codigo_ref'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="detail-field">
                                <label class="detail-label" for="codigo_barras">Código de barras / EAN</label>
                                <input class="field-input" type="text" id="codigo_barras" name="codigo_barras"
                                       placeholder="Ej. 8412345678901"
                                       value="<?= htmlspecialchars($producto['codigo_barras'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="detail-field detail-field-full">
                                <label class="detail-label" for="ubicacion">Ubicación en taller</label>
                                <input class="field-input" type="text" id="ubicacion" name="ubicacion"
                                       placeholder="Ej. Estante A3, Cajón 2…"
                                       value="<?= htmlspecialchars($producto['ubicacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dimensiones técnicas -->
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Dimensiones técnicas</div>

                            <div class="detail-field">
                                <label class="detail-label" for="peso">Peso (g)</label>
                                <input class="field-input" type="number" id="peso" name="peso" min="0" placeholder="Ej. 500"
                                       value="<?= htmlspecialchars((string)($producto['peso'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="detail-field">
                                <label class="detail-label" for="capacidad">Capacidad (ml)</label>
                                <input class="field-input" type="number" id="capacidad" name="capacidad" min="0" placeholder="Ej. 1000"
                                       value="<?= htmlspecialchars((string)($producto['capacidad'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="detail-field">
                                <label class="detail-label" for="longitud">Longitud (mm)</label>
                                <input class="field-input" type="number" id="longitud" name="longitud" min="0" placeholder="Ej. 800"
                                       value="<?= htmlspecialchars((string)($producto['longitud'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="detail-field">
                                <label class="detail-label" for="anchura">Anchura (mm)</label>
                                <input class="field-input" type="number" id="anchura" name="anchura" min="0" placeholder="Ej. 340"
                                       value="<?= htmlspecialchars((string)($producto['anchura'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="detail-field">
                                <label class="detail-label" for="diametro">Diámetro (mm)</label>
                                <input class="field-input" type="number" id="diametro" name="diametro" min="0" step="0.01" placeholder="Ej. 310.00"
                                       value="<?= htmlspecialchars((string)($producto['diametro'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Proveedor -->
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Proveedor</div>

                            <div class="detail-field">
                                <label class="detail-label" for="proveedor_id">Proveedor vinculado</label>
                                <select class="field-input" id="proveedor_id" name="proveedor_id">
                                    <option value="">— Sin proveedor vinculado —</option>
                                    <?php foreach ($proveedoresDisponibles as $pv): ?>
                                    <option value="<?= (int)$pv['id'] ?>" <?= (int)$pv['id'] === $proveedorIdActual ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pv['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="detail-field">
                                <label class="detail-label" for="proveedor">Proveedor (texto libre)</label>
                                <input class="field-input" type="text" id="proveedor" name="proveedor"
                                       placeholder="Nombre del proveedor"
                                       value="<?= htmlspecialchars($producto['proveedor'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="detail-field detail-field-full">
                                <label class="detail-label" for="url_proveedor">URL del proveedor</label>
                                <input class="field-input" type="url" id="url_proveedor" name="url_proveedor"
                                       placeholder="https://proveedor.com/producto"
                                       value="<?= htmlspecialchars($producto['url_proveedor'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Motos compatibles -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Motos compatibles</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($modelos)): ?>
                            <p style="color:#6b7280;font-style:italic;">No hay modelos registrados. <a href="modelos_moto.php">Añadir modelos</a></p>
                        <?php else: ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.75rem;">
                            <?php foreach ($modelos as $m): ?>
                            <div style="padding:.5rem;border:1px solid var(--border-color,#e5e7eb);border-radius:6px;">
                                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.875rem;font-weight:500;">
                                    <input type="checkbox"
                                           name="modelos_compatibles[]"
                                           value="<?= (int)$m['id'] ?>"
                                           <?= in_array((int)$m['id'], $modelosActuales, true) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars("{$m['marca']} {$m['modelo']}", ENT_QUOTES, 'UTF-8') ?>
                                    <span style="color:#6b7280;font-size:.82em;">(<?= (int)$m['anio_desde'] ?>–<?= $m['anio_hasta'] ? (int)$m['anio_hasta'] : 'actual' ?>)</span>
                                </label>
                                <input type="text"
                                       name="notas_compatibilidad[<?= (int)$m['id'] ?>]"
                                       placeholder="Notas (opcional)"
                                       style="width:100%;margin-top:.25rem;padding:.25rem .5rem;border:1px solid var(--border-color,#d1d5db);border-radius:4px;font-size:.82em;background:var(--bg-input,#fff);color:var(--text-primary);"
                                       value="<?= htmlspecialchars($notasActuales[$m['id']] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Botones de acción al pie de la columna derecha -->
                <div style="display:flex;gap:.75rem;justify-content:flex-end;padding-bottom:.5rem;">
                    <a href="productos.php" class="btn-ghost">Cancelar</a>
                    <button type="submit" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Guardar cambios
                    </button>
                </div>

            </div><!-- /columna derecha -->
        </div><!-- /product-detail-grid -->

        </form>
        <?php endif; ?>

    </main>
</div>

<script src="js/app.js"></script>
<script>
/**
 * Preview en tiempo real de la imagen seleccionada.
 * @author Carlitos6712
 */
(function () {
    const input = document.getElementById('imagen');
    if (!input) return;
    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            const wrap = document.getElementById('imgPreviewWrap');
            if (!wrap) return;
            wrap.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="product-detail-img">';
        };
        reader.readAsDataURL(file);
    });
}());
</script>
</body>
</html>
