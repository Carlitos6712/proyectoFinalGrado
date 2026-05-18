<?php
/**
 * Formulario para crear nueva factura — superadmin.
 *
 * @package  Es21Plus\Superadmin\Billing
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/AppException.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/../controllers/BillingController.php';

SuperadminMiddleware::require();

$ctrl = new BillingController();
$businesses   = $ctrl->getActiveBusinesses();
$flashSuccess = '';
$flashError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = $ctrl->store($_POST);
        $_SESSION['flash_success'] = 'Factura creada correctamente.';
        header('Location: ../billing.php');
        exit;
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    }
}

$base    = '../';
$cssBase = '../../';
$pageTitle  = 'Nueva factura';
$activeMenu = 'billing';

try {
    $mensajesSinLeer = (int)\Database::getInstance()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
} catch (\Throwable) {
    $mensajesSinLeer = 0;
}

ob_start();
?>
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
    <a href="../billing.php" style="font-size:.84rem;color:var(--accent);text-decoration:none;">← Volver a Facturación</a>
</div>

<div class="sa-form-card" style="max-width:600px;">
    <h2>Nueva factura</h2>
    <form method="POST">
        <div class="form-field" style="margin-bottom:1rem;">
            <label class="field-label">Empresa</label>
            <select name="business_id" class="field-input" required>
                <option value="">— Selecciona empresa —</option>
                <?php foreach ($businesses as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= (int)($_POST['business_id'] ?? 0) === $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= ucfirst($b['plan']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field" style="margin-bottom:1rem;">
            <label class="field-label">Importe (€)</label>
            <input type="number" name="amount" class="field-input" min="0.01" step="0.01" required
                value="<?= htmlspecialchars($_POST['amount'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                placeholder="29.99">
        </div>

        <div class="sa-form-grid" style="margin-bottom:1rem;">
            <div class="form-field">
                <label class="field-label">Periodo inicio</label>
                <input type="date" name="period_start" class="field-input"
                    value="<?= htmlspecialchars($_POST['period_start'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <label class="field-label">Periodo fin</label>
                <input type="date" name="period_end" class="field-input"
                    value="<?= htmlspecialchars($_POST['period_end'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="form-field" style="margin-bottom:1.5rem;">
            <label class="field-label">Notas (opcional)</label>
            <textarea name="notes" class="field-input" rows="3"
                placeholder="Observaciones internas…"><?= htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-primary">Crear factura</button>
            <a href="../billing.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
