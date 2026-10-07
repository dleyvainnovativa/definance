/*
 | Loading-state helpers.
 | - setButtonLoading(btn, true) disables a button and shows a spinner,
 |   restoring its original label when set back to false.
 | - withLoading(btn, asyncFn) wraps an async action with that state.
 */
export function setButtonLoading(btn, isLoading) {
    if (!btn) return;
    if (isLoading) {
        if (!btn.dataset.originalHtml) btn.dataset.originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
            (btn.dataset.loadingText || 'Cargando…');
    } else {
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        if (btn.dataset.originalHtml) {
            btn.innerHTML = btn.dataset.originalHtml;
            delete btn.dataset.originalHtml;
        }
    }
}

export async function withLoading(btn, asyncFn) {
    setButtonLoading(btn, true);
    try {
        return await asyncFn();
    } finally {
        setButtonLoading(btn, false);
    }
}

/*
 | Table skeleton — shimmer placeholder rows while data loads. Replaces the bare
 | "Cargando…" text row. Pass the <table>, a container holding one, or a tbody.
 | Column count and per-column alignment are inferred from the <thead> (so money
 | columns right-align) unless given explicitly.
 |
 |   DF.loading.skeleton('#rows', { rows: 6 });   // #rows is a tbody or wrapper
 |   // ...then overwrite the tbody with real rows, or call clearSkeleton for empty.
 */
function resolveTable(target) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) return null;
    if (el.tagName === 'TABLE') return el;
    if (el.tagName === 'TBODY') return el.closest('table');
    return el.querySelector('table');
}

export function skeleton(target, { rows = 6, cols = null, widths = null } = {}) {
    const table = resolveTable(target);
    if (!table) return;

    const headCells = table.tHead?.rows?.[0]?.cells;
    const n = cols || headCells?.length || 4;
    const amountCol = (i) => headCells?.[i]?.classList.contains('amount');
    const barWidth = (i) => (widths?.[i] ?? (i === 0 ? '45%' : amountCol(i) ? '60%' : '80%'));

    const body = table.tBodies[0] || table.createTBody();
    body.setAttribute('data-skeleton', 'true');
    body.innerHTML = Array.from({ length: rows }).map(() =>
        '<tr class="skeleton-row" aria-hidden="true">' +
        Array.from({ length: n }).map((_, i) =>
            `<td class="${amountCol(i) ? 'amount' : ''}"><span class="skeleton-bar" style="width:${barWidth(i)}"></span></td>`
        ).join('') +
        '</tr>'
    ).join('');
    table.setAttribute('aria-busy', 'true');
}

/** Remove the skeleton marker (the caller then injects real rows or an empty state). */
export function clearSkeleton(target) {
    const table = resolveTable(target);
    if (!table) return;
    table.removeAttribute('aria-busy');
    table.tBodies[0]?.removeAttribute('data-skeleton');
}

export const loading = { setButtonLoading, withLoading, skeleton, clearSkeleton };
