/*
 | DF.chartModal — a reusable fullscreen chart modal, fed the current page's
 | live data. Each page passes a title + a list of views; a view is just a
 | label and a render(el) callback (usually a DF.charts call closed over the
 | page's current dataset). A dropdown switches views; charts track the theme.
 |
 |   DF.chartModal.open({
 |     title: 'Balanza de comprobación · Octubre 2026',
 |     views: [
 |       { label: 'Saldo final',  render: (el) => DF.charts.bars(el, { rows }) },
 |       { label: 'Movimiento',   render: (el) => DF.charts.bars(el, { rows: mov }) },
 |     ],
 |   });
 */
import { modal } from './modal.js';

let built = false;
let views = [];

function build() {
    if (built) return;
    const wrap = document.createElement('div');
    wrap.innerHTML = `
        <div class="modal fade" id="dfChartModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-fullscreen">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="dfChartTitle">Gráficas</h5>
                        <div class="chart-modal-switch">
                            <label class="form-label mb-0" for="dfChartView">Vista</label>
                            <select class="form-select" id="dfChartView"></select>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="dfChartCanvas" class="chart-modal-canvas"></div>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.appendChild(wrap.firstElementChild);
    document.getElementById('dfChartView').addEventListener('change', renderActive);
    built = true;
}

function renderActive() {
    const sel = document.getElementById('dfChartView');
    const canvas = document.getElementById('dfChartCanvas');
    canvas.innerHTML = '';
    const view = views[Number(sel.value)];
    if (!view) { canvas.innerHTML = '<div class="empty">Sin datos para graficar.</div>'; return; }
    const host = document.createElement('div');
    canvas.appendChild(host);
    try { view.render(host); }
    catch (e) { canvas.innerHTML = '<div class="empty">No se pudo dibujar la gráfica.</div>'; }
}

export function open({ title = 'Gráficas', views: v = [] } = {}) {
    build();
    views = (v || []).filter((x) => x && typeof x.render === 'function');
    document.getElementById('dfChartTitle').textContent = title;

    const sel = document.getElementById('dfChartView');
    sel.innerHTML = views.map((x, i) => `<option value="${i}">${x.label || ('Vista ' + (i + 1))}</option>`).join('');
    sel.value = '0';
    // hide the switcher when there's only one view
    document.querySelector('.chart-modal-switch').style.visibility = views.length > 1 ? 'visible' : 'hidden';

    renderActive();
    modal.open('#dfChartModal');
}

export const chartModal = { open };
