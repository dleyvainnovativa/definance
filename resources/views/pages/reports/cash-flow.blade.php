@extends('layouts.app')

@section('title', 'Flujo de efectivo · DeFinance')
@section('heading', 'Flujo de efectivo')

@section('content')
    <div class="page-head">
        <div><h1>Flujo de efectivo</h1><p>Movimiento de las cuentas de efectivo y bancos en el periodo.</p></div>
        <button class="btn btn-ghost" id="chartBtn" disabled><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Ver gráficas</button>
    </div>

    @include('partials.period-filter')

    <div id="kpis"></div>

    <div class="card">
        <div class="table-wrap">
            <table class="ledger ledger--cards" id="cfTable">
                <thead><tr><th>Código</th><th>Cuenta</th><th class="amount">Inicial</th><th class="amount">Entradas</th><th class="amount">Salidas</th><th class="amount">Final</th></tr></thead>
                <tbody id="rows"><tr><td colspan="6" class="empty">Genera el reporte para ver el flujo.</td></tr></tbody>
                <tfoot id="foot"></tfoot>
            </table>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, format, guard, loading, charts, chartModal, kpis, tableTools } = DF;
        function paginate(hasRows) {
            const tbl = document.getElementById('cfTable');
            if (hasRows) tableTools.enhance(tbl, { pageSize: 10 });
            else { const pg = tbl.nextElementSibling; if (pg && pg.classList.contains('dt-pager')) pg.innerHTML = ''; }
        }
        function renderKpis(t) {
            kpis.render('#kpis', [
                { title: 'Saldo inicial de efectivo', value: t.opening, icon: 'fa-wallet' },
                { title: 'Saldo al final del periodo', value: t.closing, icon: 'fa-money-bill-wave' },
                { title: 'Saldo en B. de Comp', value: t.closing, icon: 'fa-scale-balanced' },
                { title: 'Variación del periodo', value: t.net_change, icon: 'fa-arrow-trend-up' },
            ]);
        }
        function wireChart(title, views) {
            const btn = document.getElementById('chartBtn');
            btn.disabled = !views.length;
            btn.onclick = views.length ? () => chartModal.open({ title, views }) : null;
        }
        async function run() {
            if (!await guard.ensureAuth()) return;
            const from = document.getElementById('from').value, to = document.getElementById('to').value;
            loading.skeleton('#rows', { rows: 4 });
            try {
                const r = await http.get(`/reports/cash-flow?from=${from}&to=${to}`);
                if (r.totals) renderKpis(r.totals);
                const body = document.getElementById('rows');
                if (!r.rows.length) {
                    body.innerHTML = '<tr><td colspan="6" class="empty">No hay cuentas de efectivo marcadas (is_cash).</td></tr>';
                    document.getElementById('foot').innerHTML = '';
                    paginate(false);
                    wireChart('', []);
                    return;
                }
                body.innerHTML = r.rows.map(x => `
                    <tr><td data-label="Código" class="num code">${x.code}</td><td data-label="Cuenta">${x.name}</td>
                        <td data-label="Inicial" class="amount num">${format.money(x.opening)}</td>
                        <td data-label="Entradas" class="amount num pos">${format.money(x.inflow)}</td>
                        <td data-label="Salidas" class="amount num neg">${format.money(x.outflow)}</td>
                        <td data-label="Final" class="amount num">${format.money(x.closing)}</td></tr>`).join('');
                document.getElementById('foot').innerHTML = `
                    <tr><td colspan="2">Totales</td>
                        <td class="amount num">${format.money(r.totals.opening)}</td>
                        <td class="amount num">${format.money(r.totals.inflow)}</td>
                        <td class="amount num">${format.money(r.totals.outflow)}</td>
                        <td class="amount num">${format.money(r.totals.closing)}</td></tr>`;
                paginate(true);

                const io = r.rows.map(x => ({ label: x.name, inflow: Number(x.inflow) || 0, outflow: Number(x.outflow) || 0 }))
                    .filter(x => x.inflow > 0 || x.outflow > 0);
                const closing = r.rows.map(x => ({ name: x.name, value: Math.abs(Number(x.closing) || 0) }))
                    .filter(x => x.value > 0).sort((a, b) => b.value - a.value);
                const views = [
                    { label: 'Entradas y salidas', render: (el) => charts.stackedBars(el, { series: [{ key: 'inflow', name: 'Entradas' }, { key: 'outflow', name: 'Salidas' }], rows: io, stacked: false }) },
                    { label: 'Saldo final por cuenta', render: (el) => charts.bars(el, { rows: closing }) },
                ];
                wireChart(`Flujo de efectivo · ${from} → ${to}`, io.length ? views : []);
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
