/**
 * @fileoverview es21plus – Frontend interactions.
 * @author Carlos Vico
 * @version 1.0.0
 */
'use strict';

// ─────────────────────────────────────────────────────
// UTILITIES
// ─────────────────────────────────────────────────────

/**
 * Executes a function after a delay, cancelling previous pending calls.
 *
 * @author Carlos Vico
 * @param {Function} fn    - The function to execute.
 * @param {number}   delay - Milliseconds to wait before executing.
 * @returns {Function} Debounced version of fn.
 */
function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

/**
 * Fetches a URL and returns the response body as HTML text.
 *
 * @author Carlos Vico
 * @param {string} url - The endpoint URL.
 * @returns {Promise<string>} HTML response text.
 * @throws {Error} If the HTTP response is not OK.
 */
async function fetchHtml(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error(`Error HTTP ${res.status}`);
    return res.text();
}

// ─────────────────────────────────────────────────────
// SIDEBAR TOGGLE
// ─────────────────────────────────────────────────────

/**
 * Initialises the sidebar open/close behaviour, including the overlay
 * and close button for mobile viewports.
 *
 * @author Carlos Vico
 * @returns {void}
 */
function initSidebar() {
    const sidebar        = document.getElementById('sidebar');
    const menuToggle     = document.getElementById('menuToggle');
    const sidebarClose   = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (!sidebar) return;

    /**
     * Opens the sidebar and shows the overlay.
     *
     * @returns {void}
     */
    function openSidebar() {
        sidebar.classList.add('sidebar-open');
        sidebarOverlay?.classList.add('overlay-visible');
        document.body.classList.add('sidebar-is-open');
    }

    /**
     * Closes the sidebar and hides the overlay.
     *
     * @returns {void}
     */
    function closeSidebar() {
        sidebar.classList.remove('sidebar-open');
        sidebarOverlay?.classList.remove('overlay-visible');
        document.body.classList.remove('sidebar-is-open');
    }

    menuToggle?.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    // Close sidebar with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('sidebar-open')) {
            closeSidebar();
        }
    });
}

// ─────────────────────────────────────────────────────
// TOAST MANAGER
// ─────────────────────────────────────────────────────

/**
 * Animates and auto-dismisses a single toast element.
 *
 * @author Carlos Vico
 * @param {HTMLElement} toast          - The toast DOM element.
 * @param {number}      [duration=4000] - Time in ms before auto-dismiss.
 * @returns {void}
 */
function runToast(toast, duration = 4000) {
    const progress = toast.querySelector('.toast-progress');
    const closeBtn = toast.querySelector('.toast-close');

    // Animate progress bar
    if (progress) {
        progress.style.transition = `width ${duration}ms linear`;
        // Force reflow so the transition fires
        void progress.offsetWidth;
        progress.style.width = '0%';
    }

    /**
     * Removes the toast from the DOM with a fade-out animation.
     *
     * @returns {void}
     */
    function dismiss() {
        toast.style.transition = 'opacity 0.35s, transform 0.35s';
        toast.style.opacity    = '0';
        toast.style.transform  = 'translateX(110%)';
        setTimeout(() => toast.remove(), 380);
    }

    const autoDismissTimer = setTimeout(dismiss, duration);

    closeBtn?.addEventListener('click', () => {
        clearTimeout(autoDismissTimer);
        dismiss();
    });
}

/**
 * Finds all toast elements in the DOM and activates their dismiss behaviour.
 *
 * @author Carlos Vico
 * @returns {void}
 */
function initToasts() {
    document.querySelectorAll('.toast[data-autodismiss]').forEach((toast) => {
        const duration = parseInt(toast.dataset.autodismiss, 10) || 4000;
        // Small delay so the element is visible before starting the timer
        setTimeout(() => runToast(toast, duration), 120);
    });
}

// ─────────────────────────────────────────────────────
// LIVE SEARCH + CATEGORY FILTER
// ─────────────────────────────────────────────────────

/**
 * Updates the products table body with results from the server.
 *
 * @author Carlos Vico
 * @param {string} termino     - The search term.
 * @param {string} categoriaId - Selected category ID, or empty string.
 * @returns {Promise<void>}
 */
