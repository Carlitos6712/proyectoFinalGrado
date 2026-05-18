<?php
/**
 * Gestión de precios y límites de planes — superadmin.
 *
 * @package  Es21Plus\Superadmin\Settings
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
$flashSuccess = '';
$flashError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['free', 'basic', 'pro'] as $plan) {
        if (!isset($_POST["monthly_{$plan}"])) {
            continue;
        }
        try {
            $features = [
                'max_products'  => $_POST["max_products_{$plan}"]  === '-1' ? -1 : (int)$_POST["max_products_{$plan}"],
                'max_employees' => $_POST["max_employees_{$plan}"] === '-1' ? -1 : (int)$_POST["max_employees_{$plan}"],
                'reports'       => isset($_POST["reports_{$plan}"]),
            ];
            $ctrl->updatePlanPrice(
                $plan,
                (float)$_POST["monthly_{$plan}"],
                (float)$_POST["annual_{$plan}"],
                $features
            );
        } catch (AppException $e) {
            $flashError = $e->getMessage();
        }
    }
    if (!$flashError) {
        $flashSuccess = 'Precios de planes actualizados.';
    }
}

$plans = $ctrl->getPlanPrices();

$base    = '../';
$cssBase = '../../';
$pageTitle  = 'Precios de Planes';
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

<form method="POST">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.25rem;">
<?php foreach (['free'=>'Free','basic'=>'Basic','pro'=>'Pro'] as $planKey => $planLabel):
    $p = $plans[$planKey] ?? [];
    $f = $p['features'] ?? [];
    $maxProducts  = $f['max_products']  ?? 50;
    $maxEmployees = $f['max_employees'] ?? 2;
    $reports      = $f['reports']       ?? false;
?>
<div class="sa-form-card">
    <h2 style="margin-bottom:1.25rem;">
        <span class="badge-<?= $planKey ?>"><?= $planLabel ?></span>
    </h2>

    <div class="form-field" style="margin-bottom:.75rem;">
        <label class="field-label" style="font-size:.78rem;">Precio mensual (€)</label>
        <input type="number" name="monthly_<?= $planKey ?>" class="field-input" min="0" step="0.01"
            value="<?= number_format((float)($p['monthly_price'] ?? 0), 2, '.', '') ?>">
    </div>
    <div class="form-field" style="margin-bottom:.75rem;">
        <label class="field-label" style="font-size:.78rem;">Precio anual (€)</label>
        <input type="number" name="annual_<?= $planKey ?>" class="field-input" min="0" step="0.01"
            value="<?= number_format((float)($p['annual_price'] ?? 0), 2, '.', '') ?>">
    </div>

    <div style="border-top:1px solid var(--border);margin:.75rem 0;padding-top:.75rem;">
        <p style="font-size:.75rem;font-weight:600;color:var(--text-muted);margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.05em;">Límites del plan</p>

        <div class="form-field" style="margin-bottom:.75rem;">
            <label class="field-label" style="font-size:.78rem;">Máx. productos (-1 = ilimitado)</label>
            <input type="number" name="max_products_<?= $planKey ?>" class="field-input" min="-1"
                value="<?= (int)$maxProducts ?>">
        </div>
        <div class="form-field" style="margin-bottom:.75rem;">
            <label class="field-label" style="font-size:.78rem;">Máx. empleados (-1 = ilimitado)</label>
            <input type="number" name="max_employees_<?= $planKey ?>" class="field-input" min="-1"
                value="<?= (int)$maxEmployees ?>">
        </div>
        <div class="toggle-wrap" style="margin-top:.5rem;">
            <input type="checkbox" id="reports_<?= $planKey ?>" name="reports_<?= $planKey ?>"
                class="sa-toggle" <?= $reports ? 'checked' : '' ?>>
            <label for="reports_<?= $planKey ?>" style="font-size:.82rem;color:var(--text-primary);cursor:pointer;">
                Acceso a reportes
            </label>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<div style="margin-top:1.5rem;">
    <button type="submit" class="btn btn-primary">Guardar todos los planes</button>
</div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
