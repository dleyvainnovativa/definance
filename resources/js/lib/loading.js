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

export const loading = { setButtonLoading, withLoading };
