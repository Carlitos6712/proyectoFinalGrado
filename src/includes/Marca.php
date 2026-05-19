<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/../core/Session.php';

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
    /** Filtra todas las queries por empresa cuando está en modo multi-tenant. */
    private ?int $businessId;

    /**
     * @param Auditoria|null $auditoria Modelo de auditoría; si es null, se crea con la conexión singleton.
     * @throws AppException Si falla la conexión.
     * @author Carlitos6712
     */
    public function __construct(?Auditoria $auditoria = null)
    {
        $this->pdo        = Database::getInstance();
        $this->auditoria  = $auditoria ?? new Auditoria($this->pdo);
        $this->businessId = Session::getBusinessId();
    }

    private function bizWhere(string $alias = 'm'): string
    {
        return $this->businessId !== null ? " AND {$alias}.business_id = :biz_id" : "";
    }

    private function bizParam(): array
    {
        return $this->businessId !== null ? [':biz_id' => $this->businessId] : [];
    }

    /**
     * Lista todas las marcas del negocio con el conteo de productos activos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(): array
    {
        $sql  = "SELECT m.*, COUNT(p.id) AS total_productos
                 FROM marcas m
                 LEFT JOIN productos p ON p.marca_id = m.id AND p.deleted_at IS NULL
                 WHERE 1=1" . $this->bizWhere() . "
                 GROUP BY m.id
                 ORDER BY m.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bizParam());
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una marca por su ID, verificando que pertenezca al negocio.
     *
     * @param int $id ID de la marca.
     * @throws AppException Si la marca no existe o no pertenece al negocio.
     * @return array<string, mixed>
     */
    public function obtenerPorId(int $id): array
    {
        $sql  = "SELECT * FROM marcas WHERE id = :id" . $this->bizWhere();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([':id' => $id], $this->bizParam()));
        $row = $stmt->fetch();
        if (!$row) {
            throw new AppException("Marca #{$id} no encontrada.", 404);
        }
        return $row;
    }

    /**
     * Crea una nueva marca para el negocio actual.
     *
     * @param string $nombre      Nombre de la marca.
     * @param string $descripcion Descripción opcional.
     * @return int ID de la marca creada.
     * @author Carlitos6712
     */
    public function crear(string $nombre, string $descripcion = ''): int
    {
        $bizCol = $this->businessId !== null ? ', business_id' : '';
        $bizVal = $this->businessId !== null ? ', :biz_id'    : '';
        $stmt   = $this->pdo->prepare(
            "INSERT INTO marcas (nombre, descripcion{$bizCol}) VALUES (:nombre, :descripcion{$bizVal})"
        );
        $stmt->execute(array_merge(
            [':nombre' => $nombre, ':descripcion' => $descripcion],
            $this->bizParam()
        ));
        $id = (int) $this->pdo->lastInsertId();
        $this->auditarOperacion('crear', $id, null, compact('nombre', 'descripcion'));
        return $id;
    }

    /**
     * Actualiza una marca existente del negocio.
     *
     * @param int    $id          ID de la marca.
     * @param string $nombre      Nuevo nombre.
     * @param string $descripcion Nueva descripción.
     * @return bool
     * @author Carlitos6712
     */
    public function actualizar(int $id, string $nombre, string $descripcion = ''): bool
    {
        $anterior = $this->obtenerPorId($id);
        $sql      = "UPDATE marcas SET nombre = :nombre, descripcion = :descripcion
                     WHERE id = :id" . $this->bizWhere();
        $stmt     = $this->pdo->prepare($sql);
        $resultado = $stmt->execute(array_merge(
            [':nombre' => $nombre, ':descripcion' => $descripcion, ':id' => $id],
            $this->bizParam()
        ));
        if ($resultado && $stmt->rowCount() > 0) {
            $this->auditarOperacion('actualizar', $id, $anterior, compact('nombre', 'descripcion'));
        }
        return $resultado;
    }

    /**
     * Elimina una marca del negocio (solo si no tiene productos activos).
     *
     * @param int $id ID de la marca.
     * @throws AppException Si la marca tiene productos activos.
     * @return bool
     * @author Carlitos6712
     */
    public function eliminar(int $id): bool
    {
        $sql  = "SELECT COUNT(*) FROM productos WHERE marca_id = :id AND deleted_at IS NULL"
              . ($this->businessId !== null ? " AND business_id = :biz_id" : "");
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([':id' => $id], $this->bizParam()));
        if ((int) $stmt->fetchColumn() > 0) {
            throw new AppException('No se puede eliminar: la marca tiene productos activos.', 409);
        }

        $anterior  = $this->obtenerPorId($id);
        $sql       = "DELETE FROM marcas WHERE id = :id" . $this->bizWhere();
        $del       = $this->pdo->prepare($sql);
        $resultado = $del->execute(array_merge([':id' => $id], $this->bizParam()));
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
