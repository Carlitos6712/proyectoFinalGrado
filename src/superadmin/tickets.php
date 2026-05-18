<?php
/**
 * Listado de tickets de soporte — panel superadmin.
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/controllers/TicketController.php';

SuperadminMiddleware::require();

$ctrl = new TicketController();

$filters = [
    'status'      => $_GET['status']      ?? '',
    'priority'    => $_GET['priority']    ?? '',
    'business_id' => (int)($_GET['business_id'] ?? 0) ?: null,
    'q'           => trim($_GET['q'] ?? ''),
];

$page = max(1, (int)($_GET['page'] ?? 1));
$data = $ctrl->list($filters, $page);

$base    = './';
$cssBase = '../';
$pageTitle  = 'Tickets de soporte';
$activeMenu = 'tickets';

try {
    $mensajesSinLeer = (int)\Database::getInstance()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
} catch (\Throwable) {
    $mensajesSinLeer = 0;
}

// Labels
$statusColors = [
    'open'        => 'badge-unread',
    'in_progress' => 'badge-basic',
    'resolved'    => 'badge-active',
    'closed'      => 'badge-free',
];
$statusLabels = [
    'open'        => 'Abierto',
    'in_progress' => 'En proceso',
    'resolved'    => 'Resuelto',
    'closed'      => 'Cerrado',
];
$priorityColors = [
    'urgent' => '#dc2626',
    'high'   => '#ea580c',
    'normal' => '#2563eb',
    'low'    => '#64748b',
];
$priorityLabels = [
    'urgent' => 'Urgente',
    'high'   => 'Alta',
    'normal' => 'Normal',
    'low'    => 'Baja',
];

ob_start();
?>
<!-- Filtros -->
<div class="sa-form-card" style="margin-bottom:1rem;padding:1rem 1.25rem;">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-field" style="margin:0;flex:1;min-width:160px;">
            <label class="field-label" style="font-size:.78rem;">Buscar</label>
            <input type="text" name="q" class="field-input" style="padding:.35rem .6rem;font-size:.82rem;"
                placeholder="Ticket, asunto, empresa…"
                value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-field" style="margin:0;">
            <label class="field-label" style="font-size:.78rem;">Estado</label>
            <select name="status" class="field-input" style="padding:.35rem .6rem;font-size:.82rem;">
                <option value="">Todos</option>
                <?php foreach (['open'=>'Abierto','in_progress'=>'En proceso','resolved'=>'Resuelto','closed'=>'Cerrado'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field" style="margin:0;">
            <label class="field-label" style="font-size:.78rem;">Prioridad</label>
            <select name="priority" class="field-input" style="padding:.35rem .6rem;font-size:.82rem;">
                <option value="">Todas</option>
                <?php foreach (['urgent'=>'Urgente','high'=>'Alta','normal'=>'Normal','low'=>'Baja'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= $filters['priority'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary" style="padding:.4rem .85rem;font-size:.82rem;">Filtrar</button>
        <a href="tickets.php" class="btn" style="padding:.4rem .85rem;font-size:.82rem;background:var(--surface);border:1px solid var(--border);color:var(--text-muted);border-radius:.4rem;text-decoration:none;">Limpiar</a>
    </form>
</div>

<!-- Tabla -->
<div class="sa-table-card">
    <div class="sa-table-header">
        <h3>Tickets (<?= number_format($data['total']) ?>)</h3>
    </div>
    <?php if (empty($data['items'])): ?>
        <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin tickets con los filtros aplicados.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead>
            <tr>
                <th>Ticket</th>
                <th>Empresa</th>
                <th>Asunto</th>
                <th>Estado</th>
                <th>Prioridad</th>
                <th>Mensajes</th>
                <th>Actualizado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data['items'] as $t): ?>
            <tr>
                <td><code style="font-size:.78rem;"><?= htmlspecialchars($t['ticket_number'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= htmlspecialchars($t['business_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td>
                    <span class="<?= $statusColors[$t['status']] ?? 'badge-free' ?>">
                        <?= $statusLabels[$t['status']] ?? $t['status'] ?>
                    </span>
                </td>
                <td>
                    <span style="font-size:.72rem;font-weight:600;color:<?= $priorityColors[$t['priority']] ?? '#64748b' ?>;">
                        <?= $priorityLabels[$t['priority']] ?? $t['priority'] ?>
                    </span>
                </td>
                <td style="color:var(--text-muted);"><?= (int)$t['msg_count'] ?></td>
                <td style="color:var(--text-muted);font-size:.78rem;"><?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?></td>
                <td>
                    <a href="tickets/view.php?id=<?= $t['id'] ?>" class="btn btn-secondary" style="padding:.25rem .6rem;font-size:.75rem;">Ver</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($data['pages'] > 1): ?>
    <div style="padding:.75rem 1.25rem;display:flex;gap:.4rem;align-items:center;border-top:1px solid var(--border);">
        <?php
        $baseUrl = 'tickets.php?' . http_build_query(array_filter(array_merge($filters, ['status'=>$filters['status'],'priority'=>$filters['priority'],'q'=>$filters['q']])));
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
