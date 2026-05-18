<div style="max-width:720px;">
    <div class="sa-form-card">
        <h2>Editar negocio</h2>
        <form method="POST" action="businesses.php?action=update&id=<?= $business['id'] ?>">
            <input type="hidden" name="_action" value="update">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="sa-form-grid">
                <div class="form-field">
                    <label class="field-label">Nombre *</label>
                    <input type="text" name="name" class="field-input" required
                           value="<?= htmlspecialchars($business['name'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-field">
                    <label class="field-label">Slug</label>
                    <input type="text" class="field-input" value="<?= htmlspecialchars($business['slug'], ENT_QUOTES, 'UTF-8') ?>"
                           readonly style="background:var(--surface);color:var(--text-muted);">
                </div>
                <div class="form-field">
                    <label class="field-label">Email de contacto</label>
                    <input type="email" name="contact_email" class="field-input"
                           value="<?= htmlspecialchars($business['contact_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-field">
                    <label class="field-label">Teléfono</label>
                    <input type="text" name="phone" class="field-input"
                           value="<?= htmlspecialchars($business['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="form-field" style="margin-top:.9rem;">
                <label class="field-label">Dirección</label>
                <textarea name="address" class="field-input" rows="2"><?= htmlspecialchars($business['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="sa-form-grid" style="margin-top:.9rem;">
                <div class="form-field">
                    <label class="field-label">Plan</label>
                    <select name="plan" class="field-input">
                        <?php foreach (['free','basic','pro'] as $p): ?>
                            <option value="<?= $p ?>" <?= $business['plan'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label class="field-label">Vencimiento del plan</label>
                    <input type="date" name="plan_expires_at" class="field-input"
                           value="<?= htmlspecialchars($business['plan_expires_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem;">
                <a href="businesses.php?action=show&id=<?= $business['id'] ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
