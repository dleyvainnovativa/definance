/*
 | Toast notifications — lightweight, theme-aware, no Bootstrap JS dependency.
 | Usage:  notify.success('Guardado');  notify.error('Algo salió mal');
 */
const VARIANTS = {
    success: { icon: 'fa-circle-check', cls: 'text-bg-success' },
    error: { icon: 'fa-circle-exclamation', cls: 'text-bg-danger' },
    warning: { icon: 'fa-triangle-exclamation', cls: 'text-bg-warning' },
    info: { icon: 'fa-circle-info', cls: 'text-bg-info' },
};

function stack() {
    let el = document.querySelector('.toast-stack');
    if (!el) {
        el = document.createElement('div');
        el.className = 'toast-stack';
        el.setAttribute('aria-live', 'polite');
        el.setAttribute('aria-atomic', 'true');
        document.body.appendChild(el);
    }
    return el;
}

function show(message, variant = 'info', timeout = 4000) {
    const v = VARIANTS[variant] ?? VARIANTS.info;
    const toast = document.createElement('div');
    toast.className = `toast align-items-center border-0 show ${v.cls}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="fa-solid ${v.icon}"></i><span></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close"></button>
        </div>`;
    toast.querySelector('span').textContent = message;

    const remove = () => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 200);
    };
    toast.querySelector('.btn-close').addEventListener('click', remove);
    stack().appendChild(toast);
    if (timeout) setTimeout(remove, timeout);
    return toast;
}

export const notify = {
    show,
    success: (m, t) => show(m, 'success', t),
    error: (m, t) => show(m, 'error', t),
    warning: (m, t) => show(m, 'warning', t),
    info: (m, t) => show(m, 'info', t),
};
