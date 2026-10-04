/*
 | Theme controller — light/dark with persistence + OS preference.
 | Sets both data-theme (our tokens) and data-bs-theme (Bootstrap) on <html>.
 */
const STORAGE_KEY = 'df-theme';
const root = document.documentElement;

function systemPrefersDark() {
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
}

export function getStoredTheme() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

export function resolveTheme() {
    return getStoredTheme() ?? (systemPrefersDark() ? 'dark' : 'light');
}

export function applyTheme(theme) {
    root.setAttribute('data-theme', theme);
    root.setAttribute('data-bs-theme', theme); // keep Bootstrap in sync
}

export function setTheme(theme) {
    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        /* storage may be unavailable (private mode) — ignore */
    }
    applyTheme(theme);
    updateToggleIcons(theme);
}

export function toggleTheme() {
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    setTheme(next);
    return next;
}

function updateToggleIcons(theme) {
    document.querySelectorAll('[data-theme-toggle] i').forEach((i) => {
        i.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    });
}

export function initTheme() {
    const theme = resolveTheme();
    applyTheme(theme);
    updateToggleIcons(theme);

    // Wire any toggle buttons.
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
        btn.addEventListener('click', toggleTheme);
    });

    // Follow OS changes only while the user hasn't made an explicit choice.
    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!getStoredTheme()) {
            const t = e.matches ? 'dark' : 'light';
            applyTheme(t);
            updateToggleIcons(t);
        }
    });
}
