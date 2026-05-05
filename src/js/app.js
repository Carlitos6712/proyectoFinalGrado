/**
 * Módulo principal de la app es21plus.
 *
 * Provee utilidades compartidas (sidebar, toast, confirmación)
 * y el módulo de búsqueda avanzada (AJAX, pushState, vista tabla/grid).
 *
 * @author Carlitos6712
 */

'use strict';

// ── Sidebar ───────────────────────────────────────────────────────────────────

/**
 * Inicializa el toggle del sidebar y el overlay de cierre.
 *
 * @returns {void}
 */
function initSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const toggle   = document.getElementById('menuToggle');
    const closeBtn = document.getElementById('sidebarClose');

    if (!sidebar) return;

    /** @param {boolean} open */
    function setSidebar(open) {
        sidebar.classList.toggle('open', open);
        if (overlay) overlay.classList.toggle('active', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }

    toggle  && toggle.addEventListener('click',   () => setSidebar(true));
    closeBtn && closeBtn.addEventListener('click', () => setSidebar(false));
    overlay  && overlay.addEventListener('click',  () => setSidebar(false));
}

// ── Toast auto-dismiss ────────────────────────────────────────────────────────

/**
 * Auto-cierra los toasts con data-autodismiss="ms".
 *
 * @returns {void}
 */
function initToasts() {
    document.querySelectorAll('[data-autodismiss]').forEach(toast => {
        const delay = parseInt(toast.dataset.autodismiss, 10) || 4000;
        const bar   = toast.querySelector('.toast-progress');

        if (bar) {
            bar.style.transition = `width ${delay}ms linear`;
            requestAnimationFrame(() => { bar.style.width = '0%'; });
        }

        setTimeout(() => toast.remove(), delay);

        const closeBtn = toast.querySelector('.toast-close');
        closeBtn && closeBtn.addEventListener('click', () => toast.remove());
    });
}

// ── Confirm delete ────────────────────────────────────────────────────────────

/**
 * Intercepta links con data-confirm="delete" y pide confirmación.
 *
 * @returns {void}
 */
function initConfirmDelete() {
    document.addEventListener('click', e => {
        const link = e.target.closest('[data-confirm="delete"]');
        if (!link) return;
        if (!confirm('¿Estás seguro de que quieres eliminar este producto? Esta acción no se puede deshacer.')) {
            e.preventDefault();
        }
    });
}

// ── Búsqueda avanzada ─────────────────────────────────────────────────────────

/**
 * Recoge los valores actuales del formulario de filtros.
 *
 * @returns {URLSearchParams}
 */
function collectFilters() {
    const params = new URLSearchParams();

    const q = (document.getElementById('searchQ')?.value ?? '').trim();
    if (q)                                   params.set('q',           q);

    const cat = document.getElementById('filterCategoria')?.value ?? '';
    if (cat)                                 params.set('categoria_id', cat);

    const pMin = document.getElementById('filterPrecioMin')?.value ?? '';
    if (pMin !== '')                          params.set('precio_min',  pMin);

    const pMax = document.getElementById('filterPrecioMax')?.value ?? '';
    if (pMax !== '')                          params.set('precio_max',  pMax);

    const sMin = document.getElementById('filterStockMin')?.value ?? '';
    if (sMin !== '')                          params.set('stock_min',   sMin);

    const sMax = document.getElementById('filterStockMax')?.value ?? '';
    if (sMax !== '')                          params.set('stock_max',   sMax);

    if (document.getElementById('filterStockBajo')?.checked) {
        params.set('stock_bajo', '1');
    }

    const orden = document.getElementById('filterOrden')?.value ?? 'nombre_asc';
    if (orden && orden !== 'nombre_asc')      params.set('orden', orden);

    return params;
}

/**
 * Actualiza el badge del panel de filtros según si hay filtros activos.
 *
 * @param {URLSearchParams} params
 * @returns {void}
 */
