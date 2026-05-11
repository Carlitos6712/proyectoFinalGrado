<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';

/**
 * Modelo de gestión de marcas del inventario.
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
 * @version  1.0.0
 */
class Marca
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
     * Lista todas las marcas con el conteo de productos activos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(): array
    {
        $stmt = $this->pdo->query(
            "SELECT m.*, COUNT(p.id) AS total_productos
             FROM marcas m
             LEFT JOIN productos p ON p.marca_id = m.id AND p.deleted_at IS NULL
             GROUP BY m.id
             ORDER BY m.nombre"
        );
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una marca por su ID.
     *
     * @param int $id ID de la marca.
     * @throws AppException Si la marca no existe.
     * @return array<string, mixed>
     */
    public function obtenerPorId(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM marcas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new AppException("Marca #{$id} no encontrada.", 404);
        }
        return $row;
    }

    /**
     * Crea una nueva marca.
     *
     * @param string $nombre      Nombre de la marca.
     * @param string $descripcion Descripción opcional.
     * @return int ID de la marca creada.
     * @author Carlitos6712
     */
    public function crear(string $nombre, string $descripcion = ''): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO marcas (nombre, descripcion) VALUES (:nombre, :descripcion)"
        );
        $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion]);
        $id = (int) $this->pdo->lastInsertId();
        $this->auditarOperacion('crear', $id, null, compact('nombre', 'descripcion'));
        return $id;
    }

    /**
     * Actualiza una marca existente.
     *
     * @param int    $id          ID de la marca.
     * @param string $nombre      Nuevo nombre.
     * @param string $descripcion Nueva descripción.
     * @return bool
     * @author Carlitos6712
     */
    public function actualizar(int $id, string $nombre, string $descripcion = ''): bool
    {
        $anterior  = $this->obtenerPorId($id);
        $stmt = $this->pdo->prepare(
            "UPDATE marcas SET nombre = :nombre, descripcion = :descripcion WHERE id = :id"
        );
        $resultado = $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':id' => $id]);
        if ($resultado && $stmt->rowCount() > 0) {
            $this->auditarOperacion('actualizar', $id, $anterior, compact('nombre', 'descripcion'));
        }
        return $resultado;
    }

    /**
     * Elimina una marca (solo si no tiene productos activos).
     *
     * @param int $id ID de la marca.
     * @throws AppException Si la marca tiene productos activos.
     * @return bool
     * @author Carlitos6712
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM productos WHERE marca_id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new AppException('No se puede eliminar: la marca tiene productos activos.', 409);
        }
        $anterior  = $this->obtenerPorId($id);
        $del = $this->pdo->prepare("DELETE FROM marcas WHERE id = :id");
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
     * @param int        $id       PK de la marca.
     * @param array|null $anterior Datos antes de la operación.
     * @param array|null $nuevo    Datos después de la operación.
     * @return void
     * @author Carlitos6712
     */
    private function auditarOperacion(string $accion, int $id, ?array $anterior, ?array $nuevo): void
    {
        try {
            $this->auditoria->registrar('marcas', $id, $accion, $anterior, $nuevo);
        } catch (\Throwable $e) {
            error_log("Auditoria::marcas: {$e->getMessage()}");
        }
    }
}
