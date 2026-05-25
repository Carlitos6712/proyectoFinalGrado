<?php
require_once __DIR__ . '/AppException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/Movimiento.php';

/**
 * Gestión de pedidos de reposición de inventario.
 *
 * Implementa una máquina de estados: borrador → enviado → recibido / cancelado.
 * Al transicionar a 'recibido', genera automáticamente movimientos de entrada
 * para cada línea del pedido usando Movimiento::registrar().
 *
 * @package  Es21Plus\Includes
 * @author   Carlitos6712
 * @version  1.0.0
 */
class Pedido
{
    private PDO       $pdo;
    private Auditoria $auditoria;

    /** Transiciones válidas de la máquina de estados */
    private const TRANSICIONES = [
        'borrador' => ['enviado', 'cancelado'],
        'enviado'  => ['recibido', 'cancelado'],
        'recibido' => [],
        'cancelado'=> [],
    ];

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
     * Lista todos los pedidos con datos del proveedor y conteo de líneas.
     *
     * @param string|null $estado Filtro opcional por estado.
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function listar(?string $estado = null): array
    {
        $where  = '';
        $params = [];
        if ($estado !== null && $estado !== '') {
            $where          = " WHERE p.estado = :estado";
            $params[':estado'] = $estado;
        }

        $sql = "SELECT p.*,
                       prov.nombre   AS proveedor_nombre,
                       prov.email    AS proveedor_email,
                       prov.telefono AS proveedor_telefono,
                       COUNT(pl.id)  AS total_lineas
                FROM pedidos p
                LEFT JOIN proveedores prov ON prov.id = p.proveedor_id
                LEFT JOIN pedidos_lineas pl ON pl.pedido_id = p.id
                {$where}
                GROUP BY p.id
                ORDER BY p.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un pedido por su ID junto con los datos del proveedor.
     *
     * @param int $id ID del pedido.
     * @throws AppException Si el pedido no existe (404).
     * @return array<string, mixed>
     * @author Carlitos6712
     */
    public function obtener(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*,
                    prov.nombre   AS proveedor_nombre,
                    prov.email    AS proveedor_email,
                    prov.telefono AS proveedor_telefono,
                    prov.contacto AS proveedor_contacto
             FROM pedidos p
             LEFT JOIN proveedores prov ON prov.id = p.proveedor_id
             WHERE p.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new AppException("Pedido #{$id} no encontrado.", 404);
        }
        return $row;
    }

