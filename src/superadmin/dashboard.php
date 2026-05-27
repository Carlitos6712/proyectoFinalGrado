<?php
/**
 * Dashboard global del panel de superadmin.
 *
 * Incluye KPIs con variación mensual, cuatro gráficos Chart.js,
 * tabla resumen de negocios y feed de actividad con auto-refresco.
 *
 * @package  Es21Plus\Superadmin
 * @author   Carlos Vico
 * @version  1.1.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/AppException.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/middleware/SuperadminMiddleware.php';
require_once __DIR__ . '/controllers/DashboardController.php';

SuperadminMiddleware::require();

$ctrl = new DashboardController();
$data = $ctrl->index();
$base    = './';
$cssBase = '../';

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$mensajesSinLeer = $data['mensajesSinLeer'];

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$kpis = $data['kpis'];

// Datos para gráficos — serializar a JSON para JS
$lineLabels  = json_encode(array_column($data['negociosPorMes'],    'mes'));
$lineValues  = json_encode(array_column($data['negociosPorMes'],    'total'));
$barLabels   = json_encode(array_column($data['topMovimientos'],    'negocio'));
$barValues   = json_encode(array_column($data['topMovimientos'],    'total'));
$donutLabels = json_encode(array_column($data['planesDistrib'],     'plan'));
$donutValues = json_encode(array_column($data['planesDistrib'],     'total'));
$areaLabels  = json_encode(array_column($data['mensajesPorSemana'], 'semana_label'));
$areaValues  = json_encode(array_column($data['mensajesPorSemana'], 'total'));

/** Renderiza el badge de variación porcentual */
function varBadge(array $v): string {
    if ($v['dir'] === 'same') {
        return '<span class="sa-metric-variation sa-var-same">→ sin cambio</span>';
    }
    $arrow = $v['dir'] === 'up' ? '↑' : '↓';
    $cls   = $v['dir'] === 'up' ? 'sa-var-up' : 'sa-var-down';
    return '<span class="sa-metric-variation ' . $cls . '">' . $arrow . ' ' . $v['pct'] . '%</span>';
}

ob_start();
?>
<!-- KPIs con variación -->
<div class="sa-metric-grid">
    <div class="sa-metric-card">
        <div class="sa-metric-label">Negocios activos</div>
        <div class="sa-metric-value"><?= $kpis['negocios']['value'] ?></div>
        <?= varBadge($kpis['negocios']['variation']) ?>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:.25rem;">
            <?= $kpis['negocios']['mes'] ?> nuevos este mes
        </div>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Empleados activos</div>
        <div class="sa-metric-value"><?= $kpis['empleados']['value'] ?></div>
        <?= varBadge($kpis['empleados']['variation']) ?>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:.25rem;">
            <?= $kpis['empleados']['mes'] ?> nuevos este mes
        </div>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Movimientos este mes</div>
        <div class="sa-metric-value"><?= $kpis['movimientos']['value'] ?></div>
        <?= varBadge($kpis['movimientos']['variation']) ?>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Mensajes sin leer</div>
        <div class="sa-metric-value" style="<?= $kpis['mensajes']['value'] > 0 ? 'color:#ef4444' : '' ?>">
            <?= $kpis['mensajes']['value'] ?>
        </div>
        <?= varBadge($kpis['mensajes']['variation']) ?>
    </div>
</div>

