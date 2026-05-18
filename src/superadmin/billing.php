<?php
/**
 * Listado y gestión de facturas — panel superadmin.
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/controllers/BillingController.php';

SuperadminMiddleware::require();

$ctrl = new BillingController();
$flashSuccess = '';
$flashError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    try {
        if ($action === 'mark_paid') {
            $ctrl->markPaid((int)$_POST['id']);
            $flashSuccess = 'Factura marcada como pagada.';
        } elseif ($action === 'cancel') {
            $ctrl->cancel((int)$_POST['id']);
            $flashSuccess = 'Factura cancelada.';
        }
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    }
}

$filters = [
    'status'      => $_GET['status']      ?? '',
    'business_id' => (int)($_GET['business_id'] ?? 0) ?: null,
    'q'           => trim($_GET['q'] ?? ''),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$data = $ctrl->list($filters, $page);
$kpis = $ctrl->kpis();

$base    = './';
$cssBase = '../';
$pageTitle  = 'Facturación';
$activeMenu = 'billing';

try {
    $mensajesSinLeer = (int)\Database::getInstance()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
} catch (\Throwable) {
    $mensajesSinLeer = 0;
}

$statusColors = ['pending'=>'badge-unread','paid'=>'badge-active','cancelled'=>'badge-inactive'];
$statusLabels = ['pending'=>'Pendiente','paid'=>'Pagada','cancelled'=>'Cancelada'];

ob_start();
?>
<!-- KPIs -->
<div class="sa-metric-grid" style="margin-bottom:1.25rem;">
    <div class="sa-metric-card">
        <div class="sa-metric-label">Por cobrar (pendientes)</div>
        <div class="sa-metric-value"><?= number_format($kpis['paid_total'], 2, ',', '.') ?> €</div>
        <div style="font-size:.78rem;color:var(--text-muted);margin-top:.3rem;"><?= $kpis['pending_count'] ?> facturas</div>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Total cobrado</div>
        <div class="sa-metric-value"><?= number_format($kpis['paid_total'], 2, ',', '.') ?> €</div>
        <div style="font-size:.78rem;color:var(--text-muted);margin-top:.3rem;"><?= $kpis['paid_count'] ?> facturas</div>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Acciones</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem;">
            <a href="billing/create.php" class="btn btn-primary" style="font-size:.82rem;padding:.4rem .85rem;">+ Nueva factura</a>
            <a href="settings/plans.php" class="btn btn-secondary" style="font-size:.82rem;padding:.4rem .85rem;">Precios planes</a>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="sa-form-card" style="margin-bottom:1rem;padding:1rem 1.25rem;">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-field" style="margin:0;flex:1;min-width:160px;">
            <label class="field-label" style="font-size:.78rem;">Buscar</label>
            <input type="text" name="q" class="field-input" style="padding:.35rem .6rem;font-size:.82rem;"
                placeholder="N.° factura, empresa…"
                value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-field" style="margin:0;">
            <label class="field-label" style="font-size:.78rem;">Estado</label>
            <select name="status" class="field-input" style="padding:.35rem .6rem;font-size:.82rem;">
                <option value="">Todos</option>
                <option value="pending"   <?= $filters['status'] === 'pending'   ? 'selected' : '' ?>>Pendiente</option>
                <option value="paid"      <?= $filters['status'] === 'paid'      ? 'selected' : '' ?>>Pagada</option>
                <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary" style="padding:.4rem .85rem;font-size:.82rem;">Filtrar</button>
        <a href="billing.php" class="btn" style="padding:.4rem .85rem;font-size:.82rem;background:var(--surface);border:1px solid var(--border);color:var(--text-muted);border-radius:.4rem;text-decoration:none;">Limpiar</a>
    </form>
</div>

<!-- Tabla -->
<div class="sa-table-card">
    <div class="sa-table-header">
        <h3>Facturas (<?= number_format($data['total']) ?>)</h3>
    </div>
    <?php if (empty($data['items'])): ?>
        <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin facturas con los filtros aplicados.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead>
            <tr>
                <th>N.° Factura</th>
                <th>Empresa</th>
                <th>Plan</th>
                <th>Importe</th>
                <th>Estado</th>
                <th>Emitida</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data['items'] as $inv): ?>
            <tr>
                <td><code style="font-size:.78rem;"><?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= htmlspecialchars($inv['business_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge-<?= $inv['business_plan'] ?? 'free' ?>"><?= ucfirst($inv['business_plan'] ?? 'free') ?></span></td>
                <td style="font-weight:600;"><?= number_format((float)$inv['amount'], 2, ',', '.') ?> €</td>
                <td><span class="<?= $statusColors[$inv['status']] ?? 'badge-free' ?>"><?= $statusLabels[$inv['status']] ?? $inv['status'] ?></span></td>
                <td style="color:var(--text-muted);font-size:.78rem;"><?= date('d/m/Y', strtotime($inv['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                        <a href="billing/pdf.php?id=<?= $inv['id'] ?>" class="btn btn-secondary" style="padding:.25rem .5rem;font-size:.72rem;" target="_blank">PDF</a>
                        <?php if ($inv['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Marcar como pagada?')">
                            <input type="hidden" name="_action" value="mark_paid">
                            <input type="hidden" name="id" value="<?= $inv['id'] ?>">
                            <button type="submit" class="btn btn-secondary" style="padding:.25rem .5rem;font-size:.72rem;color:#15803d;">Pagada</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Cancelar factura?')">
                            <input type="hidden" name="_action" value="cancel">
                            <input type="hidden" name="id" value="<?= $inv['id'] ?>">
                            <button type="submit" class="btn btn-secondary" style="padding:.25rem .5rem;font-size:.72rem;color:#dc2626;">Cancelar</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($data['pages'] > 1): ?>
    <div style="padding:.75rem 1.25rem;display:flex;gap:.4rem;align-items:center;border-top:1px solid var(--border);">
        <?php
        $baseUrl = 'billing.php?' . http_build_query(array_filter(['status'=>$filters['status'],'q'=>$filters['q']]));
        for ($p = 1; $p <= $data['pages']; $p++):
        ?>
            <a href="<?= $baseUrl ?>&page=<?= $p ?>"
               style="padding:.3rem .6rem;border-radius:.35rem;font-size:.78rem;text-decoration:none;
                      background:<?= $p === $page ? '#6366f1' : 'var(--surface)' ?>;
                      color:<?= $p === $page ? '#fff' : 'var(--text-muted)' ?>;
                      border:1px solid <?= $p === $page ? '#6366f1' : 'var(--border)' ?>;">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/views/layout.php';