async function actualizarTabla(termino, categoriaId) {
    const tbody = document.getElementById('tbody-productos');
    if (!tbody) return;

    const params = new URLSearchParams({ ajax: '1' });
    if (termino)     params.set('q', termino);
    if (categoriaId) params.set('categoria_id', categoriaId);

    try {
        tbody.innerHTML = '<tr><td colspan="8" class="td-empty td-loading">Buscando…</td></tr>';
        tbody.innerHTML = await fetchHtml(`buscar.php?${params.toString()}`);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="td-empty td-error">Error al buscar: ${err.message}</td></tr>`;
    }
}

/**
 * Initialises the live search input and category filter select on the
 * dashboard table, wiring them together with a 350 ms debounce.
 *
 * @author Carlos Vico
 * @returns {void}
 */
function initBusqueda() {
    const inputBuscar     = document.getElementById('buscar-input');
    const selectCategoria = document.getElementById('filtro-categoria');

    if (!inputBuscar && !selectCategoria) return;

    const onCambio = debounce(() => {
        const termino     = inputBuscar?.value.trim()  ?? '';
        const categoriaId = selectCategoria?.value     ?? '';
        actualizarTabla(termino, categoriaId);
    }, 350);

    inputBuscar?.addEventListener('input', onCambio);
    selectCategoria?.addEventListener('change', onCambio);
}

// ─────────────────────────────────────────────────────
// DELETE CONFIRMATION MODAL
// ─────────────────────────────────────────────────────

/**
 * Creates and injects a reusable confirmation modal into the document body.
 *
 * @author Carlos Vico
 * @returns {HTMLElement} The modal overlay element.
 */
function createDeleteModal() {
    const overlay = document.createElement('div');
    overlay.id        = 'delete-modal-overlay';
    overlay.className = 'modal-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'modal-title');
    overlay.innerHTML = `
        <div class="modal-box">
            <div class="modal-icon-wrap">
                <svg class="modal-icon-svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    <path d="M10 11v6"/><path d="M14 11v6"/>
                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                </svg>
            </div>
            <h2 class="modal-title" id="modal-title">¿Eliminar producto?</h2>
            <p class="modal-body" id="modal-body">Esta acción marcará el producto como inactivo.</p>
            <div class="modal-actions">
                <button type="button" class="btn-ghost" id="modal-cancel">Cancelar</button>
                <button type="button" class="btn-danger" id="modal-confirm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    </svg>
                    Eliminar
                </button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    return overlay;
}

/**
 * Shows the delete confirmation modal and resolves with user's decision.
 *
 * @author Carlos Vico
 * @param {string} nombre - Product name to display in the modal.
 * @returns {Promise<boolean>} True when user confirms, false when cancelled.
 */
function showDeleteModal(nombre) {
    return new Promise((resolve) => {
        let overlay = document.getElementById('delete-modal-overlay');
        if (!overlay) overlay = createDeleteModal();

        overlay.querySelector('#modal-body').textContent =
            `"${nombre}" quedará inactivo. El historial de movimientos se conservará.`;

        overlay.classList.add('modal-visible');
        document.body.classList.add('modal-open');

        const confirmBtn = overlay.querySelector('#modal-confirm');
        const cancelBtn  = overlay.querySelector('#modal-cancel');

        /**
         * Closes the modal and resolves the promise.
         *
         * @param {boolean} result - User's decision.
         * @returns {void}
         */
        function close(result) {
            overlay.classList.remove('modal-visible');
            document.body.classList.remove('modal-open');
            confirmBtn.replaceWith(confirmBtn.cloneNode(true));
            cancelBtn.replaceWith(cancelBtn.cloneNode(true));
            resolve(result);
        }

        overlay.querySelector('#modal-confirm').addEventListener('click', () => close(true),  { once: true });
        overlay.querySelector('#modal-cancel').addEventListener('click',  () => close(false), { once: true });
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); }, { once: true });

        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape') { document.removeEventListener('keydown', handler); close(false); }
        });

        cancelBtn.focus();
    });
}

/**
 * Intercepts clicks on delete links (data-confirm="delete") and shows
 * a styled modal dialog before allowing navigation.
 *
 * @author Carlos Vico
 * @returns {void}
 */
function initEliminarConfirmacion() {
    document.addEventListener('click', async (e) => {
        const link = e.target.closest('a[data-confirm="delete"]');
        if (link) {
            e.preventDefault();
            const row    = link.closest('tr');
            const nombre = row?.querySelector('.product-name')?.textContent?.trim()
                        ?? row?.querySelector('td:nth-child(3)')?.textContent?.trim()
                        ?? 'este producto';
            const confirmed = await showDeleteModal(nombre);
            if (confirmed) window.location.href = link.href;
            return;
        }

        const catBtn = e.target.closest('button[data-confirm-cat]');
        if (catBtn) {
            const nombre    = catBtn.dataset.confirmCat ?? 'esta categoría';
            const confirmed = await showDeleteModal(nombre);
            if (!confirmed) e.preventDefault();
        }
    });
}

// ─────────────────────────────────────────────────────
// ROW HOVER HIGHLIGHT
// ─────────────────────────────────────────────────────

/**
 * Highlights the entire table row when the user hovers over any of its
 * action buttons, for improved visual feedback.
 *
 * @author Carlos Vico
 * @returns {void}
 */
function initRowHighlight() {
    document.addEventListener('mouseover', (e) => {
        const btn = e.target.closest('.action-btn');
        if (!btn) return;
        const row = btn.closest('tr');
        row?.classList.add('row-highlighted');
    });

    document.addEventListener('mouseout', (e) => {
        const btn = e.target.closest('.action-btn');
        if (!btn) return;
        const row = btn.closest('tr');
        row?.classList.remove('row-highlighted');
    });
}

// ─────────────────────────────────────────────────────
// SELECT-ALL CHECKBOX
// ─────────────────────────────────────────────────────

/**
 * Wires the "select all" checkbox in the table header to toggle all
 * row checkboxes in the tbody.
 *
 * @author Carlos Vico
 * @returns {void}
 */
function initSelectAll() {
    const selectAll = document.getElementById('selectAll');
    if (!selectAll) return;

    selectAll.addEventListener('change', () => {
        const tbody       = document.getElementById('tbody-productos');
        const checkboxes  = tbody?.querySelectorAll('input[type="checkbox"]') ?? [];
        checkboxes.forEach((cb) => { cb.checked = selectAll.checked; });
    });
}

// ─────────────────────────────────────────────────────
// GLOBAL INIT
// ─────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initToasts();
    initBusqueda();
    initEliminarConfirmacion();
    initRowHighlight();
    initSelectAll();
});
