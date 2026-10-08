@extends('layouts.app')

@section('title', 'Reporte por etiqueta · DeFinance')
@section('heading', 'Reporte por etiqueta')

@section('content')
    <style>
        tr.lbl-row { cursor: pointer; }
        tr.lbl-row .lbl-caret { display: inline-block; width: 1em; color: var(--c-text-muted); transition: transform .15s; }
        tr.lbl-row.open .lbl-caret { transform: rotate(90deg); }
        tr.lbl-detail > td { background: var(--c-surface-2); padding: .3rem .8rem; }
        .lbl-detail-table { margin: 0; width: 100%; }
        .lbl-detail-table td { padding: .35rem .6rem; border: 0; border-bottom: 1px solid var(--c-surface-3); white-space: nowrap; }
        .lbl-detail-table tr:last-child td { border-bottom: 0; }
        .lbl-detail-table a { color: var(--c-text); text-decoration: none; }
        .lbl-detail-table a:hover { text-decoration: underline; }
    </style>

    <div class="page-head">
        <div><h1>Reporte por etiqueta</h1><p>Movimiento del periodo agrupado por etiqueta. Haz clic en una etiqueta para ver el detalle por cuenta.</p></div>
        <button class="btn btn-ghost" id="chartBtn" disabled><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Ver gráficas</button>
    </div>

    @include('partials.period-filter')

    <div class="card">
        <div class="table-wrap">
            <table class="ledger ledger--cards">
                <thead><tr><th>Etiqueta</th><th>Cuentas</th><th class="amount">Total del periodo</th></tr></thead>
                <tbody id="rows"><tr><td colspan="3" class="empty">Elige un periodo y genera el reporte.</td></tr></tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, format, guard, loading, charts, chartModal } = DF;
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        function wireChart(title, views) {
            const btn = document.getElementById('chartBtn');
            btn.disabled = !views.length;
            btn.onclick = views.length ? () => chartModal.open({ title, views }) : null;
        }
        async function run() {
            if (!await guard.ensureAuth()) return;
            const from = document.getElementById('from').value, to = document.getElementById('to').value;
            loading.skeleton('#rows', { rows: 5, cols: 3 });
            try {
                const r = await http.get(`/reports/by-label?from=${from}&to=${to}`);
                const body = document.getElementById('rows');
                if (!r.rows.length) { body.innerHTML = '<tr><td colspan="3" class="empty">No hay etiquetas. Crea etiquetas y asígnalas a cuentas.</td></tr>'; wireChart('', []); return; }

                body.innerHTML = r.rows.map((x, i) => {
                    const n = x.accounts.length;
                    const accts = n
                        ? x.accounts.slice(0, 4).map(a => a.code).join(', ') + (n > 4 ? '…' : '')
                        : '<span style="color:var(--c-text-subtle)">sin movimiento</span>';
                    const caret = n ? '<span class="lbl-caret">▸</span> ' : '<span style="display:inline-block;width:1em"></span>';
                    const main = `<tr class="lbl-row" data-i="${i}">
                        <td data-label="Etiqueta">${caret}<span class="tag-chip" style="--chip:${x.color || 'var(--c-primary)'}">${esc(x.name)}</span></td>
                        <td data-label="Cuentas" class="code">${accts}</td>
                        <td data-label="Total" class="amount num ${format.signClass(x.total)}">${format.money(x.total)}</td>
                    </tr>`;
                    const detail = n ? `<tr class="lbl-detail" data-detail="${i}" hidden><td colspan="3">
                        <table class="lbl-detail-table"><tbody>${x.accounts.map(a => `
                            <tr>
                                <td><a href="/entries?account_id=${a.account_id}&from=${r.from}&to=${r.to}" title="Ver pólizas de esta cuenta"><span class="code">${esc(a.code)}</span> · ${esc(a.name)}</a></td>
                                <td class="amount num ${format.signClass(a.amount)}">${format.money(a.amount)}</td>
                            </tr>`).join('')}</tbody></table>
                    </td></tr>` : '';
                    return main + detail;
                }).join('');

                body.querySelectorAll('tr.lbl-row').forEach(tr => tr.addEventListener('click', () => {
                    const d = body.querySelector(`tr.lbl-detail[data-detail="${tr.dataset.i}"]`);
                    if (!d) return;
                    d.hidden = !d.hidden;
                    tr.classList.toggle('open', !d.hidden);
                }));

                const slices = r.rows.map(x => ({ name: x.name, value: Math.abs(Number(x.total) || 0), color: x.color || undefined }))
                    .filter(x => x.value > 0);
                const views = slices.length ? [
                    { label: 'Distribución por etiqueta', render: (el) => charts.donut(el, { slices }) },
                    { label: 'Total por etiqueta', render: (el) => charts.bars(el, { rows: slices.map(s => ({ name: s.name, value: s.value })) }) },
                ] : [];
                wireChart(`Por etiqueta · ${from} → ${to}`, views);
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
