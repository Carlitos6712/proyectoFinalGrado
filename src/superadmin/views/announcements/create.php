<div style="max-width:620px;">
    <div class="sa-form-card">
        <h2>Nuevo anuncio</h2>
        <form method="POST" action="announcements.php?action=store">
            <input type="hidden" name="_action" value="store">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-field" style="margin-bottom:.9rem;">
                <label class="field-label">Título *</label>
                <input type="text" name="title" class="field-input" required placeholder="Mantenimiento programado el viernes">
            </div>
            <div class="form-field" style="margin-bottom:1.25rem;">
                <label class="field-label">Mensaje *</label>
                <textarea name="body" class="field-input" rows="5" required
                          placeholder="Texto visible en el banner de todos los paneles..."></textarea>
            </div>
            <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:1.25rem;">
                El anuncio se mostrará activo inmediatamente. Solo el más reciente activo aparece como banner.
            </p>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <a href="announcements.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Publicar anuncio</button>
            </div>
        </form>
    </div>
</div>
