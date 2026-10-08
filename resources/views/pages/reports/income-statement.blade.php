@extends('layouts.app')

@section('title', 'Estado de resultados · DeFinance')
@section('heading', 'Estado de resultados')

@section('content')
    <div class="page-head">
        <div><h1>Estado de resultados</h1><p>Ingresos menos gastos del periodo.</p></div>
        <button class="btn btn-ghost" id="chartBtn" disabled><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Ver gráficas</button>
    </div>

    @include('partials.period-filter')

    <div id="kpis"></div>

    <div class="card" style="max-width:680px;">
        <table class="statement" id="statement">
            <tbody><tr><td class="empty">Genera el reporte para ver el resultado.</td></tr></tbody>
        </table>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, format, guard, loading, charts, chartModal, kpis } = DF;
        function renderKpis(t) {
            kpis.render('#kpis', [
                { title: 'Ingresos', subtitle: 'Total de Ingresos', value: t.revenue, icon: 'fa-arrow-trend-up' },
                { title: 'Gastos', subtitle: 'Total de Gastos', value: t.expenses, icon: 'fa-arrow-trend-down' },
                { title: 'Utilidad / Pérdida', subtitle: 'Ingresos − Gastos', value: t.net_income, icon: 'fa-chart-line' },
                { title: 'Otros Productos Financieros', subtitle: 'Total de Productos Financieros', value: t.financial_income ?? 0, icon: 'fa-arrow-trend-up' },
                { title: 'Otros Gastos Financieros', subtitle: 'Total de Otros Gastos Financieros', value: t.financial_expense ?? 0, icon: 'fa-arrow-trend-down' },
                { title: 'Utilidad / Pérdida', subtitle: 'Resultado del Ejercicio', value: t.resultado_del_ejercicio ?? t.net_income, icon: 'fa-chart-line' },
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
            loading.skeleton('#statement', { rows: 5, cols: 2 });
            try {
                const r = await http.get(`/reports/income-statement?from=${from}&to=${to}`);
                if (r.totals) renderKpis(r.totals);
                const line = (x) => `<tr><td><span class="code">${x.code}</span> · ${x.name}</td><td class="amount num">${format.money(x.amount)}</td></tr>`;
                let html = '';
                html += `<tr class="subtotal"><td>Ingresos</td><td class="amount num">${format.money(r.totals.revenue)}</td></tr>`;
                html += r.revenue.map(line).join('');
                html += `<tr class="subtotal"><td>Gastos</td><td class="amount num">${format.money(r.totals.expenses)}</td></tr>`;
                html += r.expenses.map(line).join('');
                const net = r.totals.net_income;
                html += `<tr class="total"><td>${Number(net) >= 0 ? 'Utilidad' : 'Pérdida'} del periodo</td><td class="amount num ${format.signClass(net)}">${format.money(net)}</td></tr>`;
                document.querySelector('#statement tbody').innerHTML = html;

                const toSlices = (rows) => rows.map(x => ({ name: x.name, value: Number(x.amount) || 0 })).filter(x => x.value > 0);
                const gastos = toSlices(r.expenses), ingresos = toSlices(r.revenue);
                const views = [];
                if (gastos.length) views.push({ label: 'Composición de gastos', render: (el) => charts.donut(el, { slices: gastos }) });
                if (ingresos.length) views.push({ label: 'Composición de ingresos', render: (el) => charts.donut(el, { slices: ingresos }) });
                views.push({ label: 'Ingresos vs Gastos', render: (el) => charts.stackedBars(el, {
                    series: [{ key: 'ingresos', name: 'Ingresos' }, { key: 'gastos', name: 'Gastos' }],
                    rows: [{ label: 'Periodo', ingresos: Number(r.totals.revenue) || 0, gastos: Number(r.totals.expenses) || 0 }],
                    stacked: false,
                }) });
                wireChart(`Estado de resultados · ${from} → ${to}`, (gastos.length || ingresos.length) ? views : []);
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
