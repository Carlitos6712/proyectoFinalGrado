<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/../core/Session.php';

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
    /** Filtra todas las queries por empresa cuando está en modo multi-tenant. */
    private ?int $businessId;

    /**
     * @param PDO|null       $pdo       Conexión PDO inyectada (útil para tests con SQLite); si es null usa el singleton MySQL.
     * @param Auditoria|null $auditoria Modelo de auditoría; si es null, se crea con la conexión activa.
     * @throws AppException Si falla la conexión.
     * @author Carlitos6712
     */
    public function __construct(?PDO $pdo = null, ?Auditoria $auditoria = null)
    {
        $this->pdo        = $pdo ?? Database::getInstance();
        $this->auditoria  = $auditoria ?? new Auditoria($this->pdo);
        $this->businessId = Session::getBusinessId();
    }

    private function bizWhere(string $alias = 'c'): string
    {
        return $this->businessId !== null ? " AND {$alias}.business_id = :biz_id" : "";
    }

    private function bizParam(): array
    {
        return $this->businessId !== null ? [':biz_id' => $this->businessId] : [];
    }

    /**
     * Lista todas las categorías con el conteo de productos activos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(): array
    {
        $bizProd = $this->businessId !== null ? " AND p.business_id = :biz_id2" : "";
        $sql  = "SELECT c.*, COUNT(p.id) AS total_productos
                 FROM categorias c
                 LEFT JOIN productos p ON p.categoria_id = c.id AND p.deleted_at IS NULL{$bizProd}
                 WHERE 1=1" . $this->bizWhere() . "
                 GROUP BY c.id
                 ORDER BY c.nombre";
        $params = $this->bizParam();
        if ($this->businessId !== null) {
            $params[':biz_id2'] = $this->businessId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
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
        $stmt = $this->pdo->prepare(
            "SELECT * FROM categorias c WHERE c.id = :id" . $this->bizWhere()
        );
        $stmt->execute(array_merge([':id' => $id], $this->bizParam()));
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
        $bizCol = $this->businessId !== null ? ', business_id' : '';
        $bizVal = $this->businessId !== null ? ', :biz_id'    : '';
        $stmt = $this->pdo->prepare(
            "INSERT INTO categorias (nombre, descripcion{$bizCol}) VALUES (:nombre, :descripcion{$bizVal})"
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
        $biz = $this->businessId !== null ? " AND business_id = :biz_id" : "";
        $stmt = $this->pdo->prepare(
            "UPDATE categorias SET nombre = :nombre, descripcion = :descripcion
             WHERE id = :id{$biz}"
        );
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
     * Elimina una categoría (solo si no tiene productos activos).
     *
     * @param int $id ID de la categoría.
     * @throws AppException Si la categoría tiene productos activos.
     * @return bool
     */
    public function eliminar(int $id): bool
    {
        $bizProd = $this->businessId !== null ? " AND p.business_id = :biz_id2" : "";
        $chkSql  = "SELECT COUNT(*) FROM productos p
                    WHERE p.categoria_id = :id AND p.deleted_at IS NULL{$bizProd}";
        $chkStmt = $this->pdo->prepare($chkSql);
        $chkParams = [':id' => $id];
        if ($this->businessId !== null) {
            $chkParams[':biz_id2'] = $this->businessId;
        }
        $chkStmt->execute($chkParams);
        if ((int) $chkStmt->fetchColumn() > 0) {
            throw new AppException('No se puede eliminar: la categoría tiene productos activos.', 409);
        }
        $anterior = $this->obtenerPorId($id);
        $del = $this->pdo->prepare(
            "DELETE FROM categorias WHERE id = :id" . $this->bizWhere('')
        );
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
