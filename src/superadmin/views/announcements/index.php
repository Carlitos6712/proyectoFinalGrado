<div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
    <a href="announcements.php?action=create" class="btn btn-primary">+ Nuevo anuncio</a>
</div>

<div class="sa-table-card">
    <?php if (empty($announcements)): ?>
        <p style="padding:1.5rem;text-align:center;color:var(--text-muted);">Sin anuncios.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead><tr><th>Título</th><th>Estado</th><th>Creado</th><th>Acción</th></tr></thead>
        <tbody>
        <?php foreach ($announcements as $a): ?>
        <tr>
            <td style="font-weight:500;"><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $a['is_active'] ? '<span class="badge-active">Activo</span>' : '<span class="badge-inactive">Inactivo</span>' ?></td>
            <td style="color:var(--text-muted)"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
            <td>
                <form method="POST" action="announcements.php?action=toggle&id=<?= $a['id'] ?>" style="display:inline;">
                    <input type="hidden" name="_action" value="toggle">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-secondary" style="font-size:.78rem;padding:.3rem .7rem;">
                        <?= $a['is_active'] ? 'Desactivar' : 'Activar' ?>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
