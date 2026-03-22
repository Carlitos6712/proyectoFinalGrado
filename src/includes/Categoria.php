<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';

/**
 * Modelo de gestión de categorías del inventario.
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
 * @version  1.0.0
 */
class Categoria
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
     * Lista todas las categorías con el conteo de productos activos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(): array
    {
        $stmt = $this->pdo->query(
            "SELECT c.*, COUNT(p.id) AS total_productos
             FROM categorias c
             LEFT JOIN productos p ON p.categoria_id = c.id AND p.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY c.nombre"
        );
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una categoría por su ID.
     *
     * @param int $id ID de la categoría.
     * @throws AppException Si la categoría no existe.
     * @return array<string, mixed>
     */
    public function obtenerPorId(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM categorias WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new AppException("Categoría #{$id} no encontrada.", 404);
        }
        return $row;
    }

    /**
     * Crea una nueva categoría.
     *
     * @param string $nombre      Nombre de la categoría.
     * @param string $descripcion Descripción opcional.
     * @return int ID de la categoría creada.
     */
    public function crear(string $nombre, string $descripcion = ''): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)"
        );
        $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza una categoría existente.
     *
     * @param int    $id          ID de la categoría.
     * @param string $nombre      Nuevo nombre.
     * @param string $descripcion Nueva descripción.
     * @return bool
     */
    public function actualizar(int $id, string $nombre, string $descripcion = ''): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE categorias SET nombre = :nombre, descripcion = :descripcion WHERE id = :id"
        );
        return $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':id' => $id]);
    }

    /**
     * Elimina una categoría (solo si no tiene productos activos).
     *
     * @param int $id ID de la categoría.
     * @throws AppException Si la categoría tiene productos activos.
     * @return bool
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM productos WHERE categoria_id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new AppException('No se puede eliminar: la categoría tiene productos activos.', 409);
        }
        $del = $this->pdo->prepare("DELETE FROM categorias WHERE id = :id");
        return $del->execute([':id' => $id]);
    }
}
