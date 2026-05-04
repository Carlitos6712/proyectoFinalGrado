<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';

/**
 * Modelo de gestión de productos del inventario.
 *
 * @package  Es21Plus\Includes
 * @author   miguelrechefdez
 * @author   Carlitos6712
 * @version  1.0.0
 */
class Producto
{
    private PDO $pdo;

    /**
     * @throws AppException Si falla la conexión.
     */
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Lista todos los productos activos con su categoría.
     *
     * Usa LEFT JOIN para evitar N+1 y excluye soft-deleted.
     *
     * @return array<int, array<string, mixed>> Filas de productos.
     */
    public function listar(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.*, c.nombre AS categoria_nombre
             FROM productos p
             LEFT JOIN categorias c ON p.categoria_id = c.id
             WHERE p.deleted_at IS NULL
             ORDER BY p.nombre"
        );
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un producto activo por su ID.
     *
     * @param int $id Identificador del producto.
     * @throws AppException Si el producto no existe o está eliminado.
     * @return array<string, mixed>
     */
    public function obtener(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, c.nombre AS categoria_nombre
             FROM productos p
             LEFT JOIN categorias c ON p.categoria_id = c.id
             WHERE p.id = :id AND p.deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new AppException("Producto #{$id} no encontrado.", 404);
        }
        return $row;
    }

    /**
     * Crea un nuevo producto en el inventario.
     *
     * @param string      $nombre      Nombre del producto.
     * @param string      $descripcion Descripción opcional.
     * @param float       $precio      Precio unitario.
     * @param int|null    $categoriaId ID de categoría (puede ser null).
     * @param int         $stock       Stock inicial.
     * @param int         $stockMinimo Umbral de alerta de stock.
     * @param string|null $codigoRef   Código de referencia.
     * @return int ID del producto creado.
     */
    public function crear(
        string $nombre,
        string $descripcion,
        float $precio,
        ?int $categoriaId,
        int $stock = 0,
        int $stockMinimo = 5,
        ?string $codigoRef = null
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO productos
             (nombre, descripcion, precio, categoria_id, stock, stock_minimo, codigo_ref)
             VALUES (:nombre, :descripcion, :precio, :categoria_id, :stock, :stock_minimo, :codigo_ref)"
        );
        $stmt->execute([
            ':nombre'       => $nombre,
            ':descripcion'  => $descripcion,
            ':precio'       => $precio,
            ':categoria_id' => $categoriaId,
            ':stock'        => $stock,
            ':stock_minimo' => $stockMinimo,
            ':codigo_ref'   => $codigoRef,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza los datos de un producto existente.
     *
     * @param int         $id          ID del producto a actualizar.
     * @param string      $nombre      Nuevo nombre.
     * @param string      $descripcion Nueva descripción.
     * @param float       $precio      Nuevo precio.
     * @param int|null    $categoriaId Nueva categoría.
     * @param int         $stockMinimo Nuevo umbral de alerta.
     * @param string|null $codigoRef   Nuevo código de referencia.
     * @return bool
     */
    public function actualizar(
        int $id,
        string $nombre,
        string $descripcion,
        float $precio,
        ?int $categoriaId,
        int $stockMinimo = 5,
        ?string $codigoRef = null
    ): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE productos
             SET nombre = :nombre, descripcion = :descripcion, precio = :precio,
                 categoria_id = :categoria_id, stock_minimo = :stock_minimo,
                 codigo_ref = :codigo_ref
             WHERE id = :id AND deleted_at IS NULL"
        );
        return $stmt->execute([
            ':id'           => $id,
            ':nombre'       => $nombre,
            ':descripcion'  => $descripcion,
            ':precio'       => $precio,
            ':categoria_id' => $categoriaId,
            ':stock_minimo' => $stockMinimo,
            ':codigo_ref'   => $codigoRef,
        ]);
    }

    /**
     * Soft-delete: marca el producto como eliminado sin borrar el registro.
     *
     * @param int $id ID del producto.
     * @return bool
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE productos SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Busca productos por nombre o código de referencia.
     *
     * @param string   $termino     Término de búsqueda.
     * @param int|null $categoriaId Filtro opcional por categoría.
     * @return array<int, array<string, mixed>>
     */
    public function buscar(string $termino, ?int $categoriaId = null): array
    {
        $sql = "SELECT p.*, c.nombre AS categoria_nombre
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.deleted_at IS NULL
                  AND (p.nombre LIKE :termino OR p.codigo_ref LIKE :termino2)";
        $like   = "%{$termino}%";
        $params = [':termino' => $like, ':termino2' => $like];

        if ($categoriaId !== null) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params[':categoria_id'] = $categoriaId;
        }
        $sql .= " ORDER BY p.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Lista productos cuyo stock está por debajo del stock_minimo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function filtrarStockBajo(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.*, c.nombre AS categoria_nombre
             FROM productos p
             LEFT JOIN categorias c ON p.categoria_id = c.id
             WHERE p.deleted_at IS NULL AND p.stock <= p.stock_minimo
             ORDER BY p.stock ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Actualiza el stock de un producto (usado por Movimiento).
     *
     * @param int    $id       ID del producto.
     * @param int    $cantidad Cantidad a sumar (positiva) o restar (negativa).
     * @throws AppException Si el stock resultante sería negativo.
     * @return bool
     */
    public function actualizarStock(int $id, int $cantidad): bool
    {
        $producto = $this->obtener($id);
        $nuevoStock = $producto['stock'] + $cantidad;
        if ($nuevoStock < 0) {
            throw new AppException("Stock insuficiente. Stock actual: {$producto['stock']}.", 400);
        }
        $stmt = $this->pdo->prepare(
            "UPDATE productos SET stock = :stock WHERE id = :id"
        );
        return $stmt->execute([':stock' => $nuevoStock, ':id' => $id]);
    }

    /**
     * Cuenta el total de productos con movimientos activos.
     *
     * @param int $id ID del producto.
     * @return int
     */
    public function contarMovimientos(int $id): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM movimientos WHERE producto_id = :id"
        );
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Calcula el valor total del inventario (suma de precio × stock).
     *
 * @author   miguelrechefdez
     * @return float Valor total en euros.
     */
    public function valorInventario(): float
    {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(precio * stock), 0) AS total
             FROM productos
             WHERE deleted_at IS NULL"
        );
        return (float) $stmt->fetchColumn();
    }

    /**
     * Cuenta el total de productos activos.
     *
 * @author   miguelrechefdez
     * @return int
     */
    public function contarActivos(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM productos WHERE deleted_at IS NULL"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Cuenta productos activos aplicando los mismos filtros que listarPaginado.
     *
     * @param string|null $termino     Término de búsqueda (nombre o código ref).
     * @param int|null    $categoriaId Filtro por categoría.
     * @return int
     */
    public function contarFiltrados(?string $termino = null, ?int $categoriaId = null): int
    {
        $sql    = "SELECT COUNT(*) FROM productos p WHERE p.deleted_at IS NULL";
        $params = [];

        if ($termino !== null && $termino !== '') {
            $sql .= " AND (p.nombre LIKE :termino OR p.codigo_ref LIKE :termino2)";
            $like              = "%{$termino}%";
            $params[':termino']  = $like;
            $params[':termino2'] = $like;
        }
        if ($categoriaId !== null) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params[':categoria_id'] = $categoriaId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista productos activos con paginación y filtros opcionales.
     *
     * @param int         $pagina      Página actual (1-indexed).
     * @param int         $porPagina   Registros por página.
     * @param string|null $termino     Término de búsqueda.
     * @param int|null    $categoriaId Filtro por categoría.
     * @return array<int, array<string, mixed>>
     */
    public function listarPaginado(
        int $pagina,
        int $porPagina,
        ?string $termino = null,
        ?int $categoriaId = null
    ): array {
        $offset = ($pagina - 1) * $porPagina;
        $sql    = "SELECT p.*, c.nombre AS categoria_nombre
                   FROM productos p
                   LEFT JOIN categorias c ON p.categoria_id = c.id
                   WHERE p.deleted_at IS NULL";
        $params = [];

        if ($termino !== null && $termino !== '') {
            $sql .= " AND (p.nombre LIKE :termino OR p.codigo_ref LIKE :termino2)";
            $like              = "%{$termino}%";
            $params[':termino']  = $like;
            $params[':termino2'] = $like;
        }
        if ($categoriaId !== null) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params[':categoria_id'] = $categoriaId;
        }

        $sql .= " ORDER BY p.nombre LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
