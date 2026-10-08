/*
 | DF.kpis — reusable KPI header cards shown above report tables.
 |
 | Each card: { title, subtitle?, value, icon?, tone?, projected?, projectedLabel? }
 |   - value / projected: decimal strings or numbers (formatted as MXN here).
 |   - tone: 'auto' (default, colour by sign), 'pos', 'neg', 'muted' (neutral).
 |   - icon: a Font Awesome class (e.g. 'fa-coins'); omitted = no icon.
 |   - projected: when set, the card shows two values (Total / proyectado).
 |
 | Usage:
 |   DF.kpis.render('#kpis', [{ title:'Activos', value:r.summary.assets, icon:'fa-coins' }]);
 */
import { format } from './format.js';

function toneClass(tone, value) {
    if (tone === 'pos') return 'pos';
    if (tone === 'neg') return 'neg';
    if (tone === 'muted') return '';
    return format.signClass(value); // 'auto'
}

function card(c) {
    const tone = c.tone || 'auto';
    const icon = c.icon ? `<i class="fa-solid ${c.icon}" aria-hidden="true"></i>` : '';
    const sub = c.subtitle ? `<div class="kpi-sub">${c.subtitle}</div>` : '';

    if (c.projected !== undefined && c.projected !== null) {
        const pLabel = c.projectedLabel || 'Total proyectado';
        return `<div class="kpi-card">
            <div class="kpi-head"><div class="kpi-title">${c.title}</div>${icon}</div>
            ${sub}
            <div class="kpi-dual">
                <div class="kpi-dcol"><div class="kpi-dlabel">Total</div>
                    <div class="kpi-value num ${toneClass(tone, c.value)}">${format.money(c.value)}</div></div>
                <div class="kpi-dcol"><div class="kpi-dlabel">${pLabel}</div>
                    <div class="kpi-value num ${toneClass(tone, c.projected)}">${format.money(c.projected)}</div></div>
            </div>
        </div>`;
    }

    return `<div class="kpi-card">
        <div class="kpi-head"><div class="kpi-title">${c.title}</div>${icon}</div>
        ${sub}
        <div class="kpi-value num ${toneClass(tone, c.value)}">${format.money(c.value)}</div>
    </div>`;
}

export const kpis = {
    render(target, cards) {
        const el = typeof target === 'string' ? document.querySelector(target) : target;
        if (!el) return;
        el.classList.add('kpi-grid');
        el.innerHTML = (cards || []).map(card).join('');
    },
    clear(target) {
        const el = typeof target === 'string' ? document.querySelector(target) : target;
        if (el) el.innerHTML = '';
    },
};