<!-- Gráficos -->
<div class="sa-charts-grid">
    <!-- Líneas: negocios registrados por mes -->
    <div class="sa-table-card" style="padding:1.25rem;">
        <div class="sa-table-header" style="padding:0 0 .75rem;">
            <h3>Negocios registrados — últimos 12 meses</h3>
        </div>
        <canvas id="chartLinea" height="120"></canvas>
    </div>

    <!-- Barras: top movimientos 30 días -->
    <div class="sa-table-card" style="padding:1.25rem;">
        <div class="sa-table-header" style="padding:0 0 .75rem;">
            <h3>Top 5 empresas — movimientos (30 días)</h3>
        </div>
        <canvas id="chartBarras" height="120"></canvas>
    </div>

    <!-- Dona: distribución de planes -->
    <div class="sa-table-card" style="padding:1.25rem;">
        <div class="sa-table-header" style="padding:0 0 .75rem;">
            <h3>Distribución de planes</h3>
        </div>
        <div style="max-width:260px;margin:0 auto;">
            <canvas id="chartDona" height="160"></canvas>
        </div>
    </div>

    <!-- Área: mensajes por semana -->
    <div class="sa-table-card" style="padding:1.25rem;">
        <div class="sa-table-header" style="padding:0 0 .75rem;">
            <h3>Mensajes recibidos — últimas 8 semanas</h3>
        </div>
        <canvas id="chartArea" height="120"></canvas>
    </div>
</div>

