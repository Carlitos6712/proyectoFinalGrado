<div class="sa-table-card">
    <div class="sa-table-header">
        <h3>Bandeja de entrada <span style="font-weight:400;color:var(--text-muted);font-size:.82rem;">(<?= count($messages) ?> mensajes)</span></h3>
    </div>
    <?php if (empty($messages)): ?>
        <p style="padding:1.5rem;text-align:center;color:var(--text-muted);">Bandeja vacía.</p>
    <?php else: ?>
    <table class="sa-table">
        <thead><tr><th></th><th>Remitente</th><th>Negocio</th><th>Asunto</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($messages as $m): ?>
        <tr style="cursor:pointer;<?= !$m['is_read'] ? 'font-weight:600;' : '' ?>"
            onclick="location.href='contact.php?action=show&id=<?= $m['id'] ?>'">
            <td style="width:32px;">
                <?php if (!$m['is_read']): ?>
                    <span class="badge-unread">Nuevo</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($m['sender_name'], ENT_QUOTES, 'UTF-8') ?>
                <div style="font-size:.75rem;font-weight:400;color:var(--text-muted)"><?= htmlspecialchars($m['sender_email'], ENT_QUOTES, 'UTF-8') ?></div>
            </td>
            <td style="color:var(--text-muted)"><?= htmlspecialchars($m['negocio'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($m['subject'] ?? '(sin asunto)', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="color:var(--text-muted);white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
