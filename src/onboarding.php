<?php
/**
 * Flujo de onboarding para nuevos administradores de empresa.
 *
 * Paso 1: Datos de la empresa + logo
 * Paso 2: Añadir miembros del equipo
 *
 * @package  Es21Plus
 * @author   Carlos Vico
 * @version  1.0.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['business_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}
if ((int)($_SESSION['business_onboarding_completed'] ?? 0) === 1) {
    header('Location: index.php');
    exit;
}

$businessName = htmlspecialchars($_SESSION['business_name'] ?? '', ENT_QUOTES, 'UTF-8');
$businessLogo = htmlspecialchars($_SESSION['business_logo'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido – Configura tu empresa · es21plus</title>
    <link rel="stylesheet" href="css/estilos.css">
    <?php require_once __DIR__ . '/includes/theme_inject.php'; ?>
    <style>
        body.onboarding-page {
            min-height: 100vh;
            background: var(--body-bg);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem 1rem 4rem;
        }
        .ob-wrapper { width: 100%; max-width: 700px; }
        .ob-header { text-align: center; margin-bottom: 2rem; padding-top: 1rem; }
        .ob-header h1 { font-size: 1.6rem; color: var(--text-primary); margin-bottom: .5rem; }
        .ob-header p  { color: var(--text-secondary); font-size: .95rem; }

        .ob-progress { display: flex; align-items: center; margin-bottom: 2rem; }
        .ob-step-wrap { display: flex; flex-direction: column; align-items: center; }
        .ob-step-dot {
            width: 36px; height: 36px; border-radius: 50%; border: 2px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem; font-weight: 600; color: var(--text-muted);
            background: var(--card-bg); flex-shrink: 0; transition: all .2s;
        }
        .ob-step-dot.active { border-color: var(--primary); background: var(--primary); color: #fff; }
        .ob-step-dot.done   { border-color: var(--success); background: var(--success); color: #fff; }
        .ob-step-label { font-size: .72rem; color: var(--text-muted); margin-top: .3rem; white-space: nowrap; }
        .ob-step-line { flex: 1; height: 2px; background: var(--border); margin: 0 .5rem; margin-bottom: 1.1rem; transition: background .2s; }
        .ob-step-line.done { background: var(--success); }

        .ob-card { background: var(--card-bg); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-md); }
        .ob-card h2 { font-size: 1.25rem; color: var(--text-primary); margin-bottom: .4rem; }
        .ob-card p.desc { color: var(--text-secondary); font-size: .9rem; margin-bottom: 1.5rem; }
        .ob-step { display: none; }
        .ob-step.active { display: block; }
        .ob-footer { display: flex; align-items: center; justify-content: flex-end; gap: .75rem; margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border); }
        .skip-link { color: var(--text-muted); font-size: .85rem; text-decoration: none; margin-right: auto; }
        .skip-link:hover { color: var(--primary); }
        .ob-error { background: #fee2e2; border: 1px solid var(--danger); color: #991b1b; border-radius: var(--radius-sm); padding: 10px 14px; font-size: .9rem; margin-bottom: 1rem; display: none; }

        .logo-preview { display: flex; align-items: center; gap: 1rem; margin-top: .75rem; }
        .logo-preview img { width: 60px; height: 60px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border); }

        .emp-card {
            background: var(--body-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 1rem;
            margin-bottom: .75rem;
            position: relative;
        }
        .emp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .emp-grid-full { grid-column: 1 / -1; }
        .emp-remove {
            position: absolute; top: .5rem; right: .5rem;
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); font-size: 1rem; line-height: 1;
            padding: 2px 6px; border-radius: 4px;
        }
        .emp-remove:hover { color: var(--danger); background: #fee2e2; }
        .add-row-btn { background: none; border: 1px dashed var(--border); border-radius: var(--radius-sm); padding: 10px; width: 100%; color: var(--text-muted); cursor: pointer; font-size: .9rem; margin-top: .25rem; transition: all .15s; }
        .add-row-btn:hover { border-color: var(--primary); color: var(--primary); }

        @media (max-width: 520px) {
            .emp-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="onboarding-page">
<div class="ob-wrapper">
    <div class="ob-header">
        <h1>¡Bienvenido a es21plus!</h1>
        <p>Configura tu empresa en 2 pasos para empezar.</p>
    </div>

    <div class="ob-progress">
        <div class="ob-step-wrap">
            <div class="ob-step-dot active" id="dot1">1</div>
            <div class="ob-step-label">Tu empresa</div>
        </div>
        <div class="ob-step-line" id="line1"></div>
        <div class="ob-step-wrap">
            <div class="ob-step-dot" id="dot2">2</div>
            <div class="ob-step-label">Tu equipo</div>
        </div>
    </div>

    <div class="ob-card">

        <!-- ────── PASO 1: Empresa ────── -->
        <div class="ob-step active" id="step1">
            <h2>Paso 1 — Datos de tu empresa</h2>
            <p class="desc">Esta información aparecerá en tu panel. Podrás modificarla después desde Perfil de empresa.</p>

            <div id="err1" class="ob-error"></div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label">Logo de la empresa</label>
                <input type="file" id="logoFile" accept="image/jpeg,image/png,image/webp"
                       style="padding:6px;" class="field-input" onchange="previewLogo(this)">
                <div class="logo-preview" id="logoPreviewWrap" style="<?= $businessLogo ? '' : 'display:none;' ?>">
                    <img id="logoPreviewImg" src="<?= $businessLogo ?>" alt="Logo">
                    <span id="logoPreviewName" style="font-size:.85rem;color:var(--text-muted);"></span>
                </div>
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label">Nombre comercial *</label>
                <input type="text" id="s1Name" class="field-input" value="<?= $businessName ?>" placeholder="Taller Mecánico García">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div class="form-field">
                    <label class="field-label">Teléfono de contacto</label>
                    <input type="tel" id="s1Phone" class="field-input" placeholder="+34 600 000 000">
                </div>
                <div class="form-field">
                    <label class="field-label">Email de contacto</label>
                    <input type="email" id="s1ContactEmail" class="field-input" placeholder="taller@ejemplo.com">
                </div>
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label">Dirección del taller</label>
                <input type="text" id="s1Address" class="field-input" placeholder="Calle Mayor, 12, 28001 Madrid">
            </div>

            <div class="form-field" style="margin-bottom:1rem;">
                <label class="field-label">Descripción breve <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>
                <textarea id="s1Desc" class="field-input" rows="2"
                          placeholder="Taller especializado en motos de trail y enduro desde 2010…"
                          style="resize:vertical;"></textarea>
            </div>

            <div class="ob-footer">
                <button class="btn btn-primary" onclick="submitStep1()">Continuar &rarr;</button>
            </div>
        </div>

        <!-- ────── PASO 2: Equipo ────── -->
        <div class="ob-step" id="step2">
            <h2>Paso 2 — Tu equipo</h2>
            <p class="desc">Añade hasta 5 miembros. Recibirán un email con sus credenciales de acceso. Puedes saltarte este paso y añadirlos después.</p>

            <div id="err2" class="ob-error"></div>

            <div id="employeesContainer"></div>

            <button class="add-row-btn" id="addEmpBtn" onclick="addEmpCard()">+ Añadir miembro del equipo</button>

            <div class="ob-footer">
                <a href="#" class="skip-link" onclick="skipTeam(event)">Omitir, añadir equipo después</a>
                <button class="btn btn-secondary" onclick="goToStep(1)">Atrás</button>
                <button class="btn btn-primary" id="finishBtn" onclick="submitStep2()">Finalizar &rarr;</button>
            </div>
        </div>

    </div>
</div>

<!-- Sugerencias de puesto para el datalist -->
<datalist id="positionsList">
    <option value="Mecánico">
    <option value="Jefe de taller">
    <option value="Recepcionista">
    <option value="Encargado de almacén">
    <option value="Comercial">
    <option value="Auxiliar">
    <option value="Aprendiz">
    <option value="Electromecánico">
</datalist>

<script>
let currentStep = 1;
const MAX_EMPLOYEES = 5;

/**
 * Navega al paso indicado y actualiza la barra de progreso.
 * @param {number} step
 */