function updateFilterBadge(params) {
    const badge   = document.getElementById('filterBadge');
    const hasFilters = params.toString() !== '' && (
        params.has('q') || params.has('categoria_id') ||
        params.has('precio_min') || params.has('precio_max') ||
        params.has('stock_min')  || params.has('stock_max') ||
        params.has('stock_bajo')
    );
    if (badge) badge.style.display = hasFilters ? '' : 'none';
}

/**
 * Renderiza la paginación dinámica.
 *
 * @param {number} page       Página actual.
 * @param {number} totalPages Total de páginas.
 * @returns {void}
 */
function renderPagination(page, totalPages) {
    const wrap = document.getElementById('paginationWrap');
    if (!wrap) return;

    if (totalPages <= 1) { wrap.innerHTML = ''; return; }

    const from = Math.max(1, page - 2);
    const to   = Math.min(totalPages, page + 2);
    let html   = '';

    if (page > 1) {
        html += `<button class="page-btn" data-page="${page - 1}">‹ Anterior</button>`;
    }
    for (let i = from; i <= to; i++) {
        html += `<button class="page-btn ${i === page ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    if (page < totalPages) {
        html += `<button class="page-btn" data-page="${page + 1}">Siguiente ›</button>`;
    }

    wrap.innerHTML = html;
    wrap.querySelectorAll('.page-btn[data-page]').forEach(btn => {
        btn.addEventListener('click', () => fetchResults(parseInt(btn.dataset.page, 10)));
    });
}

/**
 * Actualiza el contador de resultados.
 *
 * @param {number}  total      Total de productos encontrados.
 * @param {boolean} hasFilters Si hay filtros activos.
 * @returns {void}
 */
function renderCount(total, hasFilters) {
    const el = document.getElementById('searchCount');
    if (!el) return;
    const noun = total !== 1 ? 's' : '';
    el.innerHTML = hasFilters
        ? `<strong>${total}</strong> resultado${noun} encontrado${noun}`
        : `<strong>${total}</strong> producto${noun} en total`;
}

/**
 * Lanza la petición AJAX con los filtros actuales y actualiza el DOM.
 *
 * @param {number} page Número de página a cargar.
 * @returns {Promise<void>}
 */
async function fetchResults(page = 1) {
    const tbody    = document.getElementById('resultsTbody');
    const gridView = document.getElementById('gridView');
    const tableCard = document.getElementById('tableView');

    if (tbody) tbody.classList.add('search-loading');

    const params   = collectFilters();
    params.set('page', String(page));
    params.set('ajax', '1');

    // Actualizar URL con pushState
    const urlParams = collectFilters();
    if (page > 1) urlParams.set('page', String(page));
    const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
    history.pushState({ page, params: urlParams.toString() }, '', newUrl);

    updateFilterBadge(urlParams);

    try {
        const res  = await fetch(`buscar.php?${params.toString()}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();

        if (tbody) {
            tbody.innerHTML = json.html ?? '';
            tbody.classList.remove('search-loading');
            // Re-attach confirm-delete listeners handled by event delegation in initConfirmDelete
        }

        // Sincronizar grid con los mismos datos (recarga del servidor)
        // El grid se actualiza al cambiar de vista — aquí solo mostramos tabla
        renderPagination(json.page ?? page, json.total_pages ?? 0);

        const hasFilters = urlParams.toString() !== '' && (
            urlParams.has('q') || urlParams.has('categoria_id') ||
            urlParams.has('precio_min') || urlParams.has('precio_max') ||
            urlParams.has('stock_min')  || urlParams.has('stock_max') ||
            urlParams.has('stock_bajo')
        );
        renderCount(json.total ?? 0, hasFilters);

    } catch (err) {
        console.error('[es21plus] Error en búsqueda:', err);
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="8" class="td-empty">Error al cargar resultados.</td></tr>';
            tbody.classList.remove('search-loading');
        }
    }
}

