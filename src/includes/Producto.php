<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';

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
    private Auditoria $auditoria;

    /**
     * @param Auditoria|null $auditoria Modelo de auditoría; si es null, se crea con la conexión singleton.
     * @throws AppException Si falla la conexión.
     * @author Carlitos6712
     */
    public function __construct(?Auditoria $auditoria = null)
    {
        $this->pdo       = Database::getInstance();
        $this->auditoria = $auditoria ?? new Auditoria($this->pdo);
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
        $id = (int) $this->pdo->lastInsertId();
        $this->auditarOperacion(
            'crear', $id, null,
            compact('nombre', 'descripcion', 'precio', 'categoriaId', 'stock', 'stockMinimo', 'codigoRef')
        );
        return $id;
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
        if ($precio <= 0) {
            throw new AppException('El precio debe ser mayor que cero.', 400);
        }
        if ($stockMinimo < 0) {
            throw new AppException('El stock mínimo no puede ser negativo.', 400);
        }
        $stmt = $this->pdo->prepare(
            "UPDATE productos
             SET nombre = :nombre, descripcion = :descripcion, precio = :precio,
                 categoria_id = :categoria_id, stock_minimo = :stock_minimo,
                 codigo_ref = :codigo_ref
             WHERE id = :id AND deleted_at IS NULL"
        );
        $anterior = $this->obtener($id);
        $resultado = $stmt->execute([
            ':id'           => $id,
            ':nombre'       => $nombre,
            ':descripcion'  => $descripcion,
            ':precio'       => $precio,
            ':categoria_id' => $categoriaId,
            ':stock_minimo' => $stockMinimo,
            ':codigo_ref'   => $codigoRef,
        ]);
        if ($resultado && $stmt->rowCount() > 0) {
            $this->auditarOperacion(
                'actualizar', $id, $anterior,
                compact('nombre', 'descripcion', 'precio', 'categoriaId', 'stockMinimo', 'codigoRef')
            );
        }
        return $resultado;
    }

    /**
     * Soft-delete: marca el producto como eliminado sin borrar el registro.
     *
     * @param int $id ID del producto.
     * @return bool
     */
    public function eliminar(int $id): bool
    {
        if ($this->contarMovimientos($id) > 0) {
            throw new AppException(
                'No se puede eliminar un producto que tiene movimientos registrados.',
                409
            );
        }
        $anterior = $this->obtener($id);
        $stmt = $this->pdo->prepare(
            "UPDATE productos SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        $afectado = $stmt->rowCount() > 0;
        if ($afectado) {
            $this->auditarOperacion('eliminar', $id, $anterior, null);
        }
        return $afectado;
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
     * Registra una operación en el log de auditoría sin propagar errores.
     *
     * @param string     $accion    'crear' | 'actualizar' | 'eliminar'.
     * @param int        $id        PK del producto.
     * @param array|null $anterior  Datos antes de la operación.
     * @param array|null $nuevo     Datos después de la operación.
     * @return void
     * @author Carlitos6712
     */
    private function auditarOperacion(string $accion, int $id, ?array $anterior, ?array $nuevo): void
    {
        try {
            $this->auditoria->registrar('productos', $id, $accion, $anterior, $nuevo);
        } catch (\Throwable $e) {
            error_log("Auditoria::productos: {$e->getMessage()}");
        }
    }

    // ── Constantes de imagen ──────────────────────────────────────────────────

    /** Tamaño máximo de imagen permitido (2 MB). */
    public const MAX_IMAGEN_BYTES = 2 * 1024 * 1024;

    /** Tipos MIME permitidos para imágenes. */
    public const TIPOS_IMAGEN_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];

    /** Directorio de almacenamiento de imágenes (absoluto). */
    public const UPLOAD_DIR = __DIR__ . '/../uploads/productos/';

    /** Ruta web relativa a la raíz del documento. */
    public const UPLOAD_URL = 'uploads/productos/';

    // ── Métodos de imagen ─────────────────────────────────────────────────────

    /**
     * Valida un archivo de imagen subido por el usuario.
     *
     * @param array $file Entrada de $_FILES correspondiente al campo imagen.
     * @throws AppException Si el archivo supera 2 MB o el tipo MIME no está permitido.
     * @return void
     */
    public static function validarArchivoImagen(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new AppException('Error al recibir el archivo de imagen.', 400);
        }
        if ((int)($file['size'] ?? 0) > self::MAX_IMAGEN_BYTES) {
            throw new AppException('La imagen no puede superar los 2 MB.', 400);
        }
        $mime = mime_content_type($file['tmp_name'] ?? '');
        if (!in_array($mime, self::TIPOS_IMAGEN_PERMITIDOS, true)) {
            throw new AppException('Formato no permitido. Solo JPG, PNG o WebP.', 400);
        }
    }

    /**
     * Genera el nombre de fichero determinista para una imagen de producto.
     *
     * @param int $id        ID del producto.
     * @param int $timestamp Unix timestamp de la subida.
     * @return string Nombre del fichero (sin ruta).
     */
    public static function generarNombreImagen(int $id, int $timestamp): string
    {
        return "producto_{$id}_{$timestamp}.webp";
    }

    /**
     * Devuelve la ruta web de la imagen o el placeholder SVG si no hay imagen.
     *
     * @param string|null $imagen Valor de la columna imagen en BD.
     * @return string Ruta relativa lista para usar en src="...".
     */
    public static function rutaImagen(?string $imagen): string
    {
        if ($imagen !== null && $imagen !== '') {
            return self::UPLOAD_URL . $imagen;
        }
        return 'img/placeholder-producto.svg';
    }

    /**
     * Sube, convierte a WebP y guarda la imagen de un producto.
     *
     * Elimina la imagen anterior si existe. Requiere extensión GD.
     *
     * @param array $file Entrada de $_FILES correspondiente al campo imagen.
     * @param int   $id   ID del producto al que pertenece la imagen.
     * @throws AppException Si la validación falla, GD no está disponible,
     *                      o el procesamiento de imagen falla.
     * @return string Nombre del fichero guardado.
     */
    public function subirImagen(array $file, int $id): string
    {
        if (!extension_loaded('gd')) {
            throw new AppException('La extensión GD de PHP no está disponible.', 500);
        }

        self::validarArchivoImagen($file);

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        // Eliminar imagen anterior si existe
        $this->eliminarImagenFichero($id);

        $mime      = mime_content_type($file['tmp_name']);
        $imagen    = $this->crearImagenDesdeArchivo($file['tmp_name'], $mime);
        $nombre    = self::generarNombreImagen($id, time());
        $destino   = self::UPLOAD_DIR . $nombre;

        if (!imagewebp($imagen, $destino, 85)) {
            imagedestroy($imagen);
            throw new AppException('No se pudo guardar la imagen en el servidor.', 500);
        }
        imagedestroy($imagen);

        $stmt = $this->pdo->prepare(
            "UPDATE productos SET imagen = :imagen WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':imagen' => $nombre, ':id' => $id]);

        return $nombre;
    }

    /**
     * Elimina la imagen asociada a un producto (fichero + columna BD).
     *
     * @param int $id ID del producto.
     * @return bool true si se eliminó algo, false si no había imagen.
     */
    public function eliminarImagen(int $id): bool
    {
        $eliminado = $this->eliminarImagenFichero($id);

        $stmt = $this->pdo->prepare(
            "UPDATE productos SET imagen = NULL WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);

        return $eliminado || $stmt->rowCount() > 0;
    }

    /**
     * Crea un recurso GD desde un fichero según su tipo MIME.
     *
     * @param string $ruta Ruta al fichero temporal.
     * @param string $mime Tipo MIME del fichero.
     * @throws AppException Si el tipo MIME no tiene soporte GD.
     * @return \GdImage Recurso de imagen GD.
     */
    private function crearImagenDesdeArchivo(string $ruta, string $mime): \GdImage
    {
        $imagen = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($ruta),
            'image/png'  => imagecreatefrompng($ruta),
            'image/webp' => imagecreatefromwebp($ruta),
            default      => throw new AppException("MIME '{$mime}' sin soporte GD.", 400),
        };
        if ($imagen === false) {
            throw new AppException('No se pudo procesar la imagen.', 500);
        }
        return $imagen;
    }

    /**
     * Elimina el fichero físico de imagen de un producto si existe.
     *
     * @param int $id ID del producto.
     * @return bool true si se eliminó el fichero.
     */
    private function eliminarImagenFichero(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT imagen FROM productos WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        $actual = $stmt->fetchColumn();

        if ($actual && file_exists(self::UPLOAD_DIR . $actual)) {
            return unlink(self::UPLOAD_DIR . $actual);
        }
        return false;
    }

    /**
     * Mapa de valores de ?orden= a cláusulas ORDER BY seguras.
     */
    private const ORDEN_MAP = [
        'nombre_asc'  => 'p.nombre ASC',
        'nombre_desc' => 'p.nombre DESC',
        'precio_asc'  => 'p.precio ASC',
        'precio_desc' => 'p.precio DESC',
        'stock_asc'   => 'p.stock ASC',
        'stock_desc'  => 'p.stock DESC',
        'fecha_asc'   => 'p.created_at ASC',
        'fecha_desc'  => 'p.created_at DESC',
    ];

    /**
     * Cuenta productos activos aplicando los mismos filtros que listarPaginado.
     *
     * @param string|null $termino       Término de búsqueda (nombre o código ref).
     * @param int|null    $categoriaId   Filtro por categoría.
     * @param float|null  $precioMin     Precio mínimo.
     * @param float|null  $precioMax     Precio máximo.
     * @param int|null    $stockMin      Stock mínimo.
     * @param int|null    $stockMax      Stock máximo.
     * @param bool|null   $soloStockBajo Limitar a productos con stock <= stock_minimo.
     * @return int
     */
    public function contarFiltrados(
        ?string $termino = null,
        ?int $categoriaId = null,
        ?float $precioMin = null,
        ?float $precioMax = null,
        ?int $stockMin = null,
        ?int $stockMax = null,
        ?bool $soloStockBajo = null
    ): int {
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
        if ($precioMin !== null) {
            $sql .= " AND p.precio >= :precio_min";
            $params[':precio_min'] = $precioMin;
        }
        if ($precioMax !== null) {
            $sql .= " AND p.precio <= :precio_max";
            $params[':precio_max'] = $precioMax;
        }
        if ($stockMin !== null) {
            $sql .= " AND p.stock >= :stock_min";
            $params[':stock_min'] = $stockMin;
        }
        if ($stockMax !== null) {
            $sql .= " AND p.stock <= :stock_max";
            $params[':stock_max'] = $stockMax;
        }
        if ($soloStockBajo === true) {
            $sql .= " AND p.stock <= p.stock_minimo";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista productos activos con paginación y todos los filtros combinables.
     *
     * Soporta los 7 filtros de Fase 4: q, categoria_id, precio_min,
     * precio_max, stock_min, stock_max, stock_bajo. Más 4 ordenaciones.
     *
     * @param int         $pagina        Página actual (1-indexed).
     * @param int         $porPagina     Registros por página.
     * @param string|null $termino       Término de búsqueda.
     * @param int|null    $categoriaId   Filtro por categoría.
     * @param float|null  $precioMin     Precio mínimo.
     * @param float|null  $precioMax     Precio máximo.
     * @param int|null    $stockMin      Stock mínimo.
     * @param int|null    $stockMax      Stock máximo.
     * @param string      $orden         Clave de ordenación (ver ORDEN_MAP).
     * @param bool|null   $soloStockBajo Limitar a productos con stock <= stock_minimo.
     * @return array<int, array<string, mixed>>
     */
    public function listarPaginado(
        int $pagina,
        int $porPagina,
        ?string $termino = null,
        ?int $categoriaId = null,
        ?float $precioMin = null,
        ?float $precioMax = null,
        ?int $stockMin = null,
        ?int $stockMax = null,
        string $orden = 'nombre_asc',
        ?bool $soloStockBajo = null
    ): array {
        $offset  = ($pagina - 1) * $porPagina;
        $orderBy = self::ORDEN_MAP[$orden] ?? 'p.nombre ASC';
        $sql     = "SELECT p.*, c.nombre AS categoria_nombre
                    FROM productos p
                    LEFT JOIN categorias c ON p.categoria_id = c.id
                    WHERE p.deleted_at IS NULL";
        $params  = [];

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
        if ($precioMin !== null) {
            $sql .= " AND p.precio >= :precio_min";
            $params[':precio_min'] = $precioMin;
        }
        if ($precioMax !== null) {
            $sql .= " AND p.precio <= :precio_max";
            $params[':precio_max'] = $precioMax;
        }
        if ($stockMin !== null) {
            $sql .= " AND p.stock >= :stock_min";
            $params[':stock_min'] = $stockMin;
        }
        if ($stockMax !== null) {
            $sql .= " AND p.stock <= :stock_max";
            $params[':stock_max'] = $stockMax;
        }
        if ($soloStockBajo === true) {
            $sql .= " AND p.stock <= p.stock_minimo";
        }

        $sql .= " ORDER BY {$orderBy} LIMIT :limite OFFSET :offset";

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
