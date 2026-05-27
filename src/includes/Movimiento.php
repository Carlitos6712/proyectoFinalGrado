<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Producto.php';
require_once __DIR__ . '/AlertaStock.php';
require_once __DIR__ . '/../core/Session.php';

/**
 * Modelo de gestión de movimientos de stock (entradas/salidas).
 *
 * @package  Es21Plus\Includes
 * @author   miguelrechefdez
 * @author   Carlitos6712
 * @version  1.0.0
 */
class Movimiento
{
    private PDO $pdo;
    private Producto $productoModel;
    private AlertaStock $alertaStock;
    /** Filtra todas las queries por empresa cuando está en modo multi-tenant. */
    private ?int $businessId;

    /**
     * @param PDO|null         $pdo         Conexión PDO inyectada (útil para tests con SQLite); si es null usa el singleton MySQL.
     * @param AlertaStock|null $alertaStock Servicio de alertas; si es null, se crea con la conexión activa.
     * @throws AppException Si falla la conexión.
     * @author Carlitos6712
     */
    public function __construct(?PDO $pdo = null, ?AlertaStock $alertaStock = null)
    {
        $this->pdo           = $pdo ?? Database::getInstance();
        $this->productoModel = new Producto($pdo);
        $this->alertaStock   = $alertaStock ?? new AlertaStock($this->pdo);
        $this->businessId    = Session::getBusinessId();
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
     * Registra un movimiento y actualiza el stock del producto.
     *
     * @param int    $productoId    ID del producto.
     * @param string $tipo          'entrada' o 'salida'.
     * @param int    $cantidad      Unidades del movimiento (> 0).
     * @param string $observaciones Texto libre con detalles.
     * @throws AppException Si el tipo es inválido, la cantidad inválida, o stock insuficiente.
     * @return int ID del movimiento creado.
     */
    public function registrar(int $productoId, string $tipo, int $cantidad, string $observaciones = ''): int
    {
        if (!in_array($tipo, ['entrada', 'salida'], true)) {
            throw new AppException("Tipo de movimiento inválido: '{$tipo}'.", 400);
        }
        if ($cantidad <= 0) {
            throw new AppException('La cantidad debe ser mayor a cero.', 400);
        }

        $delta = ($tipo === 'entrada') ? $cantidad : -$cantidad;

        $this->pdo->beginTransaction();
        try {
            $this->productoModel->actualizarStock($productoId, $delta);
            $bizCol = $this->businessId !== null ? ', business_id' : '';
            $bizVal = $this->businessId !== null ? ', :biz_id'    : '';
            $stmt = $this->pdo->prepare(
                "INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones{$bizCol})
                 VALUES (:producto_id, :tipo, :cantidad, :observaciones{$bizVal})"
            );
            $stmt->execute(array_merge([
                ':producto_id'   => $productoId,
                ':tipo'          => $tipo,
                ':cantidad'      => $cantidad,
                ':observaciones' => $observaciones,
            ], $this->bizParam()));
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            if ($tipo === 'salida') {
                $productoActual = $this->productoModel->obtener($productoId);
                $this->alertaStock->verificarYEnviar($productoId, (int) $productoActual['stock']);
            }

            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e instanceof AppException ? $e : new AppException($e->getMessage(), 500, $e);
        }
    }

    /**
     * Lista los movimientos de un producto ordenados por fecha descendente.
     *
     * @param int $productoId ID del producto.
     * @return array<int, array<string, mixed>>
     */
    public function listarPorProducto(int $productoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.*, p.nombre AS producto_nombre
             FROM movimientos m
             JOIN productos p ON m.producto_id = p.id
             WHERE m.producto_id = :producto_id" . $this->bizWhere() . "
             ORDER BY m.fecha DESC"
        );
        $stmt->execute(array_merge([':producto_id' => $productoId], $this->bizParam()));
        return $stmt->fetchAll();
    }

