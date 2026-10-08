/*
 | DF.tableTools — sortable headers + numbered pagination for tables.
 |
 | Two entry points:
 |
 |   enhance(table, opts)  — CLIENT-side. Takes a fully-rendered <table> (all its
 |     rows already in <tbody>), makes its headers click-to-sort, and paginates
 |     the body with a numbered pager + rows-per-page selector. Re-call it after
 |     you rebuild the table body. Headers marked [data-no-sort] stay static.
 |     opts: { pageSize=10, pageSizes=[10,25,50,100], paginate=true, sortable=true }
 |
 |   pager(host, state)    — render just the numbered pager control (for a
 |     SERVER-paginated table such as Pólizas). You own the data fetch; this only
 |     draws the control and calls back.
 |     state: { page, pages, total, from, to, pageSize, pageSizes, onPage, onPageSize }
 */

// ---- shared value helpers (money / codes / text all sort sensibly) ----------
function asNum(s) {
    const t = String(s ?? '').trim().replace(/[\s,]/g, '');
    if (/^-?\$?\d+(\.\d+)?%?$/.test(t)) return parseFloat(t.replace(/[$%]/g, ''));
    return null;
}
function natural(a, b) {
    const ax = [], bx = [];
    String(a ?? '').toLowerCase().replace(/(\d+)|(\D+)/g, (_, n, s) => { ax.push([n === undefined ? Infinity : Number(n), s || '']); return ''; });
    String(b ?? '').toLowerCase().replace(/(\d+)|(\D+)/g, (_, n, s) => { bx.push([n === undefined ? Infinity : Number(n), s || '']); return ''; });
    while (ax.length && bx.length) {
        const an = ax.shift(), bn = bx.shift();
        const c = (an[0] - bn[0]) || an[1].localeCompare(bn[1]);
        if (c) return c;
    }
    return ax.length - bx.length;
}
function compare(x, y) {
    const nx = asNum(x), ny = asNum(y);
    if (nx !== null && ny !== null) return nx - ny;
    return natural(x, y);
}

// ---- numbered pager control -------------------------------------------------
function pageWindow(page, pages) {
    const want = new Set([1, pages, page, page - 1, page + 1]);
    const list = [...want].filter((p) => p >= 1 && p <= pages).sort((a, b) => a - b);
    const out = [];
    let prev = 0;
    for (const p of list) { if (p - prev > 1) out.push('…'); out.push(p); prev = p; }
    return out;
}

function pager(host, state) {
    const el = typeof host === 'string' ? document.querySelector(host) : host;
    if (!el) return;
    const { page, pages, total, from, to, pageSize, pageSizes, onPage, onPageSize } = state;

    const rpp = (pageSizes && onPageSize)
        ? `<label class="dt-rpp">Filas
             <select class="form-select">${pageSizes.map((n) => `<option value="${n}" ${n === pageSize ? 'selected' : ''}>${n}</option>`).join('')}</select>
           </label>` : '';

    const btn = (label, p, { disabled = false, active = false, icon = false } = {}) =>
        `<button type="button" class="dt-page-btn${active ? ' active' : ''}" ${disabled ? 'disabled' : ''} data-p="${p}">${icon ? `<i class="fa-solid ${label}" aria-hidden="true"></i>` : label}</button>`;

    const nums = pages > 1
        ? pageWindow(page, pages).map((t) => t === '…'
            ? '<span class="dt-ellipsis">…</span>'
            : btn(String(t), t, { active: t === page })).join('')
        : '';

    const controls = pages > 1 ? `
        ${btn('fa-angles-left', 1, { disabled: page <= 1, icon: true })}
        ${btn('fa-angle-left', page - 1, { disabled: page <= 1, icon: true })}
        ${nums}
        ${btn('fa-angle-right', page + 1, { disabled: page >= pages, icon: true })}
        ${btn('fa-angles-right', pages, { disabled: page >= pages, icon: true })}` : '';

    el.className = 'dt-pager';
    el.innerHTML = `
        <div class="dt-info">Mostrando ${total ? from : 0}–${to} de ${total}${rpp}</div>
        <div class="dt-pages">${controls}</div>`;

    el.querySelectorAll('.dt-page-btn[data-p]').forEach((b) => {
        b.addEventListener('click', () => { if (!b.disabled) onPage?.(Number(b.dataset.p)); });
    });
    const sel = el.querySelector('.dt-rpp select');
    if (sel) sel.addEventListener('change', () => onPageSize?.(Number(sel.value)));
}

