@extends('layouts.app')

@section('title', 'Promedios · DeFinance')
@section('heading', 'Promedios')

@section('content')
    <div class="page-head"><div><h1>Promedios</h1><p>Ingresos y gastos promedio por mes en el periodo.</p></div></div>

    @include('partials.period-filter')

    <div class="card">
        <div class="table-wrap">
            <table class="ledger">
                <thead><tr><th>Código</th><th>Cuenta</th><th class="amount">Total periodo</th><th class="amount">Promedio mensual</th></tr></thead>
                <tbody id="rows"><tr><td colspan="4" class="empty">Elige un periodo y genera el reporte.</td></tr></tbody>
                <tfoot id="foot"></tfoot>
            </table>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, format, guard } = DF;
        async function run() {
            if (!await guard.ensureAuth()) return;
            const from = document.getElementById('from').value, to = document.getElementById('to').value;
            try {
                const r = await http.get(`/reports/averages?from=${from}&to=${to}`);
                const body = document.getElementById('rows');
                const section = (title, rows) => rows.length
                    ? `<tr class="subtotal"><td colspan="4" style="font-weight:600;">${title}</td></tr>` +
                      rows.map(x => `<tr><td class="num code">${x.code}</td><td>${x.name}</td>
                            <td class="amount num">${format.money(x.total)}</td>
                            <td class="amount num">${format.money(x.average)}</td></tr>`).join('')
                    : '';
                const html = section('Ingresos', r.revenue) + section('Gastos', r.expenses);
                body.innerHTML = html || `<tr><td colspan="4" class="empty">Sin movimientos en el periodo.</td></tr>`;
                document.getElementById('foot').innerHTML = `
                    <tr><td colspan="2">Resultado (${r.months} ${r.months === 1 ? 'mes' : 'meses'})</td>
                        <td class="amount num ${format.signClass(r.totals.net.total)}">${format.money(r.totals.net.total)}</td>
                        <td class="amount num ${format.signClass(r.totals.net.average)}">${format.money(r.totals.net.average)}</td></tr>`;
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
