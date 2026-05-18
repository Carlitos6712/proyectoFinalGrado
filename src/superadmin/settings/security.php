<?php
/**
 * Vista de seguridad del panel de superadmin.
 *
 * Muestra IPs bloqueadas, logs de sesión y acciones críticas.
 *
 * @package  Es21Plus\Superadmin\Settings
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/../../core/SecurityService.php';
require_once __DIR__ . '/../controllers/SecurityController.php';

SuperadminMiddleware::require();

$ctrl = new SecurityController();
$flashSuccess = '';
$flashError   = '';

// Desbloquear IP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'unblock_ip') {
    $ip = trim($_POST['ip'] ?? '');
    if ($ip !== '') {
        $ctrl->unblockIp($ip);
        $flashSuccess = 'IP ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . ' desbloqueada.';
    }
}

$userTypeFilter = $_GET['user_type'] ?? null;
$fechaDesde     = $_GET['fecha_desde'] ?? null;

$data = $ctrl->index($userTypeFilter, $fechaDesde);

$base    = '../';
$cssBase = '../../';
$pageTitle  = 'Seguridad';
$activeMenu = 'settings';

try {
    $mensajesSinLeer = (int)\Database::getInstance()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
} catch (\Throwable) {
    $mensajesSinLeer = 0;
}

ob_start();
?>
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
    <a href="../settings.php" style="font-size:.84rem;color:var(--accent);text-decoration:none;">← Volver a Configuración</a>
</div>

<!-- IPs bloqueadas -->
<div class="sa-table-card" style="margin-bottom:1.5rem;">
    <div class="sa-table-header">
        <h3>IPs bloqueadas actualmente</h3>
        <span style="font-size:.78rem;color:var(--text-muted);">Más de 5 intentos en 15 minutos</span>
    </div>
    <?php if (empty($data['blockedIps'])): ?>
        <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin IPs bloqueadas.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead><tr><th>IP</th><th>Intentos</th><th>Primer intento</th><th>Último intento</th><th>Acción</th></tr></thead>
        <tbody>
        <?php foreach ($data['blockedIps'] as $row): ?>
            <tr>
                <td><code><?= htmlspecialchars($row['ip_address'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><span class="badge-inactive"><?= (int)$row['intentos'] ?></span></td>
                <td style="color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime($row['primer_intento'])) ?></td>
                <td style="color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime($row['ultimo_intento'])) ?></td>
                <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Desbloquear IP?')">
                        <input type="hidden" name="_action" value="unblock_ip">
                        <input type="hidden" name="ip" value="<?= htmlspecialchars($row['ip_address'], ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-secondary" style="padding:.25rem .6rem;font-size:.78rem;">Desbloquear</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Filtros para session_logs -->
<div class="sa-form-card" style="margin-bottom:1rem;padding:1rem 1.25rem;">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-field" style="margin:0;">
            <label class="field-label" style="font-size:.78rem;">Tipo de usuario</label>
            <select name="user_type" class="field-input" style="padding:.35rem .6rem;font-size:.82rem;">
                <option value="">Todos</option>
                <option value="superadmin" <?= $userTypeFilter === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                <option value="employee"   <?= $userTypeFilter === 'employee'   ? 'selected' : '' ?>>Empleado</option>
            </select>
        </div>
        <div class="form-field" style="margin:0;">
            <label class="field-label" style="font-size:.78rem;">Desde</label>
            <input type="date" name="fecha_desde" class="field-input" style="padding:.35rem .6rem;font-size:.82rem;"
                value="<?= htmlspecialchars($fechaDesde ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn btn-secondary" style="padding:.4rem .85rem;font-size:.82rem;">Filtrar</button>
        <a href="security.php" class="btn" style="padding:.4rem .85rem;font-size:.82rem;background:var(--surface);border:1px solid var(--border);color:var(--text-muted);border-radius:.4rem;text-decoration:none;">Limpiar</a>
    </form>
</div>

<!-- Logs de sesión -->
<div class="sa-table-card" style="margin-bottom:1.5rem;">
    <div class="sa-table-header">
        <h3>Últimas 50 sesiones</h3>
    </div>
    <?php if (empty($data['sessionLogs'])): ?>
        <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin sesiones registradas.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead><tr><th>Tipo</th><th>User ID</th><th>Negocio ID</th><th>IP</th><th>Acción</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($data['sessionLogs'] as $log): ?>
            <tr>
                <td>
                    <span class="<?= $log['user_type'] === 'superadmin' ? 'badge-pro' : 'badge-basic' ?>">
                        <?= htmlspecialchars($log['user_type'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </td>
                <td><?= (int)$log['user_id'] ?></td>
                <td><?= $log['business_id'] ? (int)$log['business_id'] : '—' ?></td>
                <td><code><?= htmlspecialchars($log['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></code></td>
                <td>
                    <?php
                    $aColors = ['login' => 'badge-active', 'logout' => 'badge-free', 'expired' => 'badge-unread'];
                    ?>
                    <span class="<?= $aColors[$log['action']] ?? 'badge-free' ?>">
                        <?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </td>
                <td style="color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Acciones críticas -->
<div class="sa-table-card">
    <div class="sa-table-header">
        <h3>Acciones críticas recientes</h3>
    </div>
    <?php if (empty($data['criticalActions'])): ?>
        <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin acciones críticas registradas.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead><tr><th>Negocio</th><th>Empleado/SA</th><th>Acción</th><th>Metadata</th><th>IP</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($data['criticalActions'] as $act): ?>
            <tr>
                <td><?= htmlspecialchars($act['negocio'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($act['empleado'] ?? 'superadmin', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($act['action'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?php if ($act['metadata']): ?>
                        <details style="font-size:.75rem;">
                            <summary style="cursor:pointer;color:var(--accent);">Ver</summary>
                            <pre style="margin:.25rem 0 0;font-size:.72rem;background:#f8fafc;padding:.5rem;border-radius:.25rem;overflow:auto;max-width:260px;"><?= htmlspecialchars(
                                json_encode(json_decode($act['metadata']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                                ENT_QUOTES, 'UTF-8'
                            ) ?></pre>
                        </details>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><code style="font-size:.75rem;"><?= htmlspecialchars($act['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></code></td>
                <td style="color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime($act['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
