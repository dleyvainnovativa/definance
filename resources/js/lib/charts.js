/*
 | DF.charts — tiny dependency-free SVG charts that use the app's design tokens
 | (so they track light/dark automatically) and always label values, never
 | relying on colour alone.
 |   DF.charts.line(el, { points:[{label,value}] })   — trend with a zero baseline
 |   DF.charts.bars(el, { rows:[{name,value}] })        — horizontal breakdown
 */
import { format } from './format.js';

const NS = 'http://www.w3.org/2000/svg';
const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

// Categorical series colours, drawn from the brand tokens (distinct hues,
// AA-legible as fills). Series/slice legends always carry the name + value so
// colour is never the only signal.
const PALETTE_TOKENS = ['--c-primary', '--c-accent', '--c-pos', '--c-warn', '--brand-blue-300', '--c-neg', '--brand-magenta', '--brand-orange'];
const palette = (i) => css(PALETTE_TOKENS[i % PALETTE_TOKENS.length]);

function svg(w, h) {
    const s = document.createElementNS(NS, 'svg');
    s.setAttribute('viewBox', `0 0 ${w} ${h}`);
    s.setAttribute('width', '100%');
    s.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    s.style.display = 'block';
    return s;
}
function el(tag, attrs, text) {
    const n = document.createElementNS(NS, tag);
    for (const k in attrs) n.setAttribute(k, attrs[k]);
    if (text != null) n.textContent = text;
    return n;
}

/** Monthly net-income style trend: single series, zero baseline, signed dots. */
export function line(container, { points = [] } = {}) {
    const host = typeof container === 'string' ? document.querySelector(container) : container;
    if (!host) return;
    host.innerHTML = '';
    if (!points.length) return;

    const W = 640, H = 240, padX = 44, padT = 16, padB = 28;
    const s = svg(W, H);

    const vals = points.map((p) => Number(p.value) || 0);
    let min = Math.min(0, ...vals), max = Math.max(0, ...vals);
    if (min === max) max = min + 1;
    const x = (i) => padX + (i * (W - padX - 12)) / Math.max(1, points.length - 1);
    const y = (v) => padT + (max - v) * (H - padT - padB) / (max - min);

    const border = css('--c-border'), muted = css('--c-text-subtle');
    const primary = css('--c-primary'), pos = css('--c-pos'), neg = css('--c-neg');

    // zero baseline
    s.appendChild(el('line', { x1: padX, y1: y(0), x2: W - 12, y2: y(0), stroke: border, 'stroke-width': 1 }));

    // area + line
    const d = points.map((p, i) => `${i ? 'L' : 'M'}${x(i)},${y(Number(p.value) || 0)}`).join(' ');
    const area = `${d} L${x(points.length - 1)},${y(0)} L${x(0)},${y(0)} Z`;
    s.appendChild(el('path', { d: area, fill: primary, 'fill-opacity': '0.12' }));
    s.appendChild(el('path', { d, fill: 'none', stroke: primary, 'stroke-width': 2, 'stroke-linejoin': 'round' }));

    points.forEach((p, i) => {
        const v = Number(p.value) || 0;
        const dot = el('circle', { cx: x(i), cy: y(v), r: 3.5, fill: v < 0 ? neg : (v > 0 ? pos : muted) });
        dot.appendChild(el('title', {}, `${p.label}: ${format.money(p.value)}`));
        s.appendChild(dot);
        s.appendChild(el('text', { x: x(i), y: H - 10, 'text-anchor': 'middle', 'font-size': 11, fill: muted }, p.label));
    });
    // min/max y labels
    s.appendChild(el('text', { x: 6, y: y(max) + 4, 'font-size': 10, fill: muted }, short(max)));
    s.appendChild(el('text', { x: 6, y: y(min) + 4, 'font-size': 10, fill: muted }, short(min)));

    host.appendChild(s);
}

