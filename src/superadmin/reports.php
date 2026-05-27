<?php
/**
 * Centro de reportes y exportaciones — panel superadmin.
 *
 * Permite generar CSV y PDF de: negocios, facturación,
 * actividad y tickets.
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/middleware/SuperadminMiddleware.php';

SuperadminMiddleware::require();

$base    = './';
$cssBase = '../';
$pageTitle  = 'Reportes';
$activeMenu = 'reports';

try {
    $mensajesSinLeer = (int)\Database::getInstance()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
} catch (\Throwable) {
    $mensajesSinLeer = 0;
}

// Lista de negocios para filtros
try {
    $businesses = \Database::getInstance()->query(
        "SELECT id, name FROM businesses WHERE is_active = 1 ORDER BY name"
    )->fetchAll();
} catch (\Throwable) {
    $businesses = [];
}

ob_start();
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem;">

    <!-- Reporte: Negocios -->
    <div class="sa-form-card">
        <h2 style="margin-bottom:.25rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" style="vertical-align:-.2em;margin-right:.4em;"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Negocios
        </h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:1rem;">Listado completo con estado, plan y empleados activos.</p>
        <form action="reports/export.php" method="GET" target="_blank">
            <input type="hidden" name="type" value="businesses">
            <div class="form-field" style="margin-bottom:.75rem;">
                <label class="field-label" style="font-size:.78rem;">Estado</label>
                <select name="status" class="field-input" style="font-size:.82rem;">
                    <option value="">Todos</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                </select>
            </div>
            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label" style="font-size:.78rem;">Plan</label>
                <select name="plan" class="field-input" style="font-size:.82rem;">
                    <option value="">Todos</option>
                    <option value="free">Free</option>
                    <option value="basic">Basic</option>
                    <option value="pro">Pro</option>
                </select>
            </div>
            <div style="display:flex;gap:.5rem;">
                <button type="submit" name="format" value="csv" class="btn btn-secondary" style="font-size:.8rem;flex:1;">CSV</button>
                <button type="submit" name="format" value="pdf" class="btn btn-secondary" style="font-size:.8rem;flex:1;">PDF</button>
            </div>
        </form>
    </div>

    <!-- Reporte: Facturación -->
    <div class="sa-form-card">
        <h2 style="margin-bottom:.25rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" style="vertical-align:-.2em;margin-right:.4em;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Facturación
        </h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:1rem;">Facturas con importes, estados y periodos.</p>
        <form action="reports/export.php" method="GET" target="_blank">
            <input type="hidden" name="type" value="billing">
            <div class="form-field" style="margin-bottom:.75rem;">
                <label class="field-label" style="font-size:.78rem;">Estado</label>
                <select name="status" class="field-input" style="font-size:.82rem;">
                    <option value="">Todos</option>
                    <option value="pending">Pendiente</option>
                    <option value="paid">Pagada</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </div>
            <div class="sa-form-grid" style="margin-bottom:1rem;">
                <div class="form-field">
                    <label class="field-label" style="font-size:.78rem;">Desde</label>
                    <input type="date" name="fecha_desde" class="field-input" style="font-size:.82rem;">
                </div>
                <div class="form-field">
                    <label class="field-label" style="font-size:.78rem;">Hasta</label>
                    <input type="date" name="fecha_hasta" class="field-input" style="font-size:.82rem;">
                </div>
            </div>
            <div style="display:flex;gap:.5rem;">
                <button type="submit" name="format" value="csv" class="btn btn-secondary" style="font-size:.8rem;flex:1;">CSV</button>
                <button type="submit" name="format" value="pdf" class="btn btn-secondary" style="font-size:.8rem;flex:1;">PDF</button>
            </div>
        </form>
    </div>

    <!-- Reporte: Actividad -->
    <div class="sa-form-card">
        <h2 style="margin-bottom:.25rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" style="vertical-align:-.2em;margin-right:.4em;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Actividad
        </h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:1rem;">Registro de acciones de empleados y superadmin.</p>
        <form action="reports/export.php" method="GET" target="_blank">
            <input type="hidden" name="type" value="activity">
            <div class="form-field" style="margin-bottom:.75rem;">
                <label class="field-label" style="font-size:.78rem;">Empresa</label>
                <select name="business_id" class="field-input" style="font-size:.82rem;">
                    <option value="">Todas</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sa-form-grid" style="margin-bottom:1rem;">
                <div class="form-field">
                    <label class="field-label" style="font-size:.78rem;">Desde</label>
                    <input type="date" name="fecha_desde" class="field-input" style="font-size:.82rem;">
                </div>
                <div class="form-field">
                    <label class="field-label" style="font-size:.78rem;">Hasta</label>
                    <input type="date" name="fecha_hasta" class="field-input" style="font-size:.82rem;">
                </div>
            </div>
            <div style="display:flex;gap:.5rem;">
                <button type="submit" name="format" value="csv" class="btn btn-secondary" style="font-size:.8rem;flex:1;">CSV</button>
            </div>
        </form>
    </div>

    <!-- Reporte: Tickets -->
    <div class="sa-form-card">
        <h2 style="margin-bottom:.25rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" style="vertical-align:-.2em;margin-right:.4em;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            Tickets de soporte
        </h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:1rem;">Histórico de tickets con estado, prioridad y mensajes.</p>
        <form action="reports/export.php" method="GET" target="_blank">
            <input type="hidden" name="type" value="tickets">
            <div class="form-field" style="margin-bottom:.75rem;">
                <label class="field-label" style="font-size:.78rem;">Estado</label>
                <select name="status" class="field-input" style="font-size:.82rem;">
                    <option value="">Todos</option>
                    <option value="open">Abierto</option>
                    <option value="in_progress">En proceso</option>
                    <option value="resolved">Resuelto</option>
                    <option value="closed">Cerrado</option>
                </select>
            </div>
            <div class="sa-form-grid" style="margin-bottom:1rem;">
                <div class="form-field">
                    <label class="field-label" style="font-size:.78rem;">Desde</label>
                    <input type="date" name="fecha_desde" class="field-input" style="font-size:.82rem;">
                </div>
                <div class="form-field">
                    <label class="field-label" style="font-size:.78rem;">Hasta</label>
                    <input type="date" name="fecha_hasta" class="field-input" style="font-size:.82rem;">
                </div>
            </div>
            <div style="display:flex;gap:.5rem;">
                <button type="submit" name="format" value="csv" class="btn btn-secondary" style="font-size:.8rem;flex:1;">CSV</button>
                <button type="submit" name="format" value="pdf" class="btn btn-secondary" style="font-size:.8rem;flex:1;">PDF</button>
            </div>
        </form>
    </div>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/views/layout.php';
