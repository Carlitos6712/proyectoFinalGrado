<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';

/**
 * Modelo de gestión de categorías del inventario.
 *
 * @package  Es21Plus\Includes
 * @author   miguelrechefdez
 * @author   Carlitos6712
 * @version  1.0.0
 */
class Categoria
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
        $id = (int) $this->pdo->lastInsertId();
        $this->auditarOperacion('crear', $id, null, compact('nombre', 'descripcion'));
        return $id;
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
        $anterior  = $this->obtenerPorId($id);
        $stmt = $this->pdo->prepare(
            "UPDATE categorias SET nombre = :nombre, descripcion = :descripcion WHERE id = :id"
        );
        $resultado = $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':id' => $id]);
        $this->auditarOperacion('actualizar', $id, $anterior, compact('nombre', 'descripcion'));
        return $resultado;
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
        $anterior = $this->obtenerPorId($id);
        $del = $this->pdo->prepare("DELETE FROM categorias WHERE id = :id");
        $resultado = $del->execute([':id' => $id]);
        if ($resultado) {
            $this->auditarOperacion('eliminar', $id, $anterior, null);
        }
        return $resultado;
    }

    /**
     * Registra una operación en el log de auditoría sin propagar errores.
     *
     * @param string     $accion   'crear' | 'actualizar' | 'eliminar'.
     * @param int        $id       PK de la categoría.
     * @param array|null $anterior Datos antes de la operación.
     * @param array|null $nuevo    Datos después de la operación.
     * @return void
     * @author Carlitos6712
     */
    private function auditarOperacion(string $accion, int $id, ?array $anterior, ?array $nuevo): void
    {
        try {
            $this->auditoria->registrar('categorias', $id, $accion, $anterior, $nuevo);
        } catch (\Throwable $e) {
            error_log("Auditoria::categorias: {$e->getMessage()}");
        }
    }
}