// ---- client-side enhancer ---------------------------------------------------
// One controller per <table>; re-calling enhance() on an already-enhanced table
// just refreshes its data (after you rebuild the body) without stacking header
// listeners. Header listeners are wired once and read the live controller.
const MOUNTED = new WeakMap();

function enhance(table, opts = {}) {
    const tbl = typeof table === 'string' ? document.querySelector(table) : table;
    if (!tbl || !tbl.tBodies.length) return null;

    const existing = MOUNTED.get(tbl);
    if (existing) {
        existing.allRows = Array.from(tbl.tBodies[0].rows);
        existing.state.page = 1;
        existing.apply();
        return existing;
    }

    const pageSizes = opts.pageSizes || [10, 25, 50, 100];
    const ctrl = {
        tbl,
        state: { page: 1, pageSize: opts.pageSize || 10, sortCol: -1, dir: 'asc', paginate: opts.paginate !== false },
        allRows: Array.from(tbl.tBodies[0].rows),
        headCells: tbl.tHead ? Array.from(tbl.tHead.rows[0].cells) : [],
    };

    // pager host: reuse one right after the table, else insert it there
    let host = tbl.nextElementSibling && tbl.nextElementSibling.classList?.contains('dt-pager')
        ? tbl.nextElementSibling : null;
    if (!host) { host = document.createElement('div'); tbl.after(host); }
    ctrl.host = host;

    ctrl.apply = function apply() {
        const s = ctrl.state;
        let rows = ctrl.allRows;
        if (s.sortCol >= 0) {
            const dir = s.dir === 'asc' ? 1 : -1;
            const val = (tr) => { const c = tr.cells[s.sortCol]; return c ? (c.dataset.sort ?? c.textContent) : ''; };
            rows = ctrl.allRows.map((tr, i) => [tr, i]).sort((a, b) => (compare(val(a[0]), val(b[0])) || (a[1] - b[1])) * dir).map((x) => x[0]);
        }
        const total = rows.length;
        const pages = s.paginate === false ? 1 : Math.max(1, Math.ceil(total / s.pageSize));
        if (s.page > pages) s.page = pages;
        const start = s.paginate === false ? 0 : (s.page - 1) * s.pageSize;
        const end = s.paginate === false ? total : start + s.pageSize;
        ctrl.tbl.tBodies[0].replaceChildren(...rows.slice(start, end));

        ctrl.headCells.forEach((th, i) => {
            th.classList.toggle('asc', s.sortCol === i && s.dir === 'asc');
            th.classList.toggle('desc', s.sortCol === i && s.dir === 'desc');
        });

        pager(ctrl.host, {
            page: s.page, pages, total,
            from: start + 1, to: Math.min(end, total),
            pageSize: s.pageSize, pageSizes,
            onPage: (p) => { s.page = p; ctrl.apply(); },
            onPageSize: (n) => { s.pageSize = n; s.page = 1; ctrl.apply(); },
        });
    };

    if (opts.sortable !== false) {
        ctrl.headCells.forEach((th, i) => {
            if (th.hasAttribute('data-no-sort')) return;
            th.classList.add('sortable');
            if (!th.querySelector('.dt-caret')) th.insertAdjacentHTML('beforeend', '<span class="dt-caret" aria-hidden="true"></span>');
            th.addEventListener('click', () => {
                const s = ctrl.state;
                if (s.sortCol === i) s.dir = s.dir === 'asc' ? 'desc' : 'asc';
                else { s.sortCol = i; s.dir = 'asc'; }
                s.page = 1;
                ctrl.apply();
            });
        });
    }

    MOUNTED.set(tbl, ctrl);
    ctrl.apply();
    return ctrl;
}

export const tableTools = { enhance, pager };
