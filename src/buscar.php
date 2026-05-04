<?php
/**
 * Búsqueda y filtros de productos del inventario.
 *
 * Endpoint AJAX: devuelve filas <tr> del tbody para el dashboard.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @author   miguelrechefdez
 * @version  1.0.0
 */
require_once __DIR__ . '/includes/AppException.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Producto.php';

$termino     = trim($_GET['q']           ?? '');
$categoriaId = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT) ?: null;
$soloAjax    = isset($_GET['ajax']);

try {
    $modelo    = new Producto();
    $productos = $termino !== '' || $categoriaId !== null
                 ? $modelo->buscar($termino, $categoriaId)
                 : $modelo->listar();
} catch (\Throwable $e) {
    if ($soloAjax) {
        echo '<tr><td colspan="8" class="td-empty">Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        exit;
    }
    $productos = [];
    $error     = $e->getMessage();
}

// Si la petición es AJAX, devolver solo el HTML del tbody con el nuevo layout
if ($soloAjax) {
    foreach ($productos as $p):
        $esBajo   = (int)$p['stock'] <= (int)($p['stock_minimo'] ?? 5);
        $stockMax = max((int)($p['stock_minimo'] ?? 5) * 3, 1);
        $stockPct = min(100, round((int)$p['stock'] / $stockMax * 100));
        $inicial  = mb_strtoupper(mb_substr($p['nombre'], 0, 1, 'UTF-8'), 'UTF-8');
    ?>
    <tr class="<?= $esBajo ? 'row-low-stock' : '' ?>">
        <td class="td-check"><input type="checkbox" aria-label="Seleccionar fila"></td>
        <td>
            <span class="ref-code"><?= htmlspecialchars($p['codigo_ref'] ?? '–', ENT_QUOTES, 'UTF-8') ?></span>
        </td>
        <td>
            <div class="product-cell">
                <div class="product-avatar"><?= $inicial ?></div>
                <span class="product-name"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </td>
        <td>
            <span class="category-pill"><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría', ENT_QUOTES, 'UTF-8') ?></span>
        </td>
        <td class="td-price"><?= number_format((float)$p['precio'], 2, ',', '.') ?> €</td>
        <td>
            <div class="stock-cell">
                <span class="stock-value <?= $esBajo ? 'stock-value-low' : '' ?>"><?= (int)$p['stock'] ?></span>
                <div class="stock-bar-track">
                    <div class="stock-bar <?= $esBajo ? 'stock-bar-low' : '' ?>" style="width:<?= $stockPct ?>%"></div>
                </div>
            </div>
        </td>
        <td>
            <?php if ($esBajo): ?>
                <span class="status-pill status-pill-warning">Stock bajo</span>
            <?php else: ?>
                <span class="status-pill status-pill-success">Disponible</span>
            <?php endif; ?>
        </td>
        <td class="td-actions">
            <a href="movimientos.php?producto_id=<?= (int)$p['id'] ?>" class="action-btn action-btn-blue" title="Ver movimientos">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                </svg>
            </a>
            <a href="editar_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-green" title="Editar producto">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
            </a>
            <a href="eliminar_producto.php?id=<?= (int)$p['id'] ?>" class="action-btn action-btn-red" title="Eliminar producto" data-confirm="delete">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                </svg>
            </a>
        </td>
    </tr>
    <?php endforeach;
    if (empty($productos)) {
        echo '<tr><td colspan="8" class="td-empty">No se encontraron productos.</td></tr>';
    }
    exit;
}

// Página standalone: redirigir a index con parámetros
header('Location: index.php');
exit;
