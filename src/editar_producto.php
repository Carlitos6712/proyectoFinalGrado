<?php
/**
 * Formulario de edición de un producto existente.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @author   miguelrechefdez
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

    $producto   = $productoModel->obtener($id);
    $categorias = $categoriaModel->listar();
    $marcas    = $marcaModel->listar();
    $modelos   = $modeloMotoModel->listarParaSelect();
    $proveedorModel        = new Proveedor($pdo);
    $proveedoresDisponibles = $proveedorModel->listar(true);
    $proveedorIdActual     = (int)($producto['proveedor_id'] ?? 0);

    // Cargar compatibilidades actuales del producto
    $pdo = Database::getInstance();
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
        $pdo = Database::getInstance();
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
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
            <div class="user-avatar-sm">CV</div>
            <div class="sidebar-user-info">
                <span class="user-name-sm">Carlos Vico</span>
                <span class="user-role">Administrador</span>
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
                <a href="productos.php" class="breadcrumb-item">Productos</a>
                <span class="breadcrumb-sep">›</span>
                <span class="breadcrumb-item active">Editar Producto</span>
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
                <h1 class="page-title">Editar Producto</h1>
                <p class="page-subtitle">
                    <?php if (isset($producto)): ?>
                        Modificando: <strong><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php else: ?>
                        Modifica los datos del producto seleccionado
                    <?php endif; ?>
                </p>
            </div>
            <div class="page-actions">
                <a href="productos.php" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Volver
                </a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert-banner alert-banner-error">
            <svg class="alert-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <?php if (isset($producto)): ?>
        <div class="card card-form">
            <div class="card-header">
                <h2 class="card-title">Datos del Producto</h2>
                <p class="card-subtitle">Los campos marcados con <span class="field-required">*</span> son obligatorios</p>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="form-grid-wrapper">
                    <div class="form-grid">

                        <div class="form-field form-field-full">
                            <label class="field-label" for="nombre">Nombre <span class="field-required">*</span></label>
                            <input class="field-input" type="text" id="nombre" name="nombre" required
                                   placeholder="Nombre del producto"
                                   value="<?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-field form-field-full">
                            <label class="field-label" for="descripcion">Descripción corta</label>
                            <textarea class="field-input field-textarea" id="descripcion" name="descripcion" rows="2"
                                      placeholder="Resumen breve del producto…"><?= htmlspecialchars($producto['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="form-field form-field-full">
                            <label class="field-label" for="descripcion_larga">Descripción larga</label>
                            <textarea class="field-input field-textarea" id="descripcion_larga" name="descripcion_larga" rows="5"
                                      placeholder="Descripción técnica detallada, especificaciones, compatibilidades…"><?= htmlspecialchars($producto['descripcion_larga'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="precio">Precio (€) <span class="field-required">*</span></label>
                            <input class="field-input" type="number" id="precio" name="precio" step="0.01" min="0" required
                                   placeholder="0.00"
                                   value="<?= htmlspecialchars((string)$producto['precio'], ENT_QUOTES, 'UTF-8') ?>">
                            <span class="field-hint">Precio de venta al público</span>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="stock_minimo">Stock Mínimo</label>
                            <input class="field-input" type="number" id="stock_minimo" name="stock_minimo" min="0"
                                   placeholder="5"
                                   value="<?= (int)($producto['stock_minimo'] ?? 5) ?>">
                            <span class="field-hint">Alerta cuando el stock baje de este valor</span>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="codigo_ref">Código de Referencia</label>
                            <input class="field-input" type="text" id="codigo_ref" name="codigo_ref"
                                   placeholder="Ej. REF-001"
                                   value="<?= htmlspecialchars($producto['codigo_ref'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <span class="field-hint">Referencia interna o del fabricante</span>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="codigo_barras">Código de Barras / EAN</label>
                            <input class="field-input" type="text" id="codigo_barras" name="codigo_barras"
                                   placeholder="Ej. 8412345678901"
                                   value="<?= htmlspecialchars($producto['codigo_barras'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="proveedor">Proveedor (texto libre)</label>
                            <input class="field-input" type="text" id="proveedor" name="proveedor"
                                   placeholder="Nombre del proveedor"
                                   value="<?= htmlspecialchars($producto['proveedor'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="proveedor_id">Proveedor vinculado</label>
                            <select class="field-input" id="proveedor_id" name="proveedor_id">
                                <option value="">— Sin proveedor vinculado —</option>
                                <?php foreach ($proveedoresDisponibles as $pv): ?>
                                <option value="<?= (int)$pv['id'] ?>" <?= (int)$pv['id'] === $proveedorIdActual ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pv['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="url_proveedor">URL del Proveedor</label>
                            <input class="field-input" type="url" id="url_proveedor" name="url_proveedor"
                                   placeholder="https://proveedor.com/producto"
                                   value="<?= htmlspecialchars($producto['url_proveedor'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="ubicacion">Ubicación en Taller</label>
                            <input class="field-input" type="text" id="ubicacion" name="ubicacion"
                                   placeholder="Ej. Estante A3, Cajón 2…"
                                   value="<?= htmlspecialchars($producto['ubicacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <span class="field-hint">Dónde está físicamente en el taller</span>
                        </div>

                        <!-- Dimensiones técnicas -->
                        <div class="form-field">
                            <label class="field-label" for="peso">Peso (g)</label>
                            <input class="field-input" type="number" id="peso" name="peso" min="0"
                                   placeholder="Ej. 500"
                                   value="<?= htmlspecialchars((string)($producto['peso'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="field-hint">Peso en gramos</span>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="capacidad">Capacidad (ml)</label>
                            <input class="field-input" type="number" id="capacidad" name="capacidad" min="0"
                                   placeholder="Ej. 1000"
                                   value="<?= htmlspecialchars((string)($producto['capacidad'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="field-hint">Capacidad en mililitros</span>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="longitud">Longitud (mm)</label>
                            <input class="field-input" type="number" id="longitud" name="longitud" min="0"
                                   placeholder="Ej. 800"
                                   value="<?= htmlspecialchars((string)($producto['longitud'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="field-hint">Longitud en milímetros</span>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="anchura">Anchura (mm)</label>
                            <input class="field-input" type="number" id="anchura" name="anchura" min="0"
                                   placeholder="Ej. 340"
                                   value="<?= htmlspecialchars((string)($producto['anchura'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="field-hint">Anchura en milímetros</span>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="diametro">Diámetro (mm)</label>
                            <input class="field-input" type="number" id="diametro" name="diametro" min="0" step="0.01"
                                   placeholder="Ej. 310.00"
                                   value="<?= htmlspecialchars((string)($producto['diametro'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="field-hint">Diámetro en milímetros</span>
                        </div>

                        <div class="form-field form-field-full">
                            <label class="field-label">Imagen del Producto</label>
                            <div class="image-upload-wrap">
                                <div class="image-preview" id="imagePreview">
                                    <?php $imgActual = $producto['imagen'] ?? null; ?>
                                    <?php if ($imgActual): ?>
                                        <img src="<?= htmlspecialchars(\Producto::rutaImagen($imgActual), ENT_QUOTES, 'UTF-8') ?>"
                                             alt="Imagen actual"
                                             style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
                                    <?php else: ?>
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-muted)">
                                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                        <span style="font-size:.8rem;color:var(--text-muted);margin-top:.5rem;">Sin imagen</span>
                                    <?php endif; ?>
                                </div>
                                <div class="image-upload-info">
                                    <input class="field-input" type="file" id="imagen" name="imagen"
                                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                           style="cursor:pointer;">
                                    <span class="field-hint">JPG, PNG o WebP · Máximo 2 MB. Reemplazará la imagen actual.</span>
                                    <?php if ($imgActual): ?>
                                    <label style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem;font-size:.875rem;cursor:pointer;">
                                        <input type="checkbox" name="eliminar_imagen" value="1"> Eliminar imagen actual
                                    </label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="categoria_id">Categoría</label>
                            <select class="field-input field-select" id="categoria_id" name="categoria_id">
                                <option value="">Sin categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"
                                        <?= (int)$cat['id'] === (int)$producto['categoria_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="marca_id">Marca</label>
                            <select class="field-input field-select" id="marca_id" name="marca_id">
                                <option value="">Sin marca</option>
                                <?php foreach ($marcas as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>"
                                        <?= (int)$m['id'] === (int)($producto['marca_id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>


                    <div class="form-section" style="margin-top:1.5rem;">
                        <h3 class="form-section-title" style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Motos compatibles</h3>
                        <?php if (empty($modelos)): ?>
                            <p class="text-muted" style="color:#6b7280;">No hay modelos de moto registrados. <a href="modelos_moto.php">Añadir modelos</a></p>
                        <?php else: ?>
                        <div class="compat-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0.75rem;">
                            <?php foreach ($modelos as $m): ?>
                            <div class="compat-item" style="padding:0.5rem;border:1px solid var(--border-color, #e5e7eb);border-radius:6px;">
                                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-weight:500;">
                                    <input type="checkbox"
                                           name="modelos_compatibles[]"
                                           value="<?= (int)$m['id'] ?>"
                                           <?= in_array((int)$m['id'], $modelosActuales, true) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars("{$m['marca']} {$m['modelo']}", ENT_QUOTES, 'UTF-8') ?>
                                    <span style="color:#6b7280;font-size:0.85em;">(<?= (int)$m['anio_desde'] ?>–<?= $m['anio_hasta'] ? (int)$m['anio_hasta'] : 'actual' ?>)</span>
                                </label>
                                <input type="text"
                                       name="notas_compatibilidad[<?= (int)$m['id'] ?>]"
                                       placeholder="Notas (opcional)"
                                       style="width:100%;margin-top:0.25rem;padding:0.25rem 0.5rem;border:1px solid var(--border-color,#d1d5db);border-radius:4px;font-size:0.85em;"
                                       value="<?= htmlspecialchars($notasActuales[$m['id']] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer">
                        <a href="productos.php" class="btn-ghost">Cancelar</a>
                        <button type="submit" class="btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="js/app.js"></script>
</body>
</html>