/** Horizontal breakdown bars with name + value labels. */
export function bars(container, { rows = [] } = {}) {
    const host = typeof container === 'string' ? document.querySelector(container) : container;
    if (!host) return;
    host.innerHTML = '';
    if (!rows.length) { host.innerHTML = '<div class="empty">Sin datos en el periodo.</div>'; return; }

    const max = Math.max(1, ...rows.map((r) => Number(r.value) || 0));
    const rowH = 34, W = 640, labelW = 150, barMax = W - labelW - 110;
    const H = rows.length * rowH + 8;
    const s = svg(W, H);
    const primary = css('--c-primary'), text = css('--c-text'), muted = css('--c-text-muted'), track = css('--c-surface-3');

    rows.forEach((r, i) => {
        const yy = i * rowH + 6;
        const w = Math.max(2, ((Number(r.value) || 0) / max) * barMax);
        s.appendChild(el('text', { x: 0, y: yy + 16, 'font-size': 12, fill: text }, clip(r.name, 22)));
        s.appendChild(el('rect', { x: labelW, y: yy + 4, width: barMax, height: 16, rx: 4, fill: track }));
        const bar = el('rect', { x: labelW, y: yy + 4, width: w, height: 16, rx: 4, fill: primary });
        bar.appendChild(el('title', {}, `${r.name}: ${format.money(r.value)}`));
        s.appendChild(bar);
        s.appendChild(el('text', { x: W, y: yy + 16, 'text-anchor': 'end', 'font-size': 11, fill: muted }, format.money(r.value)));
    });

    host.appendChild(s);
}

/**
 * Vertical bar chart for comparing a few series across groups (e.g. income vs
 * expense per month). `stacked:true` stacks the series into one column per
 * group; otherwise they sit side by side. A legend (name + colour) is rendered
 * above; the stacked total (or per-bar value when grouped) labels each column.
 *
 *   DF.charts.stackedBars(el, {
 *     series: [{ key:'income', name:'Ingresos' }, { key:'expense', name:'Gastos' }],
 *     rows:   [{ label:'Ene', income:1000, expense:600 }, ...],
 *     stacked: true,
 *   });
 */
export function stackedBars(container, { series = [], rows = [], stacked = true } = {}) {
    const host = typeof container === 'string' ? document.querySelector(container) : container;
    if (!host) return;
    host.innerHTML = '';
    if (!rows.length || !series.length) { host.innerHTML = '<div class="empty">Sin datos en el periodo.</div>'; return; }

    series = series.map((s, i) => ({ ...s, color: s.color || palette(i) }));

    const val = (r, k) => Math.max(0, Number(r[k]) || 0);
    const groupTotal = (r) => (stacked
        ? series.reduce((a, s) => a + val(r, s.key), 0)
        : Math.max(...series.map((s) => val(r, s.key))));
    const max = Math.max(1, ...rows.map(groupTotal));

    const W = 640, H = 260, padT = 20, padB = 34, padL = 44, padR = 12;
    const plotH = H - padT - padB, plotW = W - padL - padR;
    const bandW = plotW / rows.length;
    const groupW = Math.min(bandW * 0.68, 72);
    const y0 = padT + plotH;
    const y = (v) => padT + plotH - (v / max) * plotH;

    const muted = css('--c-text-subtle'), border = css('--c-border'), text = css('--c-text-muted');
    const s = svg(W, H);

    // baseline + a couple of gridlines
    [0, 0.5, 1].forEach((f) => {
        const gy = y0 - f * plotH;
        s.appendChild(el('line', { x1: padL, y1: gy, x2: W - padR, y2: gy, stroke: border, 'stroke-width': f === 0 ? 1.2 : 1, 'stroke-dasharray': f === 0 ? '' : '3 4' }));
        s.appendChild(el('text', { x: padL - 6, y: gy + 3, 'text-anchor': 'end', 'font-size': 10, fill: muted }, short(max * f)));
    });

    rows.forEach((r, gi) => {
        const cx = padL + gi * bandW + bandW / 2;
        if (stacked) {
            let acc = 0;
            series.forEach((ser) => {
                const v = val(r, ser.key);
                if (v <= 0) return;
                const h = (v / max) * plotH;
                const yTop = y(acc + v);
                const rect = el('rect', { x: cx - groupW / 2, y: yTop, width: groupW, height: h, fill: ser.color });
                rect.appendChild(el('title', {}, `${r.label} · ${ser.name}: ${format.money(r[ser.key])}`));
                s.appendChild(rect);
                acc += v;
            });
            s.appendChild(el('text', { x: cx, y: y(acc) - 6, 'text-anchor': 'middle', 'font-size': 10.5, fill: text }, short(acc)));
        } else {
            const bw = groupW / series.length;
            series.forEach((ser, si) => {
                const v = val(r, ser.key);
                const h = (v / max) * plotH;
                const bx = cx - groupW / 2 + si * bw;
                const rect = el('rect', { x: bx + 1, y: y(v), width: Math.max(1, bw - 2), height: h, fill: ser.color });
                rect.appendChild(el('title', {}, `${r.label} · ${ser.name}: ${format.money(r[ser.key])}`));
                s.appendChild(rect);
            });
        }
        s.appendChild(el('text', { x: cx, y: H - 12, 'text-anchor': 'middle', 'font-size': 11, fill: muted }, clip(r.label, 10)));
    });

    host.appendChild(legend(series.map((ser) => ({ name: ser.name, color: ser.color }))));
    host.appendChild(s);
}

