/*
|--------------------------------------------------------------------------
| app.js — single JS entry point
|--------------------------------------------------------------------------
| Loads fonts + styles, boots Bootstrap, initializes theme and shell, and
| exposes the reusable helper library on window.DF for Blade pages.
*/

import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/500.css';

import '../css/app.css';
import * as bootstrap from 'bootstrap';

import { initTheme, setTheme, toggleTheme } from './lib/theme.js';
import { http, HttpError } from './lib/http.js';
import { notify } from './lib/notify.js';
import { loading } from './lib/loading.js';
import { modal } from './lib/modal.js';
import { forms } from './lib/forms.js';
import { auth } from './lib/auth.js';
import { format } from './lib/format.js';
import { guard } from './lib/guard.js';
import { grid } from './lib/grid.js';
import { charts } from './lib/charts.js';
import { chartModal } from './lib/chartModal.js';
import { select } from './lib/select.js';
import { kpis } from './lib/kpis.js';
import * as firebase from './firebase/firebase.js';

window.DF = {
    http, HttpError, notify, loading, modal, forms, auth, format, guard, grid, charts, chartModal, select, kpis, firebase,
    theme: { setTheme, toggleTheme },
    bootstrap,
};

function initShell() {
    const app = document.querySelector('.app');
    if (!app) return;
    document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
        app.classList.toggle('nav-open');
    });
    document.querySelector('.scrim')?.addEventListener('click', () => app.classList.remove('nav-open'));
}

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initShell();
});
