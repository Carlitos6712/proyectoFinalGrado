<?php
/**
 * Dashboard global del panel de superadmin.
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/controllers/DashboardController.php';

SuperadminMiddleware::require();

$ctrl    = new DashboardController();
$data    = $ctrl->index();
$base    = './';
$cssBase = '../';

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$mensajesSinLeer = $data['mensajesSinLeer'];

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$movLabels = json_encode(array_column($data['movimientosPorNegocio'], 'negocio'));
$movValues = json_encode(array_column($data['movimientosPorNegocio'], 'total'));

ob_start();
?>
<!-- Métricas -->
<div class="sa-metric-grid">
    <div class="sa-metric-card">
        <div class="sa-metric-label">Negocios activos</div>
        <div class="sa-metric-value"><?= $data['totalNegocios'] ?></div>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Empleados activos</div>
        <div class="sa-metric-value"><?= $data['totalEmpleados'] ?></div>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Mensajes sin leer</div>
        <div class="sa-metric-value" style="<?= $data['mensajesSinLeer'] > 0 ? 'color:#ef4444' : '' ?>"><?= $data['mensajesSinLeer'] ?></div>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Movimientos hoy</div>
        <div class="sa-metric-value"><?= $data['movimientosHoy'] ?></div>
    </div>
</div>

<!-- Gráfico movimientos por negocio (7 días) -->
<div class="sa-table-card" style="padding:1.25rem;">
    <div class="sa-table-header" style="padding:0 0 1rem;">
        <h3>Movimientos por negocio — últimos 7 días</h3>
    </div>
    <canvas id="chartMovimientos" height="80"></canvas>
</div>

<!-- Últimos mensajes sin leer -->
<div class="sa-table-card">
    <div class="sa-table-header">
        <h3>Mensajes sin leer</h3>
        <a href="contact.php" style="font-size:.82rem;color:var(--accent);">Ver todos →</a>
    </div>
    <?php if (empty($data['ultimosMensajes'])): ?>
        <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin mensajes pendientes.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead><tr><th>Remitente</th><th>Negocio</th><th>Asunto</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($data['ultimosMensajes'] as $m): ?>
            <tr style="cursor:pointer;" onclick="location.href='contact.php?action=show&id=<?= $m['id'] ?>'">
                <td><?= htmlspecialchars($m['sender_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($m['negocio'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($m['subject'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td style="color:var(--text-muted)"><?= date('d/m H:i', strtotime($m['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Última actividad -->
<div class="sa-table-card">
    <div class="sa-table-header">
        <h3>Últimas actividades</h3>
        <a href="logs.php" style="font-size:.82rem;color:var(--accent);">Ver todas →</a>
    </div>
    <?php if (empty($data['ultimaActividad'])): ?>
        <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin actividad registrada.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead><tr><th>Negocio</th><th>Empleado</th><th>Acción</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($data['ultimaActividad'] as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['negocio'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($a['empleado'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($a['action'], ENT_QUOTES, 'UTF-8') ?></td>
                <td style="color:var(--text-muted)"><?= date('d/m H:i', strtotime($a['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const ctx = document.getElementById('chartMovimientos');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= $movLabels ?>,
            datasets: [{
                label: 'Movimientos',
                data: <?= $movValues ?>,
                backgroundColor: 'rgba(99,102,241,0.7)',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/views/layout.php';
