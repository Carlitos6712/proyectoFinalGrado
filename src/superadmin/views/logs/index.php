<!-- Filtros -->
<form method="GET" action="logs.php" style="display:flex;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem;align-items:flex-end;">
    <div>
        <label style="display:block;font-size:.75rem;color:var(--text-muted);margin-bottom:.25rem;">Negocio</label>
        <select name="business_id" class="field-input" style="padding:.45rem .75rem;font-size:.84rem;">
            <option value="0">Todos</option>
            <?php foreach ($negocios as $n): ?>
                <option value="<?= $n['id'] ?>" <?= (int)$filters['business_id'] === (int)$n['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($n['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="display:block;font-size:.75rem;color:var(--text-muted);margin-bottom:.25rem;">Empleado</label>
        <input type="text" name="empleado" class="field-input" style="padding:.45rem .75rem;font-size:.84rem;"
               value="<?= htmlspecialchars($filters['empleado'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre...">
    </div>
    <div>
        <label style="display:block;font-size:.75rem;color:var(--text-muted);margin-bottom:.25rem;">Desde</label>
        <input type="date" name="fecha_desde" class="field-input" style="padding:.45rem .75rem;font-size:.84rem;"
               value="<?= htmlspecialchars($filters['fecha_desde'], ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div>
        <label style="display:block;font-size:.75rem;color:var(--text-muted);margin-bottom:.25rem;">Hasta</label>
        <input type="date" name="fecha_hasta" class="field-input" style="padding:.45rem .75rem;font-size:.84rem;"
               value="<?= htmlspecialchars($filters['fecha_hasta'], ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <button type="submit" class="btn btn-secondary">Filtrar</button>
    <a href="logs.php" class="btn btn-secondary">Limpiar</a>
</form>

<div class="sa-table-card">
    <div class="sa-table-header">
        <h3>Actividad <span style="font-weight:400;color:var(--text-muted);font-size:.82rem;">(<?= $result['total'] ?> registros)</span></h3>
    </div>
    <?php if (empty($result['items'])): ?>
        <p style="padding:1.5rem;text-align:center;color:var(--text-muted);">Sin registros.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead><tr><th>Fecha</th><th>Negocio</th><th>Empleado</th><th>Acción</th><th>Entidad</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($result['items'] as $l): ?>
        <tr>
            <td style="white-space:nowrap;color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></td>
            <td><?= htmlspecialchars($l['negocio'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($l['empleado'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($l['action'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="color:var(--text-muted)"><?= $l['entity'] ? htmlspecialchars($l['entity'], ENT_QUOTES, 'UTF-8') . ($l['entity_id'] ? " #{$l['entity_id']}" : '') : '—' ?></td>
            <td style="font-size:.78rem;color:var(--text-muted)"><?= htmlspecialchars($l['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Paginación -->
<?php if ($result['pages'] > 1): ?>
<div style="display:flex;gap:.4rem;justify-content:center;margin-top:1rem;flex-wrap:wrap;">
    <?php
    $qStr = http_build_query(array_filter($filters));
    for ($i = 1; $i <= $result['pages']; $i++):
    ?>
        <a href="logs.php?<?= $qStr ?>&page=<?= $i ?>"
           style="padding:.35rem .7rem;border:1px solid var(--border);border-radius:.4rem;font-size:.82rem;
                  <?= $i === $result['page'] ? 'background:var(--accent);color:#fff;border-color:var(--accent);' : 'color:var(--text-secondary);' ?>
                  text-decoration:none;">
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>
