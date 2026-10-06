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

function short(v) {
    const n = Number(v) || 0, a = Math.abs(n);
    if (a >= 1e6) return (n / 1e6).toFixed(1) + 'M';
    if (a >= 1e3) return Math.round(n / 1e3) + 'k';
    return String(Math.round(n));
}
function clip(s, n) { s = String(s ?? ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }

export const charts = { line, bars };