/**
 * Limpia todos los filtros y restablece el estado inicial.
 *
 * @returns {void}
 */
function clearFilters() {
    const ids = ['searchQ', 'filterCategoria', 'filterPrecioMin', 'filterPrecioMax',
                 'filterStockMin', 'filterStockMax', 'filterOrden'];

    ids.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
    });

    const chk = document.getElementById('filterStockBajo');
    if (chk) chk.checked = false;

    history.pushState({}, '', window.location.pathname);
    fetchResults(1);
}

/**
 * Inicializa el panel de filtros colapsable.
 *
 * @returns {void}
 */
function initFilterPanel() {
    const panel  = document.getElementById('filterPanel');
    const toggle = document.getElementById('filterPanelToggle');
    if (!panel || !toggle) return;

    toggle.addEventListener('click', () => {
        panel.classList.toggle('open');
    });
}

/**
 * Inicializa el toggle entre vista tabla y cuadrícula.
 *
 * @returns {void}
 */
function initViewToggle() {
    const btnTable = document.getElementById('btnViewTable');
    const btnGrid  = document.getElementById('btnViewGrid');
    const tableWrap = document.getElementById('tableView');
    const gridWrap  = document.getElementById('gridView');

    if (!btnTable || !btnGrid) return;

    btnTable.addEventListener('click', () => {
        btnTable.classList.add('active');
        btnGrid.classList.remove('active');
        tableWrap && tableWrap.removeAttribute('hidden');
        gridWrap  && gridWrap.classList.remove('active');
    });

    btnGrid.addEventListener('click', () => {
        btnGrid.classList.add('active');
        btnTable.classList.remove('active');
        tableWrap && tableWrap.setAttribute('hidden', '');
        gridWrap  && gridWrap.classList.add('active');
    });
}

/**
 * Inicializa el módulo de búsqueda avanzada.
 * Solo se activa si existe el formulario de búsqueda.
 *
 * @returns {void}
 */
function initSearch() {
    const searchInput = document.getElementById('searchQ');
    if (!searchInput) return;

    let debounceTimer;

    // Búsqueda al escribir (con debounce de 350ms)
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchResults(1), 350);
    });

    // Botón aplicar filtros
    document.getElementById('btnApply')?.addEventListener('click', () => fetchResults(1));

    // Botón limpiar filtros
    document.getElementById('btnClear')?.addEventListener('click', clearFilters);

    // Filtros: aplicar al cambiar select u orden
    ['filterCategoria', 'filterOrden'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => fetchResults(1));
    });

    // Checkbox stock bajo
    document.getElementById('filterStockBajo')?.addEventListener('change', () => fetchResults(1));

    // Paginación: delegación de eventos
    document.getElementById('paginationWrap')?.addEventListener('click', e => {
        const btn = e.target.closest('.page-btn[data-page]');
        if (btn) fetchResults(parseInt(btn.dataset.page, 10));
    });

    // Restaurar estado al navegar con back/forward
    window.addEventListener('popstate', () => {
        const urlParams = new URLSearchParams(window.location.search);

        ['q','categoria_id','precio_min','precio_max','stock_min','stock_max','orden'].forEach(key => {
            const map = {
                q:            'searchQ',
                categoria_id: 'filterCategoria',
                precio_min:   'filterPrecioMin',
                precio_max:   'filterPrecioMax',
                stock_min:    'filterStockMin',
                stock_max:    'filterStockMax',
                orden:        'filterOrden',
            };
            const el = document.getElementById(map[key]);
            if (el) el.value = urlParams.get(key) ?? '';
        });

        const chk = document.getElementById('filterStockBajo');
        if (chk) chk.checked = urlParams.get('stock_bajo') === '1';

        const page = parseInt(urlParams.get('page') ?? '1', 10);
        fetchResults(page);
    });
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initToasts();
    initConfirmDelete();
    initFilterPanel();
    initViewToggle();
    initSearch();
});
