<div style="max-width:580px;">
    <div class="sa-form-card">
        <h2>Nuevo empleado</h2>
        <form method="POST" action="employees.php?action=store">
            <input type="hidden" name="_action" value="store">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-field" style="margin-bottom:.9rem;">
                <label class="field-label">Negocio *</label>
                <select name="business_id" class="field-input" required>
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($negocios as $n): ?>
                        <option value="<?= $n['id'] ?>" <?= (int)($businessId ?? 0) === (int)$n['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($n['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field" style="margin-bottom:.9rem;">
                <label class="field-label">Nombre *</label>
                <input type="text" name="name" class="field-input" required placeholder="Juan García">
            </div>
            <div class="form-field" style="margin-bottom:.9rem;">
                <label class="field-label">Email *</label>
                <input type="email" name="email" class="field-input" required placeholder="juan@taller.com">
            </div>
            <div class="form-field" style="margin-bottom:1.25rem;">
                <label class="field-label">Rol</label>
                <select name="role" class="field-input">
                    <option value="employee">Empleado</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:1.25rem;">
                Se generará una contraseña aleatoria y se enviará al correo del empleado.
            </p>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <a href="employees.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Crear empleado</button>
            </div>
        </form>
    </div>
</div>
