@extends('layouts.app')

@section('title', 'Flujo de efectivo · DeFinance')
@section('heading', 'Flujo de efectivo')

@section('content')
    <div class="page-head"><div><h1>Flujo de efectivo</h1><p>Movimiento de las cuentas de efectivo y bancos en el periodo.</p></div></div>

    @include('partials.period-filter')

    <div class="card">
        <div class="table-wrap">
            <table class="ledger">
                <thead><tr><th>Código</th><th>Cuenta</th><th class="amount">Inicial</th><th class="amount">Entradas</th><th class="amount">Salidas</th><th class="amount">Final</th></tr></thead>
                <tbody id="rows"><tr><td colspan="6" class="empty">Genera el reporte para ver el flujo.</td></tr></tbody>
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
                const r = await http.get(`/reports/cash-flow?from=${from}&to=${to}`);
                const body = document.getElementById('rows');
                if (!r.rows.length) { body.innerHTML = '<tr><td colspan="6" class="empty">No hay cuentas de efectivo marcadas (is_cash).</td></tr>'; document.getElementById('foot').innerHTML=''; return; }
                body.innerHTML = r.rows.map(x => `
                    <tr><td class="num code">${x.code}</td><td>${x.name}</td>
                        <td class="amount num">${format.money(x.opening)}</td>
                        <td class="amount num pos">${format.money(x.inflow)}</td>
                        <td class="amount num neg">${format.money(x.outflow)}</td>
                        <td class="amount num">${format.money(x.closing)}</td></tr>`).join('');
                document.getElementById('foot').innerHTML = `
                    <tr><td colspan="2">Totales</td>
                        <td class="amount num">${format.money(r.totals.opening)}</td>
                        <td class="amount num">${format.money(r.totals.inflow)}</td>
                        <td class="amount num">${format.money(r.totals.outflow)}</td>
                        <td class="amount num">${format.money(r.totals.closing)}</td></tr>`;
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
