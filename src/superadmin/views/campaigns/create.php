<div style="max-width:900px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.4rem;font-weight:700;color:var(--text-primary);margin:0 0 .25rem;">Nueva campaña</h1>
            <p style="color:var(--text-muted);font-size:.88rem;margin:0;">Redacta y envía un correo a tus empresas registradas.</p>
        </div>
        <a href="campaigns.php" class="btn btn-secondary">&larr; Volver al listado</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 420px;gap:1.5rem;align-items:start;">

        <!-- ── Formulario ── -->
        <div class="sa-card" style="padding:1.5rem;">
            <form method="POST" action="campaigns.php" id="campaignForm">
                <div class="form-field" style="margin-bottom:1rem;">
                    <label class="field-label">Asunto *</label>
                    <input type="text" name="subject" id="campSubject" class="field-input" required
                           placeholder="Novedad importante para tu taller"
                           value="<?= htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-field" style="margin-bottom:1rem;">
                    <label class="field-label">Cuerpo del mensaje (HTML) *</label>
                    <div style="display:flex;gap:.5rem;margin-bottom:.5rem;">
                        <button type="button" class="btn btn-secondary" style="padding:4px 10px;font-size:.8rem;" onclick="fmt('bold')"><b>B</b></button>
                        <button type="button" class="btn btn-secondary" style="padding:4px 10px;font-size:.8rem;" onclick="fmt('italic')"><i>I</i></button>
                        <button type="button" class="btn btn-secondary" style="padding:4px 10px;font-size:.8rem;" onclick="insertLink()">🔗</button>
                    </div>
                    <textarea name="body_html" id="campBody" class="field-input" rows="12"
                              style="font-family:var(--font-mono);font-size:.85rem;"
                              placeholder="<p>Hola, ...</p>" required
                              oninput="updatePreview()"><?= htmlspecialchars($_POST['body_html'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Segmentación -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                    <div class="form-field">
                        <label class="field-label">Segmento por plan</label>
                        <select name="target_plan" id="segPlan" class="field-input" onchange="updateEstimate()">
                            <option value="all"   <?= ($_POST['target_plan'] ?? 'all')   === 'all'   ? 'selected' : '' ?>>Todos los planes</option>
                            <option value="free"  <?= ($_POST['target_plan'] ?? '')       === 'free'  ? 'selected' : '' ?>>Solo Free</option>
                            <option value="basic" <?= ($_POST['target_plan'] ?? '')       === 'basic' ? 'selected' : '' ?>>Solo Basic</option>
                            <option value="pro"   <?= ($_POST['target_plan'] ?? '')       === 'pro'   ? 'selected' : '' ?>>Solo Pro</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="field-label">Segmento por estado</label>
                        <select name="target_status" id="segStatus" class="field-input" onchange="updateEstimate()">
                            <option value="active"   <?= ($_POST['target_status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Solo activas</option>
                            <option value="inactive" <?= ($_POST['target_status'] ?? '')        === 'inactive' ? 'selected' : '' ?>>Solo inactivas</option>
                            <option value="all"      <?= ($_POST['target_status'] ?? '')        === 'all'      ? 'selected' : '' ?>>Todas</option>
                        </select>
                    </div>
                </div>

                <div style="background:var(--surface);border-radius:var(--radius-sm);padding:10px 14px;font-size:.88rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Destinatarios estimados: <strong id="recipientCount">—</strong>
                </div>

                <!-- Modo de envío -->
                <div class="form-field" style="margin-bottom:1rem;">
                    <label class="field-label">Modo de envío</label>
                    <div style="display:flex;gap:1.5rem;margin-top:.4rem;">
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.9rem;">
                            <input type="radio" name="send_mode" value="immediate" checked onchange="toggleSchedule(false)"
                                   style="accent-color:var(--sa-accent-dark);">
                            Enviar ahora
                        </label>
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.9rem;">
                            <input type="radio" name="send_mode" value="scheduled" onchange="toggleSchedule(true)"
                                   style="accent-color:var(--sa-accent-dark);">
                            Programar envío
                        </label>
                    </div>
                </div>

                <div id="scheduleWrap" class="form-field" style="margin-bottom:1.25rem;display:none;">
                    <label class="field-label">Fecha y hora de envío</label>
                    <input type="datetime-local" name="scheduled_at" class="field-input"
                           value="<?= htmlspecialchars($_POST['scheduled_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px;">
                    Guardar campaña
                </button>
            </form>
        </div>

        <!-- ── Preview ── -->
        <div>
            <div style="font-size:.85rem;font-weight:600;color:var(--text-secondary);margin-bottom:.5rem;">Vista previa</div>
            <div style="border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden;background:#fff;">
                <div style="padding:10px 14px;background:var(--surface);border-bottom:1px solid var(--border);font-size:.82rem;color:var(--text-muted);">
                    Asunto: <span id="previewSubject" style="color:var(--text-primary);font-weight:500;"></span>
                </div>
                <iframe id="previewFrame" style="width:100%;height:480px;border:none;display:block;" srcdoc="<p style='color:#64748b;padding:2rem;text-align:center;font-size:.9rem;'>La vista previa aparecerá aquí mientras escribes.</p>"></iframe>
            </div>
            <p style="font-size:.76rem;color:var(--text-muted);margin-top:.4rem;">
                Actualización automática con debounce de 500ms.
            </p>
        </div>

    </div><!-- /grid -->
</div>

<script>
/** Aplica formato básico al textarea usando execCommand. */
function fmt(cmd) {
    const ta = document.getElementById('campBody');
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;
    const sel   = ta.value.substring(start, end);
    const tags  = { bold: ['<strong>','</strong>'], italic: ['<em>','</em>'] };
    const [open, close] = tags[cmd] || ['', ''];
    ta.setRangeText(open + sel + close, start, end, 'end');
    updatePreview();
}

function insertLink() {
    const url   = prompt('URL del enlace:');
    if (!url) return;
    const text  = prompt('Texto del enlace:', 'Haz clic aquí');
    const ta    = document.getElementById('campBody');
    const start = ta.selectionStart;
    ta.setRangeText(`<a href="${url}">${text || url}</a>`, start, start, 'end');
    updatePreview();
}

/** Actualiza el iframe de preview con debounce de 500ms. */
let _prevTimer = null;
function updatePreview() {
    clearTimeout(_prevTimer);
    _prevTimer = setTimeout(() => {
        const html    = document.getElementById('campBody').value;
        const subject = document.getElementById('campSubject').value;
        document.getElementById('previewSubject').textContent = subject;
        document.getElementById('previewFrame').srcdoc =
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;padding:1.5rem;color:#1e293b;line-height:1.6;}</style></head><body>' + html + '</body></html>';
    }, 500);
}

/** Actualiza el estimado de destinatarios. */
let _estTimer = null;
function updateEstimate() {
    clearTimeout(_estTimer);
    _estTimer = setTimeout(async () => {
        const plan   = document.getElementById('segPlan').value;
        const status = document.getElementById('segStatus').value;
        try {
            const res  = await fetch(`campaigns.php?action=estimate&plan=${plan}&status=${status}`);
            const json = await res.json();
            document.getElementById('recipientCount').textContent = json.count + ' empresa(s)';
        } catch {
            document.getElementById('recipientCount').textContent = '—';
        }
    }, 300);
}

function toggleSchedule(show) {
    document.getElementById('scheduleWrap').style.display = show ? 'block' : 'none';
}

// Inicializar
updateEstimate();
updatePreview();
</script>
