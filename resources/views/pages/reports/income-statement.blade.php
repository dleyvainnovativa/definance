@extends('layouts.app')

@section('title', 'Estado de resultados · DeFinance')
@section('heading', 'Estado de resultados')

@section('content')
    <div class="page-head"><div><h1>Estado de resultados</h1><p>Ingresos menos gastos del periodo.</p></div></div>

    @include('partials.period-filter')

    <div class="report-cols">
        <div class="card">
            <table class="statement" id="statement">
                <tbody><tr><td class="empty">Genera el reporte para ver el resultado.</td></tr></tbody>
            </table>
        </div>
        <div class="card report-chart" id="isChartCard" hidden>
            <h2>Composición de gastos</h2>
            <div id="isChart"></div>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, format, guard, loading, charts } = DF;
        async function run() {
            if (!await guard.ensureAuth()) return;
            const from = document.getElementById('from').value, to = document.getElementById('to').value;
            loading.skeleton('#statement', { rows: 5, cols: 2 });
            try {
                const r = await http.get(`/reports/income-statement?from=${from}&to=${to}`);
                const line = (x) => `<tr><td>${x.name}</td><td class="amount num">${format.money(x.amount)}</td></tr>`;
                let html = '';
                html += `<tr class="subtotal"><td>Ingresos</td><td class="amount num">${format.money(r.totals.revenue)}</td></tr>`;
                html += r.revenue.map(line).join('');
                html += `<tr class="subtotal"><td>Gastos</td><td class="amount num">${format.money(r.totals.expenses)}</td></tr>`;
                html += r.expenses.map(line).join('');
                const net = r.totals.net_income;
                html += `<tr class="total"><td>${Number(net) >= 0 ? 'Utilidad' : 'Pérdida'} del periodo</td><td class="amount num ${format.signClass(net)}">${format.money(net)}</td></tr>`;
                document.querySelector('#statement tbody').innerHTML = html;

                const slices = r.expenses.map(x => ({ name: x.name, value: Number(x.amount) || 0 })).filter(x => x.value > 0);
                const card = document.getElementById('isChartCard');
                card.hidden = slices.length === 0;
                if (slices.length) charts.donut('#isChart', { slices });
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