    /**
     * Lista las líneas de un pedido con el nombre del producto.
     *
     * @param int $pedidoId ID del pedido.
     * @return array<int, array<string, mixed>>
     * @author Carlitos6712
     */
    public function obtenerLineas(int $pedidoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pl.*, pr.nombre AS producto_nombre
             FROM pedidos_lineas pl
             JOIN productos pr ON pr.id = pl.producto_id
             WHERE pl.pedido_id = :pedido_id
             ORDER BY pl.id"
        );
        $stmt->execute([':pedido_id' => $pedidoId]);
        return $stmt->fetchAll();
    }

    /**
     * Crea un nuevo pedido en estado 'borrador'.
     *
     * @param array<string, mixed> $datos Datos del pedido: proveedor_id (nullable), notas.
     * @return int ID del pedido creado.
     * @author Carlitos6712
     */
    public function crear(array $datos): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO pedidos (proveedor_id, notas, estado)
             VALUES (:proveedor_id, :notas, 'borrador')"
        );
        $stmt->execute([
            ':proveedor_id' => $datos['proveedor_id'] ?? null,
            ':notas'        => $datos['notas']        ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->auditar('crear', $id, null, array_merge($datos, ['estado' => 'borrador']));
        return $id;
    }

    /**
     * Añade una línea a un pedido en estado 'borrador'.
     *
     * @param int        $pedidoId   ID del pedido.
     * @param int        $productoId ID del producto.
     * @param int        $cantidad   Cantidad a pedir.
     * @param float|null $precioUnit Precio unitario estimado (opcional).
     * @throws AppException Si el pedido no está en estado 'borrador' (409).
     * @return int ID de la línea creada.
     * @author Carlitos6712
     */
    public function añadirLinea(int $pedidoId, int $productoId, int $cantidad, ?float $precioUnit = null): int
    {
        $pedido = $this->obtener($pedidoId);
        if ($pedido['estado'] !== 'borrador') {
            throw new AppException(
                "No se pueden añadir líneas a un pedido en estado '{$pedido['estado']}'.",
                409
            );
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO pedidos_lineas (pedido_id, producto_id, cantidad, precio_unit)
             VALUES (:pedido_id, :producto_id, :cantidad, :precio_unit)"
        );
        $stmt->execute([
            ':pedido_id'   => $pedidoId,
            ':producto_id' => $productoId,
            ':cantidad'    => $cantidad,
            ':precio_unit' => $precioUnit,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Cambia el estado de un pedido aplicando la máquina de estados.
     *
     * Transiciones válidas:
     * - borrador → enviado (actualiza enviado_at)
     * - borrador → cancelado
     * - enviado  → recibido (actualiza recibido_at + genera movimientos de entrada)
     * - enviado  → cancelado
     *
     * @param int    $id          ID del pedido.
     * @param string $nuevoEstado Nuevo estado destino.
     * @throws AppException Si la transición no es válida (409).
     * @return bool
     * @author Carlitos6712
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $pedido       = $this->obtener($id);
        $estadoActual = $pedido['estado'];

        $permitidos = self::TRANSICIONES[$estadoActual] ?? [];
        if (!in_array($nuevoEstado, $permitidos, true)) {
            throw new AppException(
                "Transición no válida: '{$estadoActual}' → '{$nuevoEstado}'.",
                409
            );
        }

        $timestampCol = match ($nuevoEstado) {
            'enviado'  => ", enviado_at = CURRENT_TIMESTAMP",
            'recibido' => ", recibido_at = CURRENT_TIMESTAMP",
            default    => "",
        };

        $stmt = $this->pdo->prepare(
            "UPDATE pedidos SET estado = :estado{$timestampCol} WHERE id = :id"
        );
        $resultado = $stmt->execute([':estado' => $nuevoEstado, ':id' => $id]);

        if ($resultado && $nuevoEstado === 'recibido') {
            $this->generarMovimientosEntrada($id);
        }

        if ($resultado) {
            $this->auditar('actualizar', $id, ['estado' => $estadoActual], ['estado' => $nuevoEstado]);
        }

        return $resultado;
    }

    /**
     * Genera movimientos de entrada para cada línea del pedido al recibirlo.
     *
     * @param int $id ID del pedido.
     * @return void
     * @author Carlitos6712
     */
    private function generarMovimientosEntrada(int $id): void
    {
        $movimientoModel = new Movimiento($this->pdo);
        $lineas          = $this->obtenerLineas($id);
        foreach ($lineas as $linea) {
            $movimientoModel->registrar(
                (int) $linea['producto_id'],
                'entrada',
                (int) $linea['cantidad'],
                "Pedido de reposición #{$id}"
            );
        }
    }

    /**
     * Cuenta los pedidos pendientes (estado 'borrador' o 'enviado').
     *
     * @return int
     * @author Carlitos6712
     */
    public function contarPendientes(): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM pedidos WHERE estado IN ('borrador', 'enviado')"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Genera y envía un PDF del pedido directamente al navegador.
     *
     * @param int $id ID del pedido.
     * @return void
     * @author Carlitos6712
     */
    public function exportarPdf(int $id): void
    {
        require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';
        $pedido = $this->obtener($id);
        $lineas = $this->obtenerLineas($id);

        $pdf = new FPDF();
        $pdf->AddPage();

        // ── Cabecera ──────────────────────────────────────────────────────────
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Pedido de Reposicion #' . $id, 0, 1, 'C');

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Fecha: ' . date('d/m/Y', strtotime($pedido['created_at'])), 0, 1, 'C');
        $pdf->Cell(0, 6, 'Estado: ' . strtoupper($pedido['estado']), 0, 1, 'C');
        $pdf->Ln(4);

        // ── Proveedor ─────────────────────────────────────────────────────────
        if (!empty($pedido['proveedor_nombre'])) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, 'Proveedor', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 6, 'Nombre: ' . $pedido['proveedor_nombre'], 0, 1);
            if (!empty($pedido['proveedor_email'])) {
                $pdf->Cell(0, 6, 'Email: ' . $pedido['proveedor_email'], 0, 1);
            }
            if (!empty($pedido['proveedor_telefono'])) {
                $pdf->Cell(0, 6, 'Telefono: ' . $pedido['proveedor_telefono'], 0, 1);
            }
            $pdf->Ln(4);
        }

        // ── Tabla de líneas ───────────────────────────────────────────────────
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(80, 7, 'Producto',     1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Cantidad',     1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Precio Unit.', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Total Linea',  1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 10);
        $totalGeneral = 0.0;

        foreach ($lineas as $linea) {
            $precioUnit = (float) ($linea['precio_unit'] ?? 0);
            $totalLinea = $precioUnit * (int) $linea['cantidad'];
            $totalGeneral += $totalLinea;

            $pdf->Cell(80, 6, $linea['producto_nombre'],                   1, 0);
            $pdf->Cell(25, 6, (string)(int)$linea['cantidad'],             1, 0, 'C');
            $pdf->Cell(35, 6, number_format($precioUnit, 2) . ' EUR',      1, 0, 'R');
            $pdf->Cell(40, 6, number_format($totalLinea, 2) . ' EUR',      1, 1, 'R');
        }

        // ── Fila de total ─────────────────────────────────────────────────────
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(140, 7, 'TOTAL',                                   1, 0, 'R');
        $pdf->Cell(40,  7, number_format($totalGeneral, 2) . ' EUR',  1, 1, 'R');

        // ── Notas ─────────────────────────────────────────────────────────────
        if (!empty($pedido['notas'])) {
            $pdf->Ln(6);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'Notas:', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 5, $pedido['notas']);
        }

        $pdf->Output('I', "pedido_{$id}.pdf");
        exit;
    }

    /**
     * Registra una operación en el log de auditoría sin propagar errores.
     *
     * @param string     $accion   'crear' | 'actualizar'.
     * @param int        $id       PK del pedido.
     * @param array|null $anterior Datos antes de la operación.
     * @param array|null $nuevo    Datos después de la operación.
     * @return void
     * @author Carlitos6712
     */
    private function auditar(string $accion, int $id, ?array $anterior, ?array $nuevo): void
    {
        try {
            $this->auditoria->registrar('pedidos', $id, $accion, $anterior, $nuevo);
        } catch (\Throwable $e) {
            error_log("Auditoria::pedidos: {$e->getMessage()}");
        }
    }
}
