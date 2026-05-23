<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';

/**
 * Gestión de proveedores del inventario.
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
 * @version  1.0.0
 */
class Proveedor
{
    private PDO       $pdo;
    private Auditoria $auditoria;

    /**
     * @param PDO|null       $pdo       Conexión PDO inyectada (útil para tests con SQLite); si es null usa el singleton MySQL.
     * @param Auditoria|null $auditoria Modelo de auditoría; si es null, se crea con la conexión activa.
     * @throws AppException Si falla la conexión.
     * @author Carlitos6712
     */
    public function __construct(?PDO $pdo = null, ?Auditoria $auditoria = null)
    {
        $this->pdo       = $pdo       ?? Database::getInstance();
        $this->auditoria = $auditoria ?? new Auditoria($this->pdo);
    }

    /**
     * Lista todos los proveedores con el conteo de productos activos vinculados.
     *
     * @param bool|null $soloActivos true = solo activos, false/null = todos.
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function listar(?bool $soloActivos = true): array
    {
        $where = $soloActivos ? " WHERE p.activo = 1" : "";
        $sql   = "SELECT p.*, COUNT(pr.id) AS total_productos
                  FROM proveedores p
                  LEFT JOIN productos pr ON pr.proveedor_id = p.id AND pr.deleted_at IS NULL
                  {$where}
                  GROUP BY p.id
                  ORDER BY p.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un proveedor por su ID.
     *
     * @param int $id ID del proveedor.
     * @throws AppException Si el proveedor no existe.
     * @return array<string, mixed>
     * @author Carlitos6712
     */
    public function obtener(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM proveedores WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new AppException("Proveedor #{$id} no encontrado.", 404);
        }
        return $row;
    }

    /**
     * Crea un nuevo proveedor.
     *
     * @param array<string, mixed> $datos Datos del proveedor (nombre obligatorio).
     * @throws AppException Si el nombre está vacío.
     * @return int ID del proveedor creado.
     * @author Carlitos6712
     */
    public function crear(array $datos): int
    {
        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            throw new AppException('El nombre es obligatorio', 400);
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO proveedores (nombre, contacto, email, telefono, web, notas, activo)
             VALUES (:nombre, :contacto, :email, :telefono, :web, :notas, 1)"
        );
        $stmt->execute([
            ':nombre'   => $nombre,
            ':contacto' => $datos['contacto'] ?? null,
            ':email'    => $datos['email']    ?? null,
            ':telefono' => $datos['telefono'] ?? null,
            ':web'      => $datos['web']      ?? null,
            ':notas'    => $datos['notas']    ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->auditar('crear', $id, null, array_merge($datos, ['nombre' => $nombre]));
        return $id;
    }

    /**
     * Actualiza los datos de un proveedor existente.
     *
     * @param int                  $id    ID del proveedor.
     * @param array<string, mixed> $datos Nuevos datos.
     * @return bool
     * @author Carlitos6712
     */
    public function actualizar(int $id, array $datos): bool
    {
        $anterior = $this->obtener($id);
        $nombre   = trim($datos['nombre'] ?? $anterior['nombre']);
        if ($nombre === '') {
            throw new AppException('El nombre es obligatorio', 400);
        }
        $stmt = $this->pdo->prepare(
            "UPDATE proveedores
             SET nombre = :nombre, contacto = :contacto, email = :email,
                 telefono = :telefono, web = :web, notas = :notas
             WHERE id = :id"
        );
        $resultado = $stmt->execute([
            ':id'       => $id,
            ':nombre'   => $nombre,
            ':contacto' => $datos['contacto'] ?? $anterior['contacto'],
            ':email'    => $datos['email']    ?? $anterior['email'],
            ':telefono' => $datos['telefono'] ?? $anterior['telefono'],
            ':web'      => $datos['web']      ?? $anterior['web'],
            ':notas'    => $datos['notas']    ?? $anterior['notas'],
        ]);
        if ($resultado && $stmt->rowCount() > 0) {
            $this->auditar('actualizar', $id, $anterior, $datos);
        }
        return $resultado;
    }

    /**
     * Desactiva un proveedor (lo marca como inactivo).
     * Lanza AppException 409 si tiene productos activos vinculados.
     *
     * @param int $id ID del proveedor.
     * @throws AppException Si tiene productos activos (409) o no existe (404).
     * @return bool
     * @author Carlitos6712
     */
    public function desactivar(int $id): bool
    {
        $anterior = $this->obtener($id);
        $activos  = $this->contarProductosActivos($id);
        if ($activos > 0) {
            throw new AppException(
                "No se puede desactivar: tiene {$activos} producto(s) activo(s)",
                409
            );
        }
        $stmt = $this->pdo->prepare("UPDATE proveedores SET activo = 0 WHERE id = :id");
        $resultado = $stmt->execute([':id' => $id]);
        if ($resultado) {
            $this->auditar('actualizar', $id, $anterior, ['activo' => 0]);
        }
        return $resultado;
    }

    /**
     * Cuenta los productos activos vinculados a un proveedor.
     *
     * @param int $id ID del proveedor.
     * @return int
     * @author Carlitos6712
     */
    public function contarProductosActivos(int $id): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM productos WHERE proveedor_id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista los productos activos vinculados a un proveedor.
     *
     * @param int $id ID del proveedor.
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function listarProductos(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, c.nombre AS categoria_nombre, m.nombre AS marca_nombre
             FROM productos p
             LEFT JOIN categorias c ON p.categoria_id = c.id
             LEFT JOIN marcas m ON p.marca_id = m.id
             WHERE p.proveedor_id = :id AND p.deleted_at IS NULL
             ORDER BY p.nombre"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    /**
     * Registra una operación en el log de auditoría sin propagar errores.
     *
     * @param string     $accion   'crear' | 'actualizar' | 'eliminar'.
     * @param int        $id       PK del proveedor.
     * @param array|null $anterior Datos antes de la operación.
     * @param array|null $nuevo    Datos después de la operación.
     * @return void
     * @author Carlitos6712
     */
    private function auditar(string $accion, int $id, ?array $anterior, ?array $nuevo): void
    {
        try {
            $this->auditoria->registrar('proveedores', $id, $accion, $anterior, $nuevo);
        } catch (\Throwable $e) {
            error_log("Auditoria::proveedores: {$e->getMessage()}");
        }
    }
}
