<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.4rem;font-weight:700;color:var(--text-primary);margin:0 0 .25rem;">Campañas de email</h1>
        <p style="color:var(--text-muted);font-size:.88rem;margin:0;">Gestiona envíos masivos a empresas registradas.</p>
    </div>
    <a href="campaigns.php?action=create" class="btn btn-primary">+ Nueva campaña</a>
</div>

<div class="sa-card">
    <div class="sa-table-wrap">
        <table class="sa-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Asunto</th>
                    <th>Segmentación</th>
                    <th>Estado</th>
                    <th>Destinatarios</th>
                    <th>Enviado / Programado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$campaigns): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">Sin campañas aún.</td></tr>
                <?php endif; ?>
                <?php foreach ($campaigns as $c): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:.82rem;">#<?= $c['id'] ?></td>
                    <td style="font-weight:500;"><?= htmlspecialchars($c['subject'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="font-size:.83rem;">
                        Plan: <strong><?= htmlspecialchars($c['target_plan'], ENT_QUOTES, 'UTF-8') ?></strong>
                        &middot; Estado: <strong><?= htmlspecialchars($c['target_status'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </td>
                    <td>
                        <?php
                        $badges = [
                            'draft'     => ['#64748b','Borrador'],
                            'scheduled' => ['#f59e0b','Programada'],
                            'sent'      => ['#22c55e','Enviada'],
                        ];
                        [$bc, $bl] = $badges[$c['status']] ?? ['#64748b', $c['status']];
                        ?>
                        <span style="background:<?= $bc ?>20;color:<?= $bc ?>;border-radius:20px;padding:2px 10px;font-size:.8rem;font-weight:600;">
                            <?= htmlspecialchars($bl, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td style="text-align:center;"><?= $c['recipients_count'] ?></td>
                    <td style="font-size:.83rem;color:var(--text-muted);">
                        <?php if ($c['sent_at']): ?>
                            <?= date('d/m/Y H:i', strtotime($c['sent_at'])) ?>
                        <?php elseif ($c['scheduled_at']): ?>
                            Progr. <?= date('d/m/Y H:i', strtotime($c['scheduled_at'])) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div style="display:flex;gap:.5rem;justify-content:center;padding:1rem;border-top:1px solid var(--border);">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="campaigns.php?page=<?= $p ?>"
           style="padding:4px 10px;border-radius:6px;font-size:.85rem;text-decoration:none;
                  <?= $p === $page ? 'background:var(--sa-accent-dark);color:#fff;' : 'color:var(--text-secondary);' ?>">
            <?= $p ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
