<?php
/**
 * Modelo de auditoría de cambios en el inventario.
 *
 * Registra cada operación de crear, actualizar y eliminar en las entidades
 * principales (productos, categorias), almacenando los datos anteriores y
 * nuevos en formato JSON para permitir visualizar el diff de cambios.
 * Los registros de auditoría son inmutables: no se expone ningún método
 * de borrado.
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
 * @version  1.0.0
 */

require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';

class Auditoria
{
    /**
     * @param PDO $pdo Conexión PDO inyectada.
     * @author Carlitos6712
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Crea una instancia usando la conexión singleton.
     *
     * @return self
     * @author Carlitos6712
     */
    public static function instancia(): self
    {
        return new self(Database::getInstance());
    }

    /**
     * Registra una operación de auditoría con diff JSON.
     *
     * @param string     $tabla           Tabla afectada ('productos', 'categorias').
     * @param int        $registroId      PK del registro afectado.
     * @param string     $accion          'crear' | 'actualizar' | 'eliminar'.
     * @param array|null $datosAnteriores Estado antes de la operación (null en crear).
     * @param array|null $datosNuevos     Estado después de la operación (null en eliminar).
     * @return void
     * @author Carlitos6712
     */
    public function registrar(
        string $tabla,
        int $registroId,
        string $accion,
        ?array $datosAnteriores,
        ?array $datosNuevos
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $this->pdo->prepare(
            "INSERT INTO auditoria
             (tabla, registro_id, accion, datos_anteriores, datos_nuevos, ip)
             VALUES (:tabla, :registro_id, :accion, :datos_anteriores, :datos_nuevos, :ip)"
        );
        $stmt->execute([
            ':tabla'            => $tabla,
            ':registro_id'      => $registroId,
            ':accion'           => $accion,
            ':datos_anteriores' => $datosAnteriores !== null
                ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            ':datos_nuevos'     => $datosNuevos !== null
                ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            ':ip'               => $ip,
        ]);
    }

    /**
     * Lista registros de auditoría con filtros opcionales y paginación.
     *
     * @param string|null $tabla      Filtrar por tabla afectada.
     * @param string|null $accion     Filtrar por tipo de acción.
     * @param string|null $fechaDesde Fecha inicio ISO (YYYY-MM-DD).
     * @param string|null $fechaHasta Fecha fin ISO (YYYY-MM-DD).
     * @param int         $pagina     Página actual (1-indexed).
     * @param int         $porPagina  Registros por página.
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function listar(
        ?string $tabla = null,
        ?string $accion = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        int $pagina = 1,
        int $porPagina = 25
    ): array {
        [$sql, $params] = $this->construirQuery($tabla, $accion, $fechaDesde, $fechaHasta);

        $sql   .= " ORDER BY fecha DESC";
        $offset = ($pagina - 1) * $porPagina;
        $sql   .= " LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta registros de auditoría aplicando los mismos filtros que listar().
     *
     * @param string|null $tabla
     * @param string|null $accion
     * @param string|null $fechaDesde
     * @param string|null $fechaHasta
     * @return int
     * @author Carlitos6712
     */
    public function contar(
        ?string $tabla = null,
        ?string $accion = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null
    ): int {
        [$sql, $params] = $this->construirQuery($tabla, $accion, $fechaDesde, $fechaHasta, true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Construye la cláusula SQL base con todos los filtros aplicados.
     *
     * @param string|null $tabla
     * @param string|null $accion
     * @param string|null $fechaDesde
     * @param string|null $fechaHasta
     * @param bool        $contar     Si true, genera SELECT COUNT(*).
     * @return array{0: string, 1: array<string, mixed>}
     * @author Carlitos6712
     */
    private function construirQuery(
        ?string $tabla,
        ?string $accion,
        ?string $fechaDesde,
        ?string $fechaHasta,
        bool $contar = false
    ): array {
        $select = $contar ? "SELECT COUNT(*) FROM auditoria" : "SELECT * FROM auditoria";
        $where  = [];
        $params = [];

        if ($tabla !== null && $tabla !== '') {
            $where[]         = "tabla = :tabla";
            $params[':tabla'] = $tabla;
        }
        if ($accion !== null && $accion !== '') {
            $where[]          = "accion = :accion";
            $params[':accion'] = $accion;
        }
        if ($fechaDesde !== null && $fechaDesde !== '') {
            $where[]               = "fecha >= :fecha_desde";
            $params[':fecha_desde'] = $fechaDesde . ' 00:00:00';
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $where[]               = "fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $fechaHasta . ' 23:59:59';
        }

        $sql = $select;
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        return [$sql, $params];
    }
}
