/*
 | DF.grid — a small editable number grid for the planning modules
 | (FEA, Budget, Budget Monthly, Cash Count).
 |
 | Renders a <table class="ledger grid-table"> from a column spec + rows. Columns
 | may be read-only text/money or an editable money input. Computed columns are
 | derived by a `recompute(row)` hook (run on load and on every edit) so totals
 | and dependent cells update live without losing input focus.
 |
 | Usage:
 |   const g = DF.grid.mount('#grid', {
 |     columns: [
 |       { key:'name',    label:'Cuenta' },
 |       { key:'opening', label:'Inicial',  align:'right', type:'money' },
 |       { key:'actual',  label:'Real',     align:'right', type:'money' },
 |       { key:'planned', label:'Planeado', align:'right', editable:true },
 |       { key:'variance',label:'Variación',align:'right', type:'money' },
 |       { key:'closing', label:'Final',    align:'right', type:'money' },
 |     ],
 |     rows,                      // array of plain objects
 |     totals: ['opening','actual','planned','variance','closing'],
 |     recompute: (r) => { r.variance = r.planned - r.actual; r.closing = r.opening + Number(r.planned||0); return r; },
 |     onChange: () => {},
 |   });
 |   g.rows();   // current rows, with edited values
 */
import { format } from './format.js';

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export function mount(el, cfg) {
    const container = typeof el === 'string' ? document.querySelector(el) : el;
    if (!container) return null;

    const cols = cfg.columns;
    const recompute = cfg.recompute || ((r) => r);
    let rows = (cfg.rows || []).map((r) => recompute({ ...r }));

    const alignCls = (c) => (c.align === 'right' ? 'amount' : '');
    const moneyCell = (v) => `<span class="num ${format.signClass(v)}">${format.money(v)}</span>`;

    function cellHtml(col, row, i) {
        if (col.editable) {
            const v = row[col.key];
            return `<input class="form-control grid-input num" type="number" step="0.01" inputmode="decimal"
                        data-key="${col.key}" data-i="${i}" value="${v === null || v === undefined || v === '' ? '' : Number(v)}">`;
        }
        if (col.type === 'action') {
            return `<button type="button" class="btn btn-ghost btn-sm grid-action" data-i="${i}">${escapeHtml(col.actionLabel || 'Acción')}</button>`;
        }
        if (col.type === 'money') return moneyCell(row[col.key]);
        return escapeHtml(row[col.key]);
    }

    function totalsHtml() {
        if (!cfg.totals) return '';
        const tds = cols.map((c) => {
            if (cfg.totals.includes(c.key)) {
                const sum = rows.reduce((a, r) => a + (parseFloat(r[c.key]) || 0), 0);
                return `<td class="amount num">${moneyCell(sum)}</td>`;
            }
            return `<td>${escapeHtml(c.totalLabel || '')}</td>`;
        }).join('');
        return `<tfoot><tr>${tds}</tr></tfoot>`;
    }

    function render() {
        const head = `<thead><tr>${cols.map((c) => `<th class="${alignCls(c)} ${c.cls || ''}">${escapeHtml(c.label)}</th>`).join('')}</tr></thead>`;
        const body = `<tbody>${rows.map((r, i) =>
            `<tr data-row="${i}">${cols.map((c) => `<td class="${alignCls(c)} ${c.cls || ''}">${cellHtml(c, r, i)}</td>`).join('')}</tr>`).join('')}</tbody>`;
        container.innerHTML = `<table class="ledger grid-table">${head}${body}${totalsHtml()}</table>`;
        container.querySelectorAll('input.grid-input').forEach((inp) => {
            inp.addEventListener('input', onEdit);
        });
        container.querySelectorAll('button.grid-action').forEach((b) => {
            b.addEventListener('click', () => cfg.onAction?.(rows[Number(b.dataset.i)], Number(b.dataset.i)));
        });
    }

    function onEdit(e) {
        const inp = e.target;
        const i = Number(inp.dataset.i);
        rows[i][inp.dataset.key] = inp.value === '' ? null : inp.value;
        rows[i] = recompute(rows[i]);
        refreshComputedCells(i);
        refreshTotals();
        cfg.onChange?.(rows[i], rows);
    }

    // Update only the non-input computed cells of a row (keeps edit focus).
    function refreshComputedCells(i) {
        const tr = container.querySelector(`tr[data-row="${i}"]`);
        if (!tr) return;
        cols.forEach((c, ci) => {
            if (c.editable || c.type !== 'money') return;
            tr.children[ci].innerHTML = moneyCell(rows[i][c.key]);
        });
    }

    function refreshTotals() {
        const table = container.querySelector('table');
        const tf = table?.querySelector('tfoot');
        if (tf) tf.outerHTML = totalsHtml();
        else if (table && cfg.totals) table.insertAdjacentHTML('beforeend', totalsHtml());
    }

    render();

    // Programmatically set a cell (e.g. a computed "Contado" from a sub-dialog),
    // updating its input, recomputing the row, and refreshing computed cells.
    function setValue(i, key, value) {
        if (!rows[i]) return;
        rows[i][key] = value === '' || value === null || value === undefined ? null : value;
        rows[i] = recompute(rows[i]);
        const tr = container.querySelector(`tr[data-row="${i}"]`);
        const inp = tr?.querySelector(`input.grid-input[data-key="${key}"]`);
        if (inp) inp.value = rows[i][key] === null ? '' : Number(rows[i][key]);
        refreshComputedCells(i);
        refreshTotals();
        cfg.onChange?.(rows[i], rows);
    }

    return {
        rows: () => rows.map((r) => ({ ...r })),
        setRows: (r) => { rows = (r || []).map((x) => recompute({ ...x })); render(); },
        setValue,
        render,
    };
}

export const grid = { mount };
