<!-- Toolbar -->
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <form method="GET" action="businesses.php" style="display:flex;gap:.5rem;flex:1;max-width:340px;">
        <input type="hidden" name="action" value="list">
        <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="Buscar negocio..."
               style="flex:1;padding:.5rem .75rem;border:1px solid var(--border);border-radius:.5rem;font-size:.85rem;">
        <button type="submit" class="btn btn-secondary" style="padding:.5rem .9rem;">Buscar</button>
    </form>
    <a href="businesses.php?action=create" class="btn btn-primary" style="margin-left:auto;">+ Nuevo negocio</a>
</div>

<div class="sa-table-card">
    <table class="sa-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Plan</th>
                <th>Vence</th>
                <th>Empleados</th>
                <th>Estado</th>
                <th>Creado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($result['items'])): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem;">Sin resultados.</td></tr>
        <?php else: ?>
            <?php foreach ($result['items'] as $b): ?>
            <tr>
                <td>
                    <a href="businesses.php?action=show&id=<?= $b['id'] ?>" style="font-weight:600;">
                        <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($b['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td><span class="badge-<?= $b['plan'] ?>"><?= strtoupper($b['plan']) ?></span></td>
                <td style="color:var(--text-muted);">
                    <?= $b['plan_expires_at'] ? date('d/m/Y', strtotime($b['plan_expires_at'])) : '—' ?>
                </td>
                <td><?= $b['num_empleados'] ?></td>
                <td>
                    <?php if ($b['is_active']): ?>
                        <span class="badge-active">Activo</span>
                    <?php else: ?>
                        <span class="badge-inactive">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--text-muted);"><?= date('d/m/Y', strtotime($b['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:.4rem;align-items:center;">
                        <a href="businesses.php?action=show&id=<?= $b['id'] ?>" title="Ver" style="color:var(--accent);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                        <a href="businesses.php?action=edit&id=<?= $b['id'] ?>" title="Editar" style="color:var(--text-secondary);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <form method="POST" action="businesses.php?action=toggle&id=<?= $b['id'] ?>" style="display:inline;" onsubmit="return confirm('¿Cambiar estado del negocio?')">
                            <input type="hidden" name="_action" value="toggle">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" title="<?= $b['is_active'] ? 'Desactivar' : 'Activar' ?>"
                                    style="background:none;border:none;cursor:pointer;color:<?= $b['is_active'] ? '#ef4444' : '#22c55e' ?>;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <?php if ($b['is_active']): ?>
                                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                                    <?php else: ?>
                                        <circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/>
                                    <?php endif; ?>
                                </svg>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginación -->
<?php if ($result['pages'] > 1): ?>
<div style="display:flex;gap:.4rem;justify-content:center;margin-top:1rem;">
    <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <a href="businesses.php?page=<?= $i ?>&q=<?= urlencode($q) ?>"
           style="padding:.35rem .7rem;border:1px solid var(--border);border-radius:.4rem;font-size:.82rem;
                  <?= $i === $result['page'] ? 'background:var(--accent);color:#fff;border-color:var(--accent);' : 'color:var(--text-secondary);' ?>
                  text-decoration:none;">
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>
