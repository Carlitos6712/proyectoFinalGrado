<?php
/**
 * Página de detalle de un producto.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';
require_once __DIR__ . '/includes/Movimiento.php';
require_once __DIR__ . '/includes/ModeloMoto.php';
require_once __DIR__ . '/includes/Proveedor.php';

$stockBajoCount = 0;
$movimientos    = [];

try {
    $productoModel  = new Producto();
    $movimientoModel = new Movimiento();
    $stockBajoCount = count($productoModel->filtrarStockBajo());

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        header('Location: productos.php');
        exit;
    }

    $producto    = $productoModel->obtener($id);
    $movimientos = $movimientoModel->listarPorProductoPaginado($id, 1, 10);

    $pdo = Database::getInstance();
    $stmtCompat = $pdo->prepare(
        "SELECT mm.marca, mm.modelo, mm.anio_desde, mm.anio_hasta, c.notas
         FROM compatibilidades c
         JOIN modelos_moto mm ON mm.id = c.modelo_id
         WHERE c.producto_id = :pid
         ORDER BY mm.marca, mm.modelo"
    );
    $stmtCompat->execute([':pid' => $id]);
    $compatibilidades = $stmtCompat->fetchAll(PDO::FETCH_ASSOC);

    // Cargar datos del proveedor vinculado (Fase 12)
    $proveedorVinculado = null;
    if (!empty($producto['proveedor_id'])) {
        try {
            $proveedorVinculado = (new Proveedor($pdo))->obtener((int)$producto['proveedor_id']);
        } catch (\Throwable $e) {
            // Si no se encuentra el proveedor, ignorar silenciosamente
        }
    }
} catch (AppException $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: productos.php');
    exit;
} catch (\Throwable $e) {
    $_SESSION['flash_error'] = 'Error al cargar el producto.';
    header('Location: productos.php');
    exit;
}

$esBajo   = (int)$producto['stock'] <= (int)($producto['stock_minimo'] ?? 5);
$inicial  = mb_strtoupper(mb_substr($producto['nombre'], 0, 1, 'UTF-8'), 'UTF-8');
$imgRuta  = Producto::rutaImagen($producto['imagen'] ?? null);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?> – es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
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
        .detail-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .detail-field {
            display: flex;
            flex-direction: column;
            gap: .25rem;
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
        .detail-value {
            font-size: .9rem;
            color: var(--text-primary);
            word-break: break-word;
        }
        .detail-value-empty {
            color: var(--text-muted);
            font-style: italic;
        }
        .detail-value a {
            color: var(--accent);
            text-decoration: none;
        }
        .detail-value a:hover { text-decoration: underline; }
        .detail-section-title {
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text-muted);
            padding-bottom: .5rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1rem;
            grid-column: 1 / -1;
        }
        .stock-detail-block {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stock-detail-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }
        .stock-detail-number.low { color: var(--danger, #ef4444); }
        .stock-detail-number.ok  { color: var(--success, #22c55e); }
        .product-detail-actions {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            width: 100%;
        }
        @media (max-width: 900px) {
            .product-detail-grid {
                grid-template-columns: 1fr;
            }
            .product-detail-image-card {
                position: static;
                flex-direction: row;
                align-items: flex-start;
            }
            .product-detail-img,
            .product-detail-avatar-lg {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
            .detail-fields {
                grid-template-columns: 1fr;
            }
        }
        .action-btn-gray {
            background: var(--bg-hover);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .action-btn-gray:hover {
            background: var(--border-color);
            color: var(--text-primary);
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
                <span class="breadcrumb-item active"><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
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
                <h1 class="page-title"><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="page-subtitle">Detalle completo del producto</p>
            </div>
            <div class="page-actions">
                <a href="productos.php" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Volver
                </a>
                <a href="editar_producto.php?id=<?= (int)$producto['id'] ?>" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Editar
                </a>
            </div>
        </div>

        <div class="product-detail-grid">

            <!-- Columna izquierda: imagen + acciones rápidas -->
            <div class="product-detail-image-card">
                <?php if (!empty($producto['imagen'])): ?>
                    <img src="<?= htmlspecialchars($imgRuta, ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                         class="product-detail-img">
                <?php else: ?>
                    <div class="product-detail-avatar-lg"><?= $inicial ?></div>
                <?php endif; ?>

                <div class="product-detail-name"><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="product-detail-price"><?= number_format((float)$producto['precio'], 2, ',', '.') ?> €</div>

                <div style="width:100%;text-align:center;">
                    <?php if ($esBajo): ?>
                        <span class="status-pill status-pill-warning">Stock bajo</span>
                    <?php else: ?>
                        <span class="status-pill status-pill-success">Disponible</span>
                    <?php endif; ?>
                </div>

                <div class="product-detail-actions">
                    <a href="editar_producto.php?id=<?= (int)$producto['id'] ?>" class="btn-primary" style="text-align:center;justify-content:center;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Editar producto
                    </a>
                    <a href="movimientos.php?producto_id=<?= (int)$producto['id'] ?>" class="btn-ghost" style="text-align:center;justify-content:center;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                        </svg>
                        Ver movimientos
                    </a>
                    <a href="eliminar_producto.php?id=<?= (int)$producto['id'] ?>" class="btn-danger" style="text-align:center;justify-content:center;" data-confirm="delete">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>
                        </svg>
                        Eliminar
                    </a>
                </div>
            </div>

            <!-- Columna derecha: campos del producto -->
            <div style="display:flex;flex-direction:column;gap:1.5rem;">

                <!-- Bloque stock -->
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Stock</div>
                            <div class="detail-field">
                                <span class="detail-label">Stock actual</span>
                                <div class="stock-detail-block">
                                    <span class="stock-detail-number <?= $esBajo ? 'low' : 'ok' ?>"><?= (int)$producto['stock'] ?></span>
                                    <div>
                                        <div class="stock-bar-track" style="width:120px">
                                            <?php
                                            $stockMax = max((int)($producto['stock_minimo'] ?? 5) * 3, 1);
                                            $stockPct = min(100, round((int)$producto['stock'] / $stockMax * 100));
                                            ?>
                                            <div class="stock-bar <?= $esBajo ? 'stock-bar-low' : '' ?>" style="width:<?= $stockPct ?>%"></div>
                                        </div>
                                        <span style="font-size:.75rem;color:var(--text-muted)">Mín. <?= (int)($producto['stock_minimo'] ?? 5) ?> uds.</span>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-field">
                                <span class="detail-label">Stock mínimo</span>
                                <span class="detail-value"><?= (int)($producto['stock_minimo'] ?? 5) ?> uds.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bloque identificación -->
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Identificación</div>

                            <div class="detail-field">
                                <span class="detail-label">Categoría</span>
                                <span class="detail-value">
                                    <?php if (!empty($producto['categoria_nombre'])): ?>
                                        <span class="category-pill"><?= htmlspecialchars($producto['categoria_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <span class="detail-value-empty">Sin categoría</span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="detail-field">
                                <span class="detail-label">Marca</span>
                                <span class="detail-value <?= empty($producto['marca_nombre']) ? 'detail-value-empty' : '' ?>">
                                    <?php
                                    $marcaNombre = $producto['marca_nombre'] ?? $producto['marca'] ?? '';
                                    echo $marcaNombre !== '' ? htmlspecialchars($marcaNombre, ENT_QUOTES, 'UTF-8') : '—';
                                    ?>
                                </span>
                            </div>

                            <div class="detail-field">
                                <span class="detail-label">Código de Referencia</span>
                                <span class="detail-value <?= empty($producto['codigo_ref']) ? 'detail-value-empty' : '' ?>">
                                    <?= !empty($producto['codigo_ref']) ? htmlspecialchars($producto['codigo_ref'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                </span>
                            </div>

                            <div class="detail-field">
                                <span class="detail-label">Código de Barras / EAN</span>
                                <span class="detail-value <?= empty($producto['codigo_barras']) ? 'detail-value-empty' : '' ?>">
                                    <?= !empty($producto['codigo_barras']) ? htmlspecialchars($producto['codigo_barras'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                </span>
                            </div>

                            <div class="detail-field">
                                <span class="detail-label">Ubicación en Taller</span>
                                <span class="detail-value <?= empty($producto['ubicacion']) ? 'detail-value-empty' : '' ?>">
                                    <?= !empty($producto['ubicacion']) ? htmlspecialchars($producto['ubicacion'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                </span>
                            </div>

                            <div class="detail-field">
                                <span class="detail-label">Precio</span>
                                <span class="detail-value" style="font-weight:600;"><?= number_format((float)$producto['precio'], 2, ',', '.') ?> €</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bloque dimensiones (solo si hay algún valor) -->
                <?php
                $tieneDimensiones = !empty($producto['peso']) || !empty($producto['capacidad'])
                    || !empty($producto['longitud']) || !empty($producto['anchura']) || !empty($producto['diametro']);
                ?>
                <?php if ($tieneDimensiones): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Dimensiones técnicas</div>
                            <?php if (!empty($producto['peso'])): ?>
                            <div class="detail-field">
                                <span class="detail-label">Peso</span>
                                <span class="detail-value"><?= number_format((int)$producto['peso']) ?> g</span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($producto['capacidad'])): ?>
                            <div class="detail-field">
                                <span class="detail-label">Capacidad</span>
                                <span class="detail-value"><?= number_format((int)$producto['capacidad']) ?> ml</span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($producto['longitud'])): ?>
                            <div class="detail-field">
                                <span class="detail-label">Longitud</span>
                                <span class="detail-value"><?= number_format((int)$producto['longitud']) ?> mm</span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($producto['anchura'])): ?>
                            <div class="detail-field">
                                <span class="detail-label">Anchura</span>
                                <span class="detail-value"><?= number_format((int)$producto['anchura']) ?> mm</span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($producto['diametro'])): ?>
                            <div class="detail-field">
                                <span class="detail-label">Diámetro</span>
                                <span class="detail-value"><?= number_format((float)$producto['diametro'], 2) ?> mm</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bloque proveedor -->
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Proveedor</div>

                            <?php if ($proveedorVinculado): ?>
                            <div class="detail-field">
                                <span class="detail-label">Proveedor vinculado</span>
                                <span class="detail-value">
                                    <a href="proveedores.php?ver_productos=<?= (int)$proveedorVinculado['id'] ?>">
                                        <?= htmlspecialchars($proveedorVinculado['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </span>
                            </div>
                            <?php if (!empty($proveedorVinculado['email'])): ?>
                            <div class="detail-field">
                                <span class="detail-label">Email proveedor</span>
                                <span class="detail-value">
                                    <a href="mailto:<?= htmlspecialchars($proveedorVinculado['email'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($proveedorVinculado['email'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($proveedorVinculado['telefono'])): ?>
                            <div class="detail-field">
                                <span class="detail-label">Teléfono proveedor</span>
                                <span class="detail-value"><?= htmlspecialchars($proveedorVinculado['telefono'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="detail-field">
                                <span class="detail-label">Proveedor</span>
                                <span class="detail-value <?= empty($producto['proveedor']) ? 'detail-value-empty' : '' ?>">
                                    <?= !empty($producto['proveedor']) ? htmlspecialchars($producto['proveedor'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <div class="detail-field">
                                <span class="detail-label">URL del Proveedor</span>
                                <span class="detail-value">
                                    <?php if (!empty($producto['url_proveedor'])): ?>
                                        <a href="<?= htmlspecialchars($producto['url_proveedor'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                                            Ver en proveedor ↗
                                        </a>
                                    <?php else: ?>
                                        <span class="detail-value-empty">—</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bloque descripciones -->
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Descripción</div>

                            <?php if (!empty($producto['descripcion'])): ?>
                            <div class="detail-field detail-field-full">
                                <span class="detail-label">Descripción corta</span>
                                <span class="detail-value"><?= nl2br(htmlspecialchars($producto['descripcion'], ENT_QUOTES, 'UTF-8')) ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($producto['descripcion_larga'])): ?>
                            <div class="detail-field detail-field-full">
                                <span class="detail-label">Descripción larga</span>
                                <span class="detail-value"><?= nl2br(htmlspecialchars($producto['descripcion_larga'], ENT_QUOTES, 'UTF-8')) ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if (empty($producto['descripcion']) && empty($producto['descripcion_larga'])): ?>
                            <div class="detail-field detail-field-full">
                                <span class="detail-value detail-value-empty">Sin descripción</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Bloque fechas -->
                <div class="card">
                    <div class="card-body">
                        <div class="detail-fields">
                            <div class="detail-section-title">Registro</div>
                            <div class="detail-field">
                                <span class="detail-label">Creado el</span>
                                <span class="detail-value"><?= !empty($producto['created_at']) ? date('d/m/Y H:i', strtotime($producto['created_at'])) : '—' ?></span>
                            </div>
                            <div class="detail-field">
                                <span class="detail-label">Última modificación</span>
                                <span class="detail-value"><?= !empty($producto['updated_at']) ? date('d/m/Y H:i', strtotime($producto['updated_at'])) : '—' ?></span>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Motos compatibles -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Motos compatibles</h3></div>
                    <div class="card-body">
                    <?php if (empty($compatibilidades)): ?>
                        <p class="text-muted" style="color:#6b7280;font-style:italic;">No hay modelos de moto registrados para este producto.</p>
                    <?php else: ?>
                        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0.5rem;">
                        <?php foreach ($compatibilidades as $c): ?>
                            <li style="padding:0.4rem 0;border-bottom:1px solid var(--border-color,#f3f4f6);">
                                <strong><?= htmlspecialchars($c['marca'] . ' ' . $c['modelo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span style="color:#6b7280;font-size:0.9em;">&nbsp;(<?= (int)$c['anio_desde'] ?>–<?= $c['anio_hasta'] ? (int)$c['anio_hasta'] : 'actual' ?>)</span>
                                <?php if (!empty($c['notas'])): ?>
                                    &nbsp;— <em style="color:#4b5563;"><?= htmlspecialchars($c['notas'], ENT_QUOTES, 'UTF-8') ?></em>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    </div>
                </div>

                <!-- Últimos movimientos -->
                <?php if (!empty($movimientos)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" style="font-size:.95rem;">Últimos movimientos</h3>
                        <a href="movimientos.php?producto_id=<?= (int)$producto['id'] ?>" class="btn-ghost" style="font-size:.8rem;padding:.3rem .75rem;">Ver todos</a>
                    </div>
                    <div class="card-body" style="padding:0;">
                        <table class="data-table data-table-mini">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($mov['fecha'])) ?></td>
                                    <td>
                                        <?php if ($mov['tipo'] === 'entrada'): ?>
                                            <span class="status-pill status-pill-success">Entrada</span>
                                        <?php else: ?>
                                            <span class="status-pill status-pill-warning">Salida</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight:600;"><?= (int)$mov['cantidad'] ?></td>
                                    <td><?= htmlspecialchars($mov['observaciones'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

<script src="js/app.js"></script>
</body>
</html>
