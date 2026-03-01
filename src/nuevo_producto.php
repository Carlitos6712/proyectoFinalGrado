<?php
require_once 'includes/Producto.php';
require_once 'includes/Categoria.php';

$categoria = new Categoria();
$categorias = $categoria->listar();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto = new Producto();
    $producto->crear(
        $_POST['nombre'],
        $_POST['descripcion'],
        $_POST['precio'],
        $_POST['categoria_id']
    );
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Producto</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <div class="container">
        <h1>➕ Nuevo Producto</h1>
        <form method="post">
            <label for="nombre">Nombre:</label>
            <input type="text" name="nombre" id="nombre" required>

            <label for="descripcion">Descripción:</label>
            <textarea name="descripcion" id="descripcion"></textarea>

            <label for="precio">Precio:</label>
            <input type="number" step="0.01" name="precio" id="precio" required>

            <label for="categoria_id">Categoría:</label>
            <select name="categoria_id" id="categoria_id">
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Guardar Producto</button>
        </form>
        <p style="margin-top: 20px;">
            <a href="index.php">← Volver al listado</a>
        </p>
    </div>
</body>
</html>