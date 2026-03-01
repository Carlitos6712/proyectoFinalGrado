<?php
require_once 'includes/Producto.php';
$producto = new Producto();
$productos = $producto->listar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario de Motos</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <div class="container">
        <h1>🏍️ Gestión de Inventario - Accesorios para Motos</h1>

        <div class="navbar">
            <span>Sistema de Inventario</span>
            <div>
                <a href="index.php">Inicio</a>
                <a href="nuevo_producto.php" class="btn btn-primary btn-sm">➕ Nuevo Producto</a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Categoría</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $prod): ?>
                <tr>
                    <td><?= $prod['id'] ?></td>
                    <td><?= htmlspecialchars($prod['nombre']) ?></td>
                    <td><?= htmlspecialchars($prod['descripcion']) ?></td>
                    <td>$<?= number_format($prod['precio'], 2) ?></td>
                    <td><?= $prod['stock'] ?></td>
                    <td><?= htmlspecialchars($prod['categoria_nombre'] ?? 'Sin categoría') ?></td>
                    <td>
                        <a href="editar_producto.php?id=<?= $prod['id'] ?>" class="btn btn-primary btn-sm">✏️ Editar</a>
                        <a href="movimientos.php?id=<?= $prod['id'] ?>" class="btn btn-success btn-sm">📦 Movimientos</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($productos)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No hay productos registrados.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>