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
     * Cuenta movimientos globales con filtros opcionales.
     *
     * @param int|null    $productoId Filtro por producto.
     * @param string|null $tipo       'entrada' | 'salida' | null = todos.
     * @param string|null $desde      Fecha inicio Y-m-d.
     * @param string|null $hasta      Fecha fin Y-m-d.
     * @return int
     * @author Carlitos6712
     */
    public function contarGlobal(
        ?int $productoId = null,
        ?string $tipo    = null,
        ?string $desde   = null,
        ?string $hasta   = null
    ): int {
        [$where, $params] = $this->buildGlobalWhere($productoId, $tipo, $desde, $hasta);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM movimientos m JOIN productos p ON m.producto_id = p.id WHERE 1=1 {$where}"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista movimientos globales paginados con filtros opcionales.
     *
     * @param int         $pagina
     * @param int         $porPagina
     * @param int|null    $productoId
     * @param string|null $tipo
     * @param string|null $desde
     * @param string|null $hasta
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function listarGlobalPaginado(
        int $pagina,
        int $porPagina,
        ?int $productoId = null,
        ?string $tipo    = null,
        ?string $desde   = null,
        ?string $hasta   = null
    ): array {
        [$where, $params] = $this->buildGlobalWhere($productoId, $tipo, $desde, $hasta);
        $offset = ($pagina - 1) * $porPagina;
        $sql    = "SELECT m.*, p.nombre AS producto_nombre, p.codigo_ref, c.nombre AS categoria_nombre
                   FROM movimientos m
                   JOIN productos p ON m.producto_id = p.id
                   LEFT JOIN categorias c ON p.categoria_id = c.id
                   WHERE 1=1 {$where}
                   ORDER BY m.fecha DESC
                   LIMIT :limite OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Construye cláusula WHERE y parámetros para filtros globales.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildGlobalWhere(
        ?int $productoId,
        ?string $tipo,
        ?string $desde,
        ?string $hasta
    ): array {
        $where  = '';
        $params = [];

        if ($this->businessId !== null) {
            $where .= " AND m.business_id = :biz_id";
            $params[':biz_id'] = $this->businessId;
        }
        if ($productoId !== null) {
            $where .= " AND m.producto_id = :producto_id";
            $params[':producto_id'] = $productoId;
        }
        if ($tipo !== null && in_array($tipo, ['entrada', 'salida'], true)) {
            $where .= " AND m.tipo = :tipo";
            $params[':tipo'] = $tipo;
        }
        if ($desde !== null) {
            $where .= " AND DATE(m.fecha) >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== null) {
            $where .= " AND DATE(m.fecha) <= :hasta";
            $params[':hasta'] = $hasta;
        }
        return [$where, $params];
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
}