function goToStep(step) {
    document.querySelectorAll('.ob-step').forEach(el => el.classList.remove('active'));
    document.getElementById('step' + step).classList.add('active');

    for (let i = 1; i <= 2; i++) {
        const dot = document.getElementById('dot' + i);
        dot.className = 'ob-step-dot' + (i < step ? ' done' : i === step ? ' active' : '');
        if (i < 2) {
            document.getElementById('line' + i).className = 'ob-step-line' + (i < step ? ' done' : '');
        }
    }
    currentStep = step;

    if (step === 2 && document.getElementById('employeesContainer').children.length === 0) {
        addEmpCard();
    }
}

function showErr(n, msg) {
    const el = document.getElementById('err' + n);
    el.textContent = msg;
    el.style.display = 'block';
}
function clearErr(n) { document.getElementById('err' + n).style.display = 'none'; }

// ─── Paso 1 ───────────────────────────────────────────────────

/**
 * Preview del logo antes de subir.
 * @param {HTMLInputElement} input
 */
function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('logoPreviewImg').src = e.target.result;
        document.getElementById('logoPreviewName').textContent = input.files[0].name;
        document.getElementById('logoPreviewWrap').style.display = 'flex';
    };
    reader.readAsDataURL(input.files[0]);
}

async function submitStep1() {
    clearErr(1);
    const name = document.getElementById('s1Name').value.trim();
    if (!name) { showErr(1, 'El nombre de la empresa es obligatorio.'); return; }

    const fd = new FormData();
    fd.append('name',         name);
    fd.append('phone',        document.getElementById('s1Phone').value.trim());
    fd.append('contact_email', document.getElementById('s1ContactEmail').value.trim());
    fd.append('address',      document.getElementById('s1Address').value.trim());
    fd.append('description',  document.getElementById('s1Desc').value.trim());
    const logoFile = document.getElementById('logoFile').files[0];
    if (logoFile) fd.append('logo', logoFile);

    try {
        const res  = await fetch('api/onboarding/step1.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) { goToStep(2); } else { showErr(1, json.message); }
    } catch { showErr(1, 'Error de conexión.'); }
}

// ─── Paso 2: Equipo ───────────────────────────────────────────

/**
 * Añade una tarjeta de empleado al contenedor.
 */
function addEmpCard() {
    const container = document.getElementById('employeesContainer');
    if (container.children.length >= MAX_EMPLOYEES) return;

    const card = document.createElement('div');
    card.className = 'emp-card';
    card.innerHTML = `
        <button type="button" class="emp-remove" title="Eliminar" onclick="removeEmpCard(this)">&#x2715;</button>
        <div class="emp-grid">
            <div class="form-field emp-grid-full">
                <label class="field-label">Nombre completo *</label>
                <input type="text" class="field-input emp-name" placeholder="Juan García Martínez">
            </div>
            <div class="form-field">
                <label class="field-label">Email *</label>
                <input type="email" class="field-input emp-email" placeholder="juan@taller.com">
            </div>
            <div class="form-field">
                <label class="field-label">Teléfono</label>
                <input type="tel" class="field-input emp-phone" placeholder="+34 600 000 000">
            </div>
            <div class="form-field">
                <label class="field-label">Puesto / Cargo</label>
                <input type="text" class="field-input emp-position" list="positionsList"
                       placeholder="Mecánico, Recepcionista…">
            </div>
            <div class="form-field">
                <label class="field-label">Rol en el sistema</label>
                <select class="field-input emp-role">
                    <option value="employee">Empleado — acceso estándar</option>
                    <option value="admin">Administrador — acceso completo</option>
                </select>
            </div>
        </div>`;
    container.appendChild(card);

    if (container.children.length >= MAX_EMPLOYEES) {
        document.getElementById('addEmpBtn').style.display = 'none';
    }
}

/**
 * Elimina una tarjeta de empleado.
 * @param {HTMLButtonElement} btn
 */
function removeEmpCard(btn) {
    btn.closest('.emp-card').remove();
    document.getElementById('addEmpBtn').style.display = 'block';
}

async function skipTeam(e) {
    e.preventDefault();
    await finishOnboarding([]);
}

async function submitStep2() {
    clearErr(2);
    const cards     = document.querySelectorAll('#employeesContainer .emp-card');
    const employees = [];
    let hasError    = false;

    cards.forEach(card => {
        const name  = card.querySelector('.emp-name').value.trim();
        const email = card.querySelector('.emp-email').value.trim();

        if (!name || !email) { hasError = true; return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { hasError = true; return; }

        employees.push({
            name,
            email,
            phone:    card.querySelector('.emp-phone').value.trim(),
            position: card.querySelector('.emp-position').value.trim(),
            role:     card.querySelector('.emp-role').value,
        });
    });

    if (hasError) {
        showErr(2, 'Revisa que nombre y email estén completos y sean válidos en todas las filas.');
        return;
    }

    document.getElementById('finishBtn').disabled    = true;
    document.getElementById('finishBtn').textContent = 'Guardando…';
    await finishOnboarding(employees);
    document.getElementById('finishBtn').disabled    = false;
    document.getElementById('finishBtn').textContent = 'Finalizar &rarr;';
}

/**
 * Envía la lista de empleados al servidor y redirige al dashboard.
 * @param {Array} employees
 */
async function finishOnboarding(employees) {
    try {
        const res  = await fetch('api/onboarding/step3.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ employees })
        });
        const json = await res.json();
        if (json.success) {
            window.location.href = json.redirect || 'index.php?welcome=1';
        } else {
            showErr(2, json.message || 'Error al guardar el equipo.');
        }
    } catch {
        showErr(2, 'Error de conexión.');
    }
}
</script>
</body>
</html>
