<div style="max-width:720px;">
    <div class="sa-form-card">
        <h2>Nuevo negocio</h2>
        <form method="POST" action="businesses.php?action=store">
            <input type="hidden" name="_action" value="store">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="sa-form-grid">
                <div class="form-field">
                    <label class="field-label">Nombre *</label>
                    <input type="text" name="name" class="field-input" required
                           placeholder="Taller Mecánico García"
                           oninput="generarSlug(this.value)">
                </div>
                <div class="form-field">
                    <label class="field-label">Slug (URL)</label>
                    <input type="text" name="slug" id="slugField" class="field-input" readonly
                           style="background:var(--surface);color:var(--text-muted);">
                </div>
                <div class="form-field">
                    <label class="field-label">Email de contacto</label>
                    <input type="email" name="contact_email" class="field-input" placeholder="admin@taller.com">
                </div>
                <div class="form-field">
                    <label class="field-label">Teléfono</label>
                    <input type="text" name="phone" class="field-input" placeholder="+34 600 000 000">
                </div>
            </div>

            <div class="form-field" style="margin-top:.9rem;">
                <label class="field-label">Dirección</label>
                <textarea name="address" class="field-input" rows="2" placeholder="Calle, ciudad, CP"></textarea>
            </div>

            <div class="sa-form-grid" style="margin-top:.9rem;">
                <div class="form-field">
                    <label class="field-label">Plan</label>
                    <select name="plan" class="field-input">
                        <option value="free">Free</option>
                        <option value="basic">Basic</option>
                        <option value="pro">Pro</option>
                    </select>
                </div>
                <div class="form-field">
                    <label class="field-label">Vencimiento del plan</label>
                    <input type="date" name="plan_expires_at" class="field-input">
                </div>
            </div>

            <p style="font-size:.78rem;color:var(--text-muted);margin:.9rem 0 1.25rem;">
                Se creará automáticamente un empleado con rol admin usando el email de contacto.
            </p>

            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <a href="businesses.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Crear negocio</button>
            </div>
        </form>
    </div>
</div>

<script>
function generarSlug(nombre) {
    const slug = nombre.toLowerCase().trim()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
    document.getElementById('slugField').value = slug;
}
</script>
