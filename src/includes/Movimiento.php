<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Producto.php';

/**
 * Modelo de gestión de movimientos de stock (entradas/salidas).
 *
 * @package  Es21Plus\Includes
 * @author   Carlos Vico
 * @author   miguelrechefdez
 * @version  1.0.0
 */
class Movimiento
{
    private PDO $pdo;
    private Producto $productoModel;

    /**
     * @throws AppException Si falla la conexión.
     */
    public function __construct()
    {
        $this->pdo           = Database::getInstance();
        $this->productoModel = new Producto();
    }

    /**
     * Registra un movimiento y actualiza el stock del producto.
     *
     * @param int    $productoId   ID del producto.
     * @param string $tipo         'entrada' o 'salida'.
     * @param int    $cantidad     Unidades del movimiento (> 0).
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
            $stmt = $this->pdo->prepare(
                "INSERT INTO movimientos (producto_id, tipo, cantidad, observaciones)
                 VALUES (:producto_id, :tipo, :cantidad, :observaciones)"
            );
            $stmt->execute([
                ':producto_id'  => $productoId,
                ':tipo'         => $tipo,
                ':cantidad'     => $cantidad,
                ':observaciones'=> $observaciones,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
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
             WHERE m.producto_id = :producto_id
             ORDER BY m.fecha DESC"
        );
        $stmt->execute([':producto_id' => $productoId]);
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
             FROM movimientos
             WHERE producto_id = :producto_id"
        );
        $stmt->execute([':producto_id' => $productoId]);
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
        $stmt = $this->pdo->prepare(
            "SELECT m.*, p.nombre AS producto_nombre
             FROM movimientos m
             JOIN productos p ON m.producto_id = p.id
             ORDER BY m.fecha DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retorna estadísticas de movimientos agrupadas por día (últimos N días).
     *
     * @author Carlos Vico
 * @author   miguelrechefdez
     * @param int $dias Número de días a consultar (default 7).
     * @return array<int, array{fecha: string, entradas: int, salidas: int}>
     */
    public function estadisticasPorDia(int $dias = 7): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                DATE(fecha) AS fecha,
                SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE 0 END) AS entradas,
                SUM(CASE WHEN tipo = 'salida'  THEN cantidad ELSE 0 END) AS salidas
             FROM movimientos
             WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
             GROUP BY DATE(fecha)
             ORDER BY DATE(fecha) ASC"
        );
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Cuenta el total de movimientos del mes actual.
     *
     * @author Carlos Vico
 * @author   miguelrechefdez
     * @return int
     */
    public function contarEsteMes(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM movimientos
             WHERE YEAR(fecha) = YEAR(CURDATE())
               AND MONTH(fecha) = MONTH(CURDATE())"
        );
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
            "SELECT COUNT(*) FROM movimientos WHERE producto_id = :producto_id"
        );
        $stmt->execute([':producto_id' => $productoId]);
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
        $stmt   = $this->pdo->prepare(
            "SELECT m.*, p.nombre AS producto_nombre
             FROM movimientos m
             JOIN productos p ON m.producto_id = p.id
             WHERE m.producto_id = :producto_id
             ORDER BY m.fecha DESC
             LIMIT :limite OFFSET :offset"
        );
        $stmt->bindValue(':producto_id', $productoId, PDO::PARAM_INT);
        $stmt->bindValue(':limite',      $porPagina,  PDO::PARAM_INT);
        $stmt->bindValue(':offset',      $offset,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