/**
 * Donut for composition (e.g. asset / liability / equity). Centre shows the
 * total; the legend lists each slice's name, value and share — so colour is
 * never the only cue.
 *
 *   DF.charts.donut(el, { slices:[{ name:'Activo', value:120000 }, ...] });
 */
export function donut(container, { slices = [], total: totalOverride = null } = {}) {
    const host = typeof container === 'string' ? document.querySelector(container) : container;
    if (!host) return;
    host.innerHTML = '';
    const data = slices.map((d, i) => ({ ...d, value: Math.max(0, Number(d.value) || 0), color: d.color || palette(i) }))
        .filter((d) => d.value > 0);
    if (!data.length) { host.innerHTML = '<div class="empty">Sin datos en el periodo.</div>'; return; }

    const sum = data.reduce((a, d) => a + d.value, 0);
    const total = totalOverride != null ? Number(totalOverride) : sum;

    const SZ = 240, cx = SZ / 2, cy = SZ / 2, rOuter = 100, rInner = 62;
    const s = svg(SZ, SZ);
    const text = css('--c-text'), muted = css('--c-text-muted');

    const arc = (a0, a1) => {
        const p = (r, a) => [cx + r * Math.cos(a), cy + r * Math.sin(a)];
        const large = a1 - a0 > Math.PI ? 1 : 0;
        const [x0, y0] = p(rOuter, a0), [x1, y1] = p(rOuter, a1);
        const [x2, y2] = p(rInner, a1), [x3, y3] = p(rInner, a0);
        return `M${x0},${y0} A${rOuter},${rOuter} 0 ${large} 1 ${x1},${y1} L${x2},${y2} A${rInner},${rInner} 0 ${large} 0 ${x3},${y3} Z`;
    };

    let a = -Math.PI / 2;
    data.forEach((d) => {
        const a1 = a + (d.value / sum) * Math.PI * 2;
        const path = el('path', { d: arc(a, a1 - 0.012), fill: d.color });
        path.appendChild(el('title', {}, `${d.name}: ${format.money(d.value)} (${Math.round((d.value / sum) * 100)}%)`));
        s.appendChild(path);
        a = a1;
    });

    s.appendChild(el('text', { x: cx, y: cy - 2, 'text-anchor': 'middle', 'font-size': 18, 'font-weight': 600, fill: text }, short(total)));
    s.appendChild(el('text', { x: cx, y: cy + 16, 'text-anchor': 'middle', 'font-size': 10.5, fill: muted }, 'Total'));

    const wrap = document.createElement('div');
    wrap.className = 'chart-donut';
    wrap.appendChild(s);
    wrap.appendChild(legend(data.map((d) => ({ name: d.name, color: d.color, meta: `${format.money(d.value)} · ${Math.round((d.value / sum) * 100)}%` }))));
    host.appendChild(wrap);
}

/** Shared HTML legend: a swatch + name (+ optional meta) per series/slice. */
function legend(items) {
    const box = document.createElement('div');
    box.className = 'chart-legend';
    box.innerHTML = items.map((it) =>
        `<span class="chart-legend__item"><i class="chart-legend__dot" style="background:${it.color}"></i>` +
        `<span class="chart-legend__name">${clip(it.name, 28)}</span>` +
        (it.meta ? `<span class="chart-legend__meta num">${it.meta}</span>` : '') +
        `</span>`
    ).join('');
    return box;
}

function short(v) {
    const n = Number(v) || 0, a = Math.abs(n);
    if (a >= 1e6) return (n / 1e6).toFixed(a >= 1e7 ? 0 : 1) + 'M';
    if (a >= 1e4) return Math.round(n / 1e3) + 'k';
    if (a >= 1e3) return (n / 1e3).toFixed(1) + 'k';   // 1k–10k keeps one decimal (1.6k, not 2k)
    return String(Math.round(n));
}
function clip(s, n) { s = String(s ?? ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }

export const charts = { line, bars, stackedBars, donut };
