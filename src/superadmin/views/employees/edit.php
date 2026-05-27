<div style="max-width:580px;">
    <div class="sa-form-card">
        <h2>Editar empleado</h2>
        <form method="POST" action="employees.php?action=update&id=<?= $employee['id'] ?>">
            <input type="hidden" name="_action" value="update">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-field" style="margin-bottom:.9rem;">
                <label class="field-label">Negocio *</label>
                <select name="business_id" class="field-input" required>
                    <?php foreach ($negocios as $n): ?>
                        <option value="<?= $n['id'] ?>" <?= (int)$employee['business_id'] === (int)$n['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($n['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field" style="margin-bottom:.9rem;">
                <label class="field-label">Nombre *</label>
                <input type="text" name="name" class="field-input" required
                       value="<?= htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field" style="margin-bottom:.9rem;">
                <label class="field-label">Email *</label>
                <input type="email" name="email" class="field-input" required
                       value="<?= htmlspecialchars($employee['email'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field" style="margin-bottom:1.25rem;">
                <label class="field-label">Rol</label>
                <select name="role" class="field-input">
                    <option value="employee" <?= $employee['role'] === 'employee' ? 'selected' : '' ?>>Empleado</option>
                    <option value="admin"    <?= $employee['role'] === 'admin'    ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <a href="employees.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