    /**
     * Retorna el resumen de stock: suma entradas - suma salidas.
     *
     * @param int $productoId ID del producto.
     * @return array{entradas: int, salidas: int, balance: int}
     */
    public function resumenStock(int $productoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE 0 END) AS entradas,
                SUM(CASE WHEN tipo = 'salida'  THEN cantidad ELSE 0 END) AS salidas
             FROM movimientos m
             WHERE m.producto_id = :producto_id" . $this->bizWhere()
        );
        $stmt->execute(array_merge([':producto_id' => $productoId], $this->bizParam()));
        $row = $stmt->fetch();
        $entradas = (int) ($row['entradas'] ?? 0);
        $salidas  = (int) ($row['salidas']  ?? 0);
        return ['entradas' => $entradas, 'salidas' => $salidas, 'balance' => $entradas - $salidas];
    }

    /**
     * Lista los últimos N movimientos de todos los productos.
     *
     * @param int $limite Cantidad máxima de registros.
     * @return array<int, array<string, mixed>>
     */
    public function ultimosMovimientos(int $limite = 10): array
    {
        $sql  = "SELECT m.*, p.nombre AS producto_nombre
                 FROM movimientos m
                 JOIN productos p ON m.producto_id = p.id
                 WHERE 1=1" . $this->bizWhere() . "
                 ORDER BY m.fecha DESC LIMIT :limite";
        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bizParam() as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retorna estadísticas de movimientos agrupadas por día (últimos N días).
     *
     * @author   miguelrechefdez
     * @param int $dias Número de días a consultar (default 7).
     * @return array<int, array{fecha: string, entradas: int, salidas: int}>
     */
    public function estadisticasPorDia(int $dias = 7): array
    {
        $sql  = "SELECT
                    DATE(m.fecha) AS fecha,
                    SUM(CASE WHEN m.tipo = 'entrada' THEN m.cantidad ELSE 0 END) AS entradas,
                    SUM(CASE WHEN m.tipo = 'salida'  THEN m.cantidad ELSE 0 END) AS salidas
                 FROM movimientos m
                 WHERE m.fecha >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)"
                . $this->bizWhere() . "
                 GROUP BY DATE(m.fecha)
                 ORDER BY DATE(m.fecha) ASC";
        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bizParam() as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Cuenta el total de movimientos del mes actual.
     *
     * @author   miguelrechefdez
     * @return int
     */
    public function contarEsteMes(): int
    {
        $sql  = "SELECT COUNT(*) FROM movimientos m
                 WHERE YEAR(m.fecha) = YEAR(CURDATE())
                   AND MONTH(m.fecha) = MONTH(CURDATE())" . $this->bizWhere();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bizParam());
        return (int) $stmt->fetchColumn();
    }

    /**
     * Cuenta el total de movimientos de un producto.
     *
     * @param int $productoId ID del producto.
     * @return int
     */
    public function contarPorProducto(int $productoId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM movimientos m
             WHERE m.producto_id = :producto_id" . $this->bizWhere()
        );
        $stmt->execute(array_merge([':producto_id' => $productoId], $this->bizParam()));
        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista movimientos de un producto con paginación, más recientes primero.
     *
     * @param int $productoId ID del producto.
     * @param int $pagina     Página actual (1-indexed).
     * @param int $porPagina  Registros por página.
     * @return array<int, array<string, mixed>>
     */
    public function listarPorProductoPaginado(int $productoId, int $pagina, int $porPagina): array
    {
        $offset = ($pagina - 1) * $porPagina;
        $sql    = "SELECT m.*, p.nombre AS producto_nombre
                   FROM movimientos m
                   JOIN productos p ON m.producto_id = p.id
                   WHERE m.producto_id = :producto_id" . $this->bizWhere() . "
                   ORDER BY m.fecha DESC
                   LIMIT :limite OFFSET :offset";
        $stmt   = $this->pdo->prepare($sql);
        foreach ($this->bizParam() as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':producto_id', $productoId, PDO::PARAM_INT);
        $stmt->bindValue(':limite',      $porPagina,  PDO::PARAM_INT);
        $stmt->bindValue(':offset',      $offset,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Lista movimientos filtrados para exportación CSV.
     *
     * Sin fechas, exporta los últimos 30 días por defecto.
     * Ambos extremos del rango son inclusivos.
     *
     * @param  string|null $desde      Fecha inicio en formato Y-m-d (incluida). Default: hoy - 30 días.
     * @param  string|null $hasta      Fecha fin en formato Y-m-d (incluida). Default: hoy.
     * @param  int|null    $productoId Filtro opcional por producto.
     * @param  string|null $tipo       Filtro opcional: 'entrada' o 'salida'.
     * @return array<int, array<string, mixed>> Filas de movimientos con nombre de producto.
     * @author Carlitos6712
     */
    public function listarParaExportar(
        ?string $desde = null,
        ?string $hasta = null,
        ?int $productoId = null,
        ?string $tipo = null
    ): array {
        $desde = $desde ?: date('Y-m-d', strtotime('-30 days'));
        $hasta = $hasta ?: date('Y-m-d');

        $sql    = "SELECT m.id, m.fecha, p.nombre AS producto, p.codigo_ref AS referencia,
                          m.tipo, m.cantidad, m.observaciones, m.usuario
                   FROM movimientos m
                   LEFT JOIN productos p ON m.producto_id = p.id
                   WHERE substr(m.fecha, 1, 10) >= :desde AND substr(m.fecha, 1, 10) <= :hasta";
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if ($productoId !== null) {
            $sql .= " AND m.producto_id = :producto_id";
            $params[':producto_id'] = $productoId;
        }
        if ($tipo !== null && in_array($tipo, ['entrada', 'salida'], true)) {
            $sql .= " AND m.tipo = :tipo";
            $params[':tipo'] = $tipo;
        }

        $sql .= " ORDER BY m.fecha DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Devuelve los productos con más salidas en los últimos N días.
     *
     * Útil para el dashboard analítico — bloque "Top más vendidos".
     * Usa substr(fecha,1,10) para compatibilidad SQLite+MySQL.
     *
     * @param  int $limit Número máximo de productos a devolver (default 10).
     * @param  int $dias  Período de análisis en días hacia atrás (default 30).
     * @return array<int, array{producto_id: int, nombre: string, total_salidas: int}>
     * @author Carlitos6712
     */
    public function topProductosVendidos(int $limit = 10, int $dias = 30): array
    {
        $desde  = date('Y-m-d', strtotime("-{$dias} days"));
        $params = array_merge([':desde' => $desde, ':tipo' => 'salida'], $this->bizParam());

        $sql  = "SELECT m.producto_id, p.nombre,
                        SUM(m.cantidad) AS total_salidas
                 FROM movimientos m
                 JOIN productos p ON p.id = m.producto_id
                 WHERE m.tipo = :tipo
                   AND substr(m.fecha, 1, 10) >= :desde
                   AND p.deleted_at IS NULL"
              . $this->bizWhere('m')
              . " GROUP BY m.producto_id, p.nombre
                 ORDER BY total_salidas DESC
                 LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Devuelve productos con stock > 0 que no han tenido ningún movimiento en los últimos N días.
     *
     * Útil para el dashboard analítico — bloque "Stock muerto".
     *
     * @param  int $dias Umbral de inactividad en días (default 90).
     * @return array<int, array{producto_id: int, nombre: string, stock: int, ultimo_movimiento: string|null}>
     * @author Carlitos6712
     */
    public function productosSinMovimiento(int $dias = 90): array
    {
        $cutoff = date('Y-m-d', strtotime("-{$dias} days"));
        $params = array_merge([':cutoff' => $cutoff], $this->bizParam());

        $sql  = "SELECT p.id AS producto_id, p.nombre, p.stock, p.precio,
                        COALESCE(c.nombre, 'Sin categoría') AS categoria_nombre,
                        MAX(substr(m.fecha, 1, 10)) AS ultimo_movimiento
                 FROM productos p
                 LEFT JOIN movimientos m ON m.producto_id = p.id"
              . ($this->businessId !== null ? " AND m.business_id = :biz_id2" : "")
              . " LEFT JOIN categorias c ON c.id = p.categoria_id
                 WHERE p.deleted_at IS NULL
                   AND p.stock > 0"
              . $this->bizWhere('p')
              . " GROUP BY p.id, p.nombre, p.stock, p.precio, c.nombre
                 HAVING MAX(substr(m.fecha, 1, 10)) < :cutoff
                     OR MAX(substr(m.fecha, 1, 10)) IS NULL
                 ORDER BY (p.precio * p.stock) DESC";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        if ($this->businessId !== null) {
            $stmt->bindValue(':biz_id2', $this->businessId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
