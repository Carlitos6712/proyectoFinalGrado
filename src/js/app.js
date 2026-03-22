/**
 * @fileoverview MotoStock Pro – Frontend interactions.
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
// DELETE CONFIRMATION
// ─────────────────────────────────────────────────────

/**
 * Intercepts clicks on delete links (data-confirm="delete") and shows
 * a native confirm dialog before allowing navigation.
 *
 * @author Carlos Vico
 * @returns {void}
 */
function initEliminarConfirmacion() {
    document.addEventListener('click', (e) => {
        // Links to eliminar_producto.php with data-confirm attribute
        const link = e.target.closest('a[data-confirm="delete"]');
        if (link) {
            const row    = link.closest('tr');
            const nombre = row?.querySelector('.product-name')?.textContent?.trim()
                        ?? row?.querySelector('td:nth-child(3)')?.textContent?.trim()
                        ?? 'este producto';
            if (!confirm(`¿Eliminar "${nombre}"?\nEsta acción marcará el producto como inactivo.`)) {
                e.preventDefault();
            }
            return;
        }

        // Category delete buttons with data-confirm-cat attribute
        const catBtn = e.target.closest('button[data-confirm-cat]');
        if (catBtn) {
            const nombre = catBtn.dataset.confirmCat ?? 'esta categoría';
            if (!confirm(`¿Eliminar la categoría "${nombre}"?`)) {
                e.preventDefault();
            }
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