<!-- Tabla resumen de negocios -->
<div class="sa-table-card" style="margin-bottom:1.5rem;">
    <div class="sa-table-header">
        <h3>Resumen de negocios</h3>
        <a href="businesses.php" style="font-size:.82rem;color:var(--accent);">Ver todos →</a>
    </div>
    <?php if (empty($data['resumenNegocios'])): ?>
        <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin negocios registrados.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead>
        <tr>
            <th>Nombre</th><th>Plan</th><th>Vencimiento</th>
            <th>Mov. mes</th><th>Empleados</th><th>Estado</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data['resumenNegocios'] as $neg):
            $dias = $neg['dias_vencimiento'] !== null ? (int)$neg['dias_vencimiento'] : null;
            $rowCls = '';
            if ($dias !== null) {
                if ($dias < 0) $rowCls = 'sa-row-danger';
                elseif ($dias < 7) $rowCls = 'sa-row-warn';
            }
        ?>
            <tr class="<?= $rowCls ?>" style="cursor:pointer;" onclick="location.href='businesses.php?action=show&id=<?= $neg['id'] ?>'">
                <td><?= htmlspecialchars($neg['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge-<?= htmlspecialchars($neg['plan'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($neg['plan'], ENT_QUOTES, 'UTF-8') ?></span></td>
                <td>
                    <?php if ($neg['plan_expires_at']): ?>
                        <?= htmlspecialchars($neg['plan_expires_at'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($dias !== null): ?>
                            <span style="font-size:.72rem;color:<?= $dias < 0 ? '#dc2626' : ($dias < 7 ? '#d97706' : 'var(--text-muted)') ?>">
                                (<?= $dias < 0 ? 'vencido' : $dias . ' días' ?>)
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--text-muted)">—</span>
                    <?php endif; ?>
                </td>
                <td><?= (int)$neg['movimientos_mes'] ?></td>
                <td><?= (int)$neg['empleados_activos'] ?></td>
                <td>
                    <span class="<?= (int)$neg['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                        <?= (int)$neg['is_active'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Grid: mensajes sin leer + feed de actividad -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">

    <!-- Mensajes sin leer -->
    <div class="sa-table-card" style="margin-bottom:0;">
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

    <!-- Feed de actividad (auto-refresco 30s) -->
    <div class="sa-table-card" style="margin-bottom:0;">
        <div class="sa-table-header">
            <h3>Actividad reciente</h3>
            <a href="logs.php" style="font-size:.82rem;color:var(--accent);">Ver todas →</a>
        </div>
        <div id="feedActividad" style="max-height:320px;overflow-y:auto;">
            <?php foreach ($data['ultimaActividad'] as $a): ?>
            <div style="display:flex;gap:.75rem;padding:.65rem 1rem;border-bottom:1px solid #f1f5f9;align-items:flex-start;">
                <div style="width:8px;height:8px;border-radius:50%;margin-top:5px;flex-shrink:0;background:<?= $a['is_critical'] ? '#ef4444' : '#818cf8' ?>"></div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.82rem;font-weight:500;color:var(--text-primary);">
                        <?= htmlspecialchars($a['action'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($a['is_critical']): ?>
                            <span style="font-size:.68rem;background:#fee2e2;color:#dc2626;padding:.1rem .35rem;border-radius:4px;margin-left:.25rem;">crítico</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:.74rem;color:var(--text-muted);">
                        <?= htmlspecialchars($a['negocio'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($a['empleado'])): ?> · <?= htmlspecialchars($a['empleado'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                    </div>
                </div>
                <div style="font-size:.72rem;color:var(--text-muted);white-space:nowrap;"><?= date('d/m H:i', strtotime($a['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($data['ultimaActividad'])): ?>
                <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin actividad registrada.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const C = Chart;

    // — Gráfico líneas: negocios por mes —
    const ctxLinea = document.getElementById('chartLinea');
    if (ctxLinea) {
        new C(ctxLinea, {
            type: 'line',
            data: {
                labels: <?= $lineLabels ?>,
                datasets: [{
                    label: 'Negocios', data: <?= $lineValues ?>,
                    borderColor: '#818cf8', backgroundColor: 'rgba(129,140,248,.1)',
                    tension: .35, fill: true, pointRadius: 4,
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }

    // — Gráfico barras: top movimientos —
    const ctxBarras = document.getElementById('chartBarras');
    if (ctxBarras) {
        new C(ctxBarras, {
            type: 'bar',
            data: {
                labels: <?= $barLabels ?>,
                datasets: [{
                    label: 'Movimientos', data: <?= $barValues ?>,
                    backgroundColor: ['rgba(99,102,241,.8)','rgba(139,92,246,.8)','rgba(59,130,246,.8)','rgba(16,185,129,.8)','rgba(245,158,11,.8)'],
                    borderRadius: 6,
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }

    // — Gráfico dona: planes —
    const ctxDona = document.getElementById('chartDona');
    if (ctxDona) {
        new C(ctxDona, {
            type: 'doughnut',
            data: {
                labels: <?= $donutLabels ?>,
                datasets: [{
                    data: <?= $donutValues ?>,
                    backgroundColor: ['#e2e8f0','#93c5fd','#a78bfa'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true, cutout: '65%',
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
            }
        });
    }

    // — Gráfico área: mensajes por semana —
    const ctxArea = document.getElementById('chartArea');
    if (ctxArea) {
        new C(ctxArea, {
            type: 'line',
            data: {
                labels: <?= $areaLabels ?>,
                datasets: [{
                    label: 'Mensajes', data: <?= $areaValues ?>,
                    borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,.12)',
                    tension: .35, fill: true, pointRadius: 4,
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }

    // — Feed de actividad: auto-refresco cada 30 segundos —
    async function refreshFeed() {
        try {
            const res  = await fetch('./activity/recent.php');
            const json = await res.json();
            if (!json.success || !json.data) return;

            const feed = document.getElementById('feedActividad');
            if (!feed) return;

            feed.innerHTML = json.data.map(a => `
                <div style="display:flex;gap:.75rem;padding:.65rem 1rem;border-bottom:1px solid #f1f5f9;align-items:flex-start;">
                    <div style="width:8px;height:8px;border-radius:50%;margin-top:5px;flex-shrink:0;background:${a.is_critical ? '#ef4444' : '#818cf8'}"></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.82rem;font-weight:500;color:#0f172a;">
                            ${escHtml(a.action)}
                            ${a.is_critical ? '<span style="font-size:.68rem;background:#fee2e2;color:#dc2626;padding:.1rem .35rem;border-radius:4px;margin-left:.25rem;">crítico</span>' : ''}
                        </div>
                        <div style="font-size:.74rem;color:#64748b;">${escHtml(a.negocio || '—')}${a.empleado ? ' · ' + escHtml(a.empleado) : ''}</div>
                    </div>
                    <div style="font-size:.72rem;color:#64748b;white-space:nowrap;">${escHtml(a.tiempo_relativo)}</div>
                </div>
            `).join('') || '<p style="padding:1rem;color:#64748b;font-size:.85rem;">Sin actividad registrada.</p>';
        } catch {}
    }

    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    setInterval(refreshFeed, 30000);
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/views/layout.php';
