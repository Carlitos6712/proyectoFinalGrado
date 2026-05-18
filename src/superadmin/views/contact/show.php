<div style="max-width:760px;">
    <a href="contact.php" style="font-size:.82rem;color:var(--accent);display:inline-flex;align-items:center;gap:.3rem;margin-bottom:1rem;">
        ← Volver a bandeja
    </a>

    <div class="sa-form-card" style="margin-bottom:1rem;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem;">
            <div>
                <h2 style="margin:0 0 .25rem;"><?= htmlspecialchars($message['subject'] ?? '(sin asunto)', ENT_QUOTES, 'UTF-8') ?></h2>
                <p style="margin:0;font-size:.83rem;color:var(--text-muted);">
                    De: <strong><?= htmlspecialchars($message['sender_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    &lt;<?= htmlspecialchars($message['sender_email'], ENT_QUOTES, 'UTF-8') ?>&gt;
                    <?= $message['negocio'] ? ' · ' . htmlspecialchars($message['negocio'], ENT_QUOTES, 'UTF-8') : '' ?>
                </p>
                <p style="margin:.25rem 0 0;font-size:.78rem;color:var(--text-muted);">
                    <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                    <?= $message['replied_at'] ? ' · Respondido: ' . date('d/m/Y H:i', strtotime($message['replied_at'])) : '' ?>
                </p>
            </div>
            <span class="badge-active" style="flex-shrink:0;">Leído</span>
        </div>

        <div style="background:var(--surface);border-radius:.5rem;padding:1rem;white-space:pre-wrap;font-size:.9rem;line-height:1.6;color:var(--text-primary);">
            <?= htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <!-- Responder -->
    <div class="sa-form-card">
        <h3 style="margin:0 0 1rem;font-size:.9rem;">Responder a <?= htmlspecialchars($message['sender_email'], ENT_QUOTES, 'UTF-8') ?></h3>
        <form method="POST" action="contact.php?action=reply&id=<?= $message['id'] ?>">
            <input type="hidden" name="_action" value="reply">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-field" style="margin-bottom:1rem;">
                <textarea name="reply_body" class="field-input" rows="5" required
                          placeholder="Escribe tu respuesta..."></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">Enviar respuesta</button>
            </div>
        </form>
    </div>
</div>
