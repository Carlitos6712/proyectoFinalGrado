<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <form method="GET" action="employees.php" style="display:flex;gap:.5rem;">
        <select name="business_id" class="field-input" style="padding:.5rem .75rem;font-size:.85rem;" onchange="this.form.submit()">
            <option value="0">Todos los negocios</option>
            <?php foreach ($negocios as $n): ?>
                <option value="<?= $n['id'] ?>" <?= $businessId === (int)$n['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($n['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <a href="employees.php?action=create" class="btn btn-primary" style="margin-left:auto;">+ Nuevo empleado</a>
</div>

<div class="sa-table-card">
    <table class="sa-table">
        <thead>
            <tr><th>Nombre</th><th>Email</th><th>Negocio</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php if (empty($employees)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">Sin empleados.</td></tr>
        <?php else: ?>
            <?php foreach ($employees as $e): ?>
            <tr>
                <td style="font-weight:500;"><?= htmlspecialchars($e['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td style="color:var(--text-muted)"><?= htmlspecialchars($e['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($e['negocio'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $e['role'] === 'admin' ? '<span class="badge-basic">Admin</span>' : '<span class="badge-free">Empleado</span>' ?></td>
                <td><?= $e['is_active'] ? '<span class="badge-active">Activo</span>' : '<span class="badge-inactive">Inactivo</span>' ?></td>
                <td>
                    <div style="display:flex;gap:.5rem;align-items:center;">
                        <a href="employees.php?action=edit&id=<?= $e['id'] ?>" style="font-size:.8rem;color:var(--accent);">Editar</a>
                        <form method="POST" action="employees.php?action=toggle&id=<?= $e['id'] ?>" style="display:inline;" onsubmit="return confirm('¿Cambiar estado?')">
                            <input type="hidden" name="_action" value="toggle">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" style="background:none;border:none;cursor:pointer;font-size:.8rem;color:<?= $e['is_active'] ? '#ef4444' : '#22c55e' ?>">
                                <?= $e['is_active'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <form method="POST" action="employees.php?action=reset-password&id=<?= $e['id'] ?>" style="display:inline;" onsubmit="return confirm('¿Resetear contraseña? Se enviará al correo del empleado.')">
                            <input type="hidden" name="_action" value="reset-password">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" style="background:none;border:none;cursor:pointer;font-size:.8rem;color:var(--text-muted);">Reset pwd</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
