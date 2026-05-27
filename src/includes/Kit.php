<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';

/**
 * Gestión de kits / packs de productos del inventario.
 *
 * Un kit agrupa N productos con sus cantidades para registrar
 * movimientos de salida en bloque desde la vista de movimientos.
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
 * @version  1.0.0
 */
class Kit
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
     * Lista todos los kits con el número de líneas (productos) que contienen.
     *
     * @param bool $soloActivos true = solo activos (default), false = todos.
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function listar(bool $soloActivos = true): array
    {
        $where = $soloActivos ? " WHERE k.activo = 1" : "";
        $stmt  = $this->pdo->prepare(
            "SELECT k.*, COUNT(kl.id) AS total_lineas
             FROM kits k
             LEFT JOIN kits_lineas kl ON kl.kit_id = k.id
             {$where}
             GROUP BY k.id
             ORDER BY k.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un kit por su ID.
     *
     * @param int $id ID del kit.
     * @throws AppException Si el kit no existe (404).
     * @return array<string, mixed>
     * @author Carlitos6712
     */
    public function obtener(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM kits WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row  = $stmt->fetch();
        if (!$row) {
            throw new AppException("Kit #{$id} no encontrado.", 404);
        }
        return $row;
    }

    /**
     * Devuelve las líneas de un kit con nombre de producto y stock actual.
     *
     * @param int $kitId ID del kit.
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function getLineas(int $kitId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT kl.id, kl.kit_id, kl.producto_id, kl.cantidad,
                    p.nombre AS producto_nombre,
                    p.codigo_ref,
                    p.stock   AS stock_actual
             FROM kits_lineas kl
             JOIN productos p ON p.id = kl.producto_id
             WHERE kl.kit_id = :kit_id
             ORDER BY p.nombre"
        );
        $stmt->execute([':kit_id' => $kitId]);
        return $stmt->fetchAll();
    }

    /**
     * Crea un nuevo kit con sus líneas.
     *
     * @param array<string, mixed> $datos  Campos: nombre (oblig.), descripcion, activo.
     * @param array<int, array{producto_id: int, cantidad: int}> $lineas Líneas del kit.
     * @throws AppException Si el nombre está vacío o alguna línea es inválida.
     * @return int ID del kit creado.
     * @author Carlitos6712
     */
    public function crear(array $datos, array $lineas = []): int
    {
        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            throw new AppException('El nombre del kit es obligatorio.', 422);
        }
        $descripcion = trim($datos['descripcion'] ?? '');
        $activo      = isset($datos['activo']) ? (int)(bool)$datos['activo'] : 1;

        $stmt = $this->pdo->prepare(
            "INSERT INTO kits (nombre, descripcion, activo) VALUES (:nombre, :descripcion, :activo)"
        );
        $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':activo' => $activo]);
        $id = (int) $this->pdo->lastInsertId();

        if (!empty($lineas)) {
            $this->syncLineas($id, $lineas);
        }

        $this->auditarOperacion('crear', $id, null, compact('nombre', 'descripcion', 'activo'));
        return $id;
    }

    /**
     * Actualiza un kit existente y reemplaza sus líneas.
     *
     * @param int $id     ID del kit.
     * @param array<string, mixed> $datos Campos a actualizar.
     * @param array<int, array{producto_id: int, cantidad: int}> $lineas Nuevas líneas.
     * @throws AppException Si el kit no existe o el nombre está vacío.
     * @return bool
     * @author Carlitos6712
     */
    public function actualizar(int $id, array $datos, array $lineas = []): bool
    {
        $anterior = $this->obtener($id);
        $nombre      = trim($datos['nombre']      ?? $anterior['nombre']);
        $descripcion = trim($datos['descripcion'] ?? $anterior['descripcion']);
        $activo      = isset($datos['activo']) ? (int)(bool)$datos['activo'] : (int)$anterior['activo'];

        if ($nombre === '') {
            throw new AppException('El nombre del kit es obligatorio.', 422);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE kits SET nombre = :nombre, descripcion = :descripcion, activo = :activo WHERE id = :id"
        );
        $ok = $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':activo' => $activo, ':id' => $id]);

        if (!empty($lineas)) {
            $this->syncLineas($id, $lineas);
        }

        if ($ok) {
            $this->auditarOperacion('actualizar', $id, $anterior, compact('nombre', 'descripcion', 'activo'));
        }
        return $ok;
    }

    /**
     * Activa o desactiva un kit (toggle).
     *
     * @param int $id ID del kit.
     * @throws AppException Si el kit no existe.
     * @return bool Nuevo estado: true = activo, false = inactivo.
     * @author Carlitos6712
     */
    public function toggleActivo(int $id): bool
    {
        $kit    = $this->obtener($id);
        $nuevo  = $kit['activo'] ? 0 : 1;
        $stmt   = $this->pdo->prepare("UPDATE kits SET activo = :activo WHERE id = :id");
        $stmt->execute([':activo' => $nuevo, ':id' => $id]);
        $this->auditarOperacion('actualizar', $id, $kit, ['activo' => $nuevo]);
        return (bool) $nuevo;
    }

    /**
     * Sincroniza las líneas de un kit: elimina las existentes y crea las nuevas.
     *
     * @param int $kitId  ID del kit.
     * @param array<int, array{producto_id: int, cantidad: int}> $lineas Líneas nuevas.
     * @throws AppException Si alguna línea tiene cantidad ≤ 0 o producto_id inválido.
     * @return void
     * @author Carlitos6712
     */
    public function syncLineas(int $kitId, array $lineas): void
    {
        $del  = $this->pdo->prepare("DELETE FROM kits_lineas WHERE kit_id = :kit_id");
        $del->execute([':kit_id' => $kitId]);

        if (empty($lineas)) {
            return;
        }

        $ins = $this->pdo->prepare(
            "INSERT INTO kits_lineas (kit_id, producto_id, cantidad) VALUES (:kit_id, :producto_id, :cantidad)"
        );

        foreach ($lineas as $linea) {
            $productoId = (int)($linea['producto_id'] ?? 0);
            $cantidad   = (int)($linea['cantidad']    ?? 0);

            if ($productoId <= 0 || $cantidad <= 0) {
                throw new AppException('Cada línea del kit debe tener producto_id y cantidad > 0.', 422);
            }
            $ins->execute([':kit_id' => $kitId, ':producto_id' => $productoId, ':cantidad' => $cantidad]);
        }
    }

    /**
     * Cuenta las líneas de un kit.
     *
     * @param int $kitId ID del kit.
     * @return int
     * @author Carlitos6712
     */
    public function contarLineas(int $kitId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM kits_lineas WHERE kit_id = :kit_id");
        $stmt->execute([':kit_id' => $kitId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Registra una operación en el log de auditoría sin propagar errores.
     *
     * @param string     $accion   'crear' | 'actualizar' | 'eliminar'.
     * @param int        $id       PK del kit.
     * @param array|null $anterior Datos antes de la operación.
     * @param array|null $nuevo    Datos después de la operación.
     * @return void
     * @author Carlitos6712
     */
    private function auditarOperacion(string $accion, int $id, ?array $anterior, ?array $nuevo): void
    {
        try {
            $this->auditoria->registrar('kits', $id, $accion, $anterior, $nuevo);
        } catch (\Throwable $e) {
            error_log("Auditoria::kits: {$e->getMessage()}");
        }
    }
}
