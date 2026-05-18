<?php
/**
 * Controlador de facturación y precios de planes.
 *
 * Gestiona facturas (CRUD) y la tabla plan_prices.
 *
 * @package  Es21Plus\Superadmin\Controllers
 * @author   Carlos Vico
 * @version  1.0.0
 */

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppException.php';

class BillingController
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ──────────────────────────────────────────
    //  Facturas: listado paginado
    // ──────────────────────────────────────────

    /**
     * Devuelve facturas paginadas con filtros opcionales.
     *
     * @param array<string,mixed> $filters  Keys: status, business_id, q
     * @param int                 $page
     * @param int                 $perPage
     * @return array{items: array, total: int, pages: int}
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['business_id'])) {
            $where[]  = 'i.business_id = ?';
            $params[] = (int)$filters['business_id'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(i.invoice_number LIKE ? OR b.name LIKE ?)';
            $like     = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM invoices i
             LEFT JOIN businesses b ON b.id = i.business_id
             WHERE {$sql}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $pages  = max(1, (int)ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $itemStmt = $this->pdo->prepare(
            "SELECT i.*, b.name AS business_name, b.plan AS business_plan
             FROM invoices i
             LEFT JOIN businesses b ON b.id = i.business_id
             WHERE {$sql}
             ORDER BY i.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $itemStmt->execute(array_merge($params, [$perPage, $offset]));

        return ['items' => $itemStmt->fetchAll(), 'total' => $total, 'pages' => $pages];
    }

    // ──────────────────────────────────────────
    //  Facturas: crear
    // ──────────────────────────────────────────

    /**
     * Crea una nueva factura con número auto-generado.
     *
     * @param array<string,mixed> $data Keys: business_id, amount, period_start, period_end, notes
     * @throws AppException Si los datos son inválidos.
     * @return int ID de la factura creada.
     */
    public function store(array $data): int
    {
        $businessId = (int)($data['business_id'] ?? 0);
        $amount     = (float)($data['amount'] ?? 0);

        if ($businessId <= 0) {
            throw new AppException('Selecciona un negocio válido.');
        }
        if ($amount <= 0) {
            throw new AppException('El importe debe ser mayor que 0.');
        }

        // Verificar que el negocio existe
        $stmt = $this->pdo->prepare('SELECT id FROM businesses WHERE id = ?');
        $stmt->execute([$businessId]);
        if (!$stmt->fetch()) {
            throw new AppException('Negocio no encontrado.');
        }

        $invoiceNumber = $this->generateInvoiceNumber();
        $periodStart   = !empty($data['period_start']) ? $data['period_start'] : null;
        $periodEnd     = !empty($data['period_end'])   ? $data['period_end']   : null;
        $notes         = trim($data['notes'] ?? '');

        $this->pdo->prepare(
            'INSERT INTO invoices (business_id, invoice_number, amount, period_start, period_end, notes)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$businessId, $invoiceNumber, $amount, $periodStart, $periodEnd, $notes ?: null]);

        return (int)$this->pdo->lastInsertId();
    }

    // ──────────────────────────────────────────
    //  Facturas: marcar como pagada
    // ──────────────────────────────────────────

    /**
     * Marca una factura como pagada.
     *
     * @param  int $id
     * @throws AppException Si la factura no existe o no está en estado pendiente.
     */
    public function markPaid(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT status FROM invoices WHERE id = ?');
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            throw new AppException('Factura no encontrada.');
        }
        if ($invoice['status'] !== 'pending') {
            throw new AppException('Solo se pueden marcar como pagadas las facturas pendientes.');
        }

        $this->pdo->prepare(
            "UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ?"
        )->execute([$id]);
    }

    // ──────────────────────────────────────────
    //  Facturas: cancelar
    // ──────────────────────────────────────────

    /**
     * Cancela una factura pendiente.
     *
     * @param  int $id
     * @throws AppException Si la factura no existe o no está pendiente.
     */
    public function cancel(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT status FROM invoices WHERE id = ?');
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            throw new AppException('Factura no encontrada.');
        }
        if ($invoice['status'] !== 'pending') {
            throw new AppException('Solo se pueden cancelar facturas pendientes.');
        }

        $this->pdo->prepare(
            "UPDATE invoices SET status = 'cancelled' WHERE id = ?"
        )->execute([$id]);
    }

    // ──────────────────────────────────────────
    //  Facturas: obtener por ID (para PDF)
    // ──────────────────────────────────────────

    /**
     * Devuelve una factura con los datos del negocio.
     *
     * @param  int   $id
     * @return array
     * @throws AppException Si no existe.
     */
    public function find(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, b.name, b.email, b.plan
             FROM invoices i
             LEFT JOIN businesses b ON b.id = i.business_id
             WHERE i.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new AppException('Factura no encontrada.');
        }
        return $row;
    }

    // ──────────────────────────────────────────
    //  Plan prices: leer y actualizar
    // ──────────────────────────────────────────

    /**
     * Devuelve los tres planes con sus precios y features.
     *
     * @return array<string,array>
     */
    public function getPlanPrices(): array
    {
        $rows = $this->pdo->query(
            "SELECT * FROM plan_prices ORDER BY FIELD(plan,'free','basic','pro')"
        )->fetchAll();

        $plans = [];
        foreach ($rows as $r) {
            $r['features'] = json_decode($r['features'] ?? '{}', true) ?? [];
            $plans[$r['plan']] = $r;
        }
        return $plans;
    }

    /**
     * Actualiza el precio mensual, anual y features de un plan.
     *
     * @param string $plan         'free'|'basic'|'pro'
     * @param float  $monthly
     * @param float  $annual
     * @param array  $features
     * @throws AppException Si el plan no es válido.
     */
    public function updatePlanPrice(string $plan, float $monthly, float $annual, array $features): void
    {
        if (!in_array($plan, ['free', 'basic', 'pro'], true)) {
            throw new AppException('Plan no válido.');
        }
        $this->pdo->prepare(
            'UPDATE plan_prices SET monthly_price = ?, annual_price = ?, features = ? WHERE plan = ?'
        )->execute([$monthly, $annual, json_encode($features, JSON_UNESCAPED_UNICODE), $plan]);
    }

    /**
     * Lista todos los negocios activos (para el select de crear factura).
     *
     * @return array
     */
    public function getActiveBusinesses(): array
    {
        return $this->pdo->query(
            "SELECT id, name, plan FROM businesses WHERE is_active = 1 ORDER BY name"
        )->fetchAll();
    }

    // ──────────────────────────────────────────
    //  KPIs rápidos
    // ──────────────────────────────────────────

    /**
     * Devuelve totales de facturación por estado.
     *
     * @return array{pending_total: float, paid_total: float, cancelled_total: float, pending_count: int, paid_count: int}
     */
    public function kpis(): array
    {
        $row = $this->pdo->query(
            "SELECT
                SUM(CASE WHEN status='pending'   THEN amount ELSE 0 END) AS pending_total,
                SUM(CASE WHEN status='paid'       THEN amount ELSE 0 END) AS paid_total,
                SUM(CASE WHEN status='cancelled'  THEN amount ELSE 0 END) AS cancelled_total,
                SUM(CASE WHEN status='pending'    THEN 1 ELSE 0 END)     AS pending_count,
                SUM(CASE WHEN status='paid'       THEN 1 ELSE 0 END)     AS paid_count
             FROM invoices"
        )->fetch();

        return [
            'pending_total'   => (float)($row['pending_total']   ?? 0),
            'paid_total'      => (float)($row['paid_total']       ?? 0),
            'cancelled_total' => (float)($row['cancelled_total']  ?? 0),
            'pending_count'   => (int)($row['pending_count']      ?? 0),
            'paid_count'      => (int)($row['paid_count']         ?? 0),
        ];
    }

    // ──────────────────────────────────────────
    //  Helper privado
    // ──────────────────────────────────────────

    private function generateInvoiceNumber(): string
    {
        $year  = date('Y');
        $count = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM invoices WHERE YEAR(created_at) = {$year}"
        )->fetchColumn();
        return 'FAC-' . $year . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}
