<?php
/**
 * Vista detalle de ticket — chat entre negocio y superadmin.
 *
 * @package  Es21Plus\Superadmin\Tickets
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/../controllers/TicketController.php';

SuperadminMiddleware::require();

$ctrl = new TicketController();
$id   = (int)($_GET['id'] ?? 0);

$flashSuccess = '';
$flashError   = '';

// Responder
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    try {
        if ($action === 'reply') {
            $saId = (int)($_SESSION['usuario_id'] ?? 1);
            $ctrl->replyAsSuperadmin($id, trim($_POST['message'] ?? ''), $saId);
            $flashSuccess = 'Respuesta enviada.';
        } elseif ($action === 'update_status') {
            $ctrl->updateStatus($id, $_POST['status'] ?? 'open', $_POST['priority'] ?? 'normal');
            $flashSuccess = 'Estado actualizado.';
        }
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    }
}

try {
    $data = $ctrl->view($id);
} catch (AppException $e) {
    http_response_code(404);
    die('Ticket no encontrado.');
}

$ticket   = $data['ticket'];
$messages = $data['messages'];

$base    = '../';
$cssBase = '../../';
$pageTitle  = 'Ticket ' . htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8');
$activeMenu = 'tickets';

try {
    $mensajesSinLeer = (int)\Database::getInstance()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
} catch (\Throwable) {
    $mensajesSinLeer = 0;
}

$statusColors = ['open'=>'badge-unread','in_progress'=>'badge-basic','resolved'=>'badge-active','closed'=>'badge-free'];
$statusLabels = ['open'=>'Abierto','in_progress'=>'En proceso','resolved'=>'Resuelto','closed'=>'Cerrado'];
$priorityLabels = ['urgent'=>'Urgente','high'=>'Alta','normal'=>'Normal','low'=>'Baja'];
$priorityColors = ['urgent'=>'#dc2626','high'=>'#ea580c','normal'=>'#2563eb','low'=>'#64748b'];

ob_start();
?>
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <a href="../tickets.php" style="font-size:.84rem;color:var(--accent);text-decoration:none;">← Volver a Tickets</a>
    <span class="<?= $statusColors[$ticket['status']] ?? 'badge-free' ?>">
        <?= $statusLabels[$ticket['status']] ?? $ticket['status'] ?>
    </span>
    <span style="font-size:.72rem;font-weight:600;color:<?= $priorityColors[$ticket['priority']] ?? '#64748b' ?>;">
        Prioridad: <?= $priorityLabels[$ticket['priority']] ?? $ticket['priority'] ?>
    </span>
    <span style="font-size:.78rem;color:var(--text-muted);margin-left:auto;">
        <?= htmlspecialchars($ticket['business_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
        · <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
    </span>
</div>

<div style="display:grid;grid-template-columns:1fr 260px;gap:1.25rem;align-items:start;">

    <!-- Hilo de mensajes -->
    <div>
        <div class="sa-table-card" style="margin-bottom:1rem;">
            <div class="sa-table-header">
                <h3><?= htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8') ?></h3>
                <code style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?></code>
            </div>
            <div style="padding:1rem 1.25rem;display:flex;flex-direction:column;gap:.75rem;" id="msgThread">
                <?php foreach ($messages as $msg): ?>
                <?php $isSA = $msg['sender_type'] === 'superadmin'; ?>
                <div style="display:flex;gap:.75rem;<?= $isSA ? 'justify-content:flex-end;' : '' ?>">
                    <?php if (!$isSA): ?>
                    <div style="width:32px;height:32px;border-radius:50%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#4338ca;flex-shrink:0;">B</div>
                    <?php endif; ?>
                    <div style="max-width:75%;background:<?= $isSA ? '#eef2ff' : '#f8fafc' ?>;border:1px solid <?= $isSA ? '#c7d2fe' : 'var(--border)' ?>;border-radius:<?= $isSA ? '1rem 1rem 0 1rem' : '1rem 1rem 1rem 0' ?>;padding:.75rem 1rem;">
                        <p style="margin:0 0 .4rem;font-size:.7rem;color:var(--text-muted);">
                            <?= $isSA ? 'Soporte es21plus' : 'Empresa' ?>
                            · <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                        </p>
                        <p style="margin:0;font-size:.85rem;white-space:pre-wrap;color:var(--text-primary);"><?= htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php if ($isSA): ?>
                    <div style="width:32px;height:32px;border-radius:50%;background:#6366f1;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#fff;flex-shrink:0;">SA</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!in_array($ticket['status'], ['closed'], true)): ?>
        <div class="sa-form-card">
            <form method="POST">
                <input type="hidden" name="_action" value="reply">
                <div class="form-field" style="margin-bottom:.75rem;">
                    <label class="field-label">Tu respuesta</label>
                    <textarea name="message" class="field-input" rows="4" required
                        placeholder="Escribe tu respuesta…" style="resize:vertical;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Enviar respuesta</button>
            </form>
        </div>
        <?php else: ?>
        <p style="color:var(--text-muted);font-size:.84rem;padding:.5rem 0;">Este ticket está cerrado.</p>
        <?php endif; ?>
    </div>

    <!-- Panel lateral: cambiar estado -->
    <div>
        <div class="sa-form-card">
            <h2 style="font-size:.9rem;margin-bottom:1rem;">Gestionar ticket</h2>
            <form method="POST">
                <input type="hidden" name="_action" value="update_status">
                <div class="form-field" style="margin-bottom:.75rem;">
                    <label class="field-label" style="font-size:.78rem;">Estado</label>
                    <select name="status" class="field-input" style="font-size:.82rem;">
                        <?php foreach (['open'=>'Abierto','in_progress'=>'En proceso','resolved'=>'Resuelto','closed'=>'Cerrado'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= $ticket['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field" style="margin-bottom:1rem;">
                    <label class="field-label" style="font-size:.78rem;">Prioridad</label>
                    <select name="priority" class="field-input" style="font-size:.82rem;">
                        <?php foreach (['urgent'=>'Urgente','high'=>'Alta','normal'=>'Normal','low'=>'Baja'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= $ticket['priority'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary" style="width:100%;font-size:.82rem;">Actualizar</button>
            </form>

            <?php if ($ticket['closed_at']): ?>
            <p style="margin-top:.75rem;font-size:.75rem;color:var(--text-muted);">
                Cerrado: <?= date('d/m/Y H:i', strtotime($ticket['closed_at'])) ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
