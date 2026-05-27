<?php
/**
 * Página de configuración global del sistema.
 *
 * Tabs sin recarga de página: General | Correo | Seguridad.
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/../core/Settings.php';
require_once __DIR__ . '/../core/Mailer.php';
require_once __DIR__ . '/controllers/SettingsController.php';

SuperadminMiddleware::require();

$ctrl = new SettingsController();
$flashSuccess = '';
$flashError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    try {
        if ($action === 'save_general') {
            $ctrl->saveGeneral($_POST);
            $flashSuccess = 'Configuración general guardada.';
        } elseif ($action === 'save_email') {
            $ctrl->saveEmail($_POST);
            $flashSuccess = 'Configuración de correo guardada.';
        }
    } catch (AppException $e) {
        $flashError = $e->getMessage();
    } catch (\Throwable) {
        $flashError = 'Error inesperado al guardar.';
    }
}

$s = $ctrl->index(); // array de settings

$base    = './';
$cssBase = '../';
$pageTitle  = 'Configuración';
$activeMenu = 'settings';

// Badge mensajes para sidebar
try {
    $mensajesSinLeer = (int)\Database::getInstance()->query(
        'SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'
    )->fetchColumn();
} catch (\Throwable) {
    $mensajesSinLeer = 0;
}

$activeTab = $_GET['tab'] ?? 'general';

ob_start();
?>

<nav class="sa-tabs-nav" id="settingsTabs">
    <button class="sa-tab-btn <?= $activeTab === 'general' ? 'active' : '' ?>" onclick="showTab('general')">General</button>
    <button class="sa-tab-btn <?= $activeTab === 'email'   ? 'active' : '' ?>" onclick="showTab('email')">Correo SMTP</button>
    <button class="sa-tab-btn" onclick="location.href='settings/security.php'">Seguridad →</button>
</nav>

<!-- TAB: General -->
<div id="tab-general" class="sa-tab-panel <?= $activeTab === 'general' ? 'active' : '' ?>">
    <div class="sa-form-card" style="max-width:680px;">
        <h2>Ajustes generales</h2>
        <form method="POST">
            <input type="hidden" name="_action" value="save_general">

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label">Nombre de la aplicación</label>
                <input type="text" name="app_name" class="field-input" required
                    value="<?= htmlspecialchars($s['app_name'] ?? 'es21plus', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label">Plan por defecto en registro</label>
                <select name="default_plan" class="field-input">
                    <?php foreach (['free','basic','pro'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($s['default_plan'] ?? 'free') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label">Días de prueba al registrarse</label>
                <input type="number" name="trial_days" class="field-input" min="0" max="365"
                    value="<?= (int)($s['trial_days'] ?? 14) ?>">
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <div class="toggle-wrap">
                    <input type="checkbox" id="tgl_reg" name="registration_open" class="sa-toggle"
                        <?= ($s['registration_open'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <label for="tgl_reg" style="font-size:.88rem;color:var(--text-primary);cursor:pointer;">
                        Permitir registro público de nuevas empresas
                    </label>
                </div>
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <div class="toggle-wrap">
                    <input type="checkbox" id="tgl_maint" name="maintenance_mode" class="sa-toggle"
                        <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label for="tgl_maint" style="font-size:.88rem;color:var(--text-primary);cursor:pointer;">
                        Activar modo mantenimiento
                    </label>
                </div>
                <p style="font-size:.78rem;color:var(--text-muted);margin-top:.35rem;">
                    Los empleados verán la pantalla de mantenimiento. El superadmin accede con normalidad.
                </p>
            </div>

            <div class="form-field" style="margin-bottom:1.5rem;">
                <label class="field-label">Mensaje de mantenimiento</label>
                <textarea name="maintenance_message" class="field-input" rows="3"
                    placeholder="Estamos realizando mejoras. Vuelve pronto."><?= htmlspecialchars($s['maintenance_message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </form>
    </div>
</div>

<!-- TAB: Correo SMTP -->
<div id="tab-email" class="sa-tab-panel <?= $activeTab === 'email' ? 'active' : '' ?>">
    <div class="sa-form-card" style="max-width:680px;">
        <h2>Configuración SMTP</h2>
        <form method="POST" id="emailForm">
            <input type="hidden" name="_action" value="save_email">

            <div class="sa-form-grid" style="margin-bottom:1rem;">
                <div class="form-field">
                    <label class="field-label">Email de notificaciones (superadmin)</label>
                    <input type="email" name="superadmin_email" class="field-input"
                        value="<?= htmlspecialchars($s['superadmin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="superadmin@example.com">
                </div>
                <div class="form-field">
                    <label class="field-label">Nombre del remitente</label>
                    <input type="text" name="smtp_from_name" class="field-input"
                        value="<?= htmlspecialchars($s['smtp_from_name'] ?? 'es21plus', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="sa-form-grid" style="margin-bottom:1rem;">
                <div class="form-field">
                    <label class="field-label">Servidor SMTP (host)</label>
                    <input type="text" name="smtp_host" class="field-input"
                        value="<?= htmlspecialchars($s['smtp_host'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="smtp.gmail.com">
                </div>
                <div class="form-field">
                    <label class="field-label">Puerto SMTP</label>
                    <input type="number" name="smtp_port" class="field-input" min="1" max="65535"
                        value="<?= (int)($s['smtp_port'] ?? 587) ?>">
                </div>
            </div>

            <div class="sa-form-grid" style="margin-bottom:1.5rem;">
                <div class="form-field">
                    <label class="field-label">Usuario SMTP</label>
                    <input type="text" name="smtp_user" class="field-input" autocomplete="off"
                        value="<?= htmlspecialchars($s['smtp_user'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-field">
                    <label class="field-label">
                        Contraseña SMTP
                        <span style="font-size:.72rem;color:var(--text-muted);font-weight:400;">(dejar vacío para no cambiar)</span>
                    </label>
                    <input type="password" name="smtp_password" class="field-input" autocomplete="new-password"
                        placeholder="••••••••">
                </div>
            </div>

            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Guardar SMTP</button>
                <button type="button" class="btn btn-secondary" id="btnTestEmail" onclick="testEmail()">
                    Enviar email de prueba
                </button>
                <span id="testEmailMsg" style="font-size:.82rem;"></span>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(name) {
    document.querySelectorAll('.sa-tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.sa-tab-btn').forEach(b => b.classList.remove('active'));
    const panel = document.getElementById('tab-' + name);
    if (panel) panel.classList.add('active');
    event.currentTarget.classList.add('active');
}

async function testEmail() {
    const btn = document.getElementById('btnTestEmail');
    const msg = document.getElementById('testEmailMsg');
    btn.disabled = true;
    btn.textContent = 'Enviando…';
    msg.textContent = '';
    msg.style.color = '';
    try {
        const res  = await fetch('./settings/test-email.php', { method: 'POST' });
        const json = await res.json();
        msg.textContent = json.message;
        msg.style.color = json.success ? '#16a34a' : '#dc2626';
    } catch {
        msg.textContent = 'Error de conexión.';
        msg.style.color = '#dc2626';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Enviar email de prueba';
    }
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/views/layout.php';
