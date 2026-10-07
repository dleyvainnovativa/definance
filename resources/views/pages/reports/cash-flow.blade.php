@extends('layouts.app')

@section('title', 'Flujo de efectivo · DeFinance')
@section('heading', 'Flujo de efectivo')

@section('content')
    <div class="page-head"><div><h1>Flujo de efectivo</h1><p>Movimiento de las cuentas de efectivo y bancos en el periodo.</p></div></div>

    @include('partials.period-filter')

    <div class="card report-chart" id="cfChartCard" hidden>
        <h2>Entradas y salidas por cuenta</h2>
        <div id="cfChart"></div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="ledger ledger--cards">
                <thead><tr><th>Código</th><th>Cuenta</th><th class="amount">Inicial</th><th class="amount">Entradas</th><th class="amount">Salidas</th><th class="amount">Final</th></tr></thead>
                <tbody id="rows"><tr><td colspan="6" class="empty">Genera el reporte para ver el flujo.</td></tr></tbody>
                <tfoot id="foot"></tfoot>
            </table>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, format, guard, loading, charts } = DF;
        async function run() {
            if (!await guard.ensureAuth()) return;
            const from = document.getElementById('from').value, to = document.getElementById('to').value;
            loading.skeleton('#rows', { rows: 4 });
            try {
                const r = await http.get(`/reports/cash-flow?from=${from}&to=${to}`);
                const body = document.getElementById('rows');
                const chartCard = document.getElementById('cfChartCard');
                if (!r.rows.length) {
                    body.innerHTML = '<tr><td colspan="6" class="empty">No hay cuentas de efectivo marcadas (is_cash).</td></tr>';
                    document.getElementById('foot').innerHTML = '';
                    chartCard.hidden = true;
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

                const rows = r.rows
                    .map(x => ({ label: x.name, inflow: Number(x.inflow) || 0, outflow: Number(x.outflow) || 0 }))
                    .filter(x => x.inflow > 0 || x.outflow > 0);
                chartCard.hidden = rows.length === 0;
                if (rows.length) charts.stackedBars('#cfChart', {
                    series: [{ key: 'inflow', name: 'Entradas' }, { key: 'outflow', name: 'Salidas' }],
                    rows, stacked: false,
                });
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
