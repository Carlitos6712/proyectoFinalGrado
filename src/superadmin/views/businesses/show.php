<!-- Header del negocio -->
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <div style="flex:1;">
        <div style="display:flex;align-items:center;gap:.75rem;">
            <h2 style="margin:0;font-size:1.3rem;"><?= htmlspecialchars($business['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <?php if ($business['is_active']): ?>
                <span class="badge-active">Activo</span>
            <?php else: ?>
                <span class="badge-inactive">Inactivo</span>
            <?php endif; ?>
            <span class="badge-<?= $business['plan'] ?>"><?= strtoupper($business['plan']) ?></span>
        </div>
        <p style="margin:.25rem 0 0;font-size:.82rem;color:var(--text-muted);">
            <?= htmlspecialchars($business['contact_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            <?= $business['phone'] ? ' · ' . htmlspecialchars($business['phone'], ENT_QUOTES, 'UTF-8') : '' ?>
        </p>
    </div>
    <div style="display:flex;gap:.5rem;">
        <a href="businesses.php?action=edit&id=<?= $business['id'] ?>" class="btn btn-secondary">Editar</a>
        <form method="POST" action="businesses.php?action=toggle&id=<?= $business['id'] ?>" style="display:inline;" onsubmit="return confirm('¿Cambiar estado?')">
            <input type="hidden" name="_action" value="toggle">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn <?= $business['is_active'] ? 'btn-danger' : 'btn-primary' ?>">
                <?= $business['is_active'] ? 'Desactivar' : 'Activar' ?>
            </button>
        </form>
    </div>
</div>

<!-- Métricas rápidas -->
<div class="sa-metric-grid" style="margin-bottom:1.5rem;">
    <div class="sa-metric-card">
        <div class="sa-metric-label">Empleados</div>
        <div class="sa-metric-value"><?= count($business['employees']) ?></div>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Productos</div>
        <div class="sa-metric-value"><?= $business['total_productos'] ?></div>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Movimientos (mes)</div>
        <div class="sa-metric-value"><?= $business['movimientos_mes'] ?></div>
    </div>
    <div class="sa-metric-card">
        <div class="sa-metric-label">Plan vence</div>
        <div class="sa-metric-value" style="font-size:1rem;">
            <?= $business['plan_expires_at'] ? date('d/m/Y', strtotime($business['plan_expires_at'])) : '—' ?>
        </div>
    </div>
</div>

<!-- Empleados del negocio -->
<div class="sa-table-card" style="margin-bottom:1.5rem;">
    <div class="sa-table-header">
        <h3>Empleados</h3>
        <a href="../employees.php?business_id=<?= $business['id'] ?>&action=create" class="btn btn-primary" style="font-size:.8rem;padding:.35rem .8rem;">+ Empleado</a>
    </div>
    <?php if (empty($business['employees'])): ?>
        <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin empleados.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($business['employees'] as $e): ?>
        <tr>
            <td><?= htmlspecialchars($e['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="color:var(--text-muted)"><?= htmlspecialchars($e['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $e['role'] === 'admin' ? '<span class="badge-basic">Admin</span>' : '<span class="badge-free">Empleado</span>' ?></td>
            <td><?= $e['is_active'] ? '<span class="badge-active">Activo</span>' : '<span class="badge-inactive">Inactivo</span>' ?></td>
            <td>
                <a href="../employees.php?action=edit&id=<?= $e['id'] ?>" style="font-size:.8rem;color:var(--accent);">Editar</a>
                <span style="color:var(--border);margin:0 .25rem;">|</span>
                <form method="POST" action="../employees.php?action=toggle&id=<?= $e['id'] ?>" style="display:inline;" onsubmit="return confirm('¿Cambiar estado?')">
                    <input type="hidden" name="_action" value="toggle">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" style="background:none;border:none;cursor:pointer;font-size:.8rem;color:<?= $e['is_active'] ? '#ef4444' : '#22c55e' ?>">
                        <?= $e['is_active'] ? 'Desactivar' : 'Activar' ?>
                    </button>
                </form>
                <span style="color:var(--border);margin:0 .25rem;">|</span>
                <form method="POST" action="../employees.php?action=reset-password&id=<?= $e['id'] ?>" style="display:inline;" onsubmit="return confirm('¿Resetear contraseña? Se enviará por correo.')">
                    <input type="hidden" name="_action" value="reset-password">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" style="background:none;border:none;cursor:pointer;font-size:.8rem;color:var(--text-muted);">Reset pwd</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Últimos 10 movimientos -->
<div class="sa-table-card">
    <div class="sa-table-header"><h3>Últimos movimientos</h3></div>
    <?php if (empty($business['movimientos'])): ?>
        <p style="padding:1rem 1.25rem;color:var(--text-muted);font-size:.85rem;">Sin movimientos.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead><tr><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($business['movimientos'] as $m): ?>
        <tr>
            <td><?= htmlspecialchars($m['producto'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $m['tipo'] === 'entrada'
                ? '<span class="badge-active">Entrada</span>'
                : '<span class="badge-inactive">Salida</span>' ?></td>
            <td><?= $m['cantidad'] ?></td>
            <td style="color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
