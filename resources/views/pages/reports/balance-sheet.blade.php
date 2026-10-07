@extends('layouts.app')

@section('title', 'Balance general · DeFinance')
@section('heading', 'Balance general')

@section('content')
    <div class="page-head">
        <div><h1>Balance general</h1><p>Activos = Pasivos + Capital, a una fecha.</p></div>
        <span class="pill" id="balancedPill" hidden></span>
    </div>

    @include('partials.period-filter', ['target' => 'asof'])

    <div class="report-cols">
        <div class="card">
            <h2 class="statement-title">Activos</h2>
            <table class="statement"><tbody id="assets"></tbody></table>
        </div>
        <div class="card">
            <h2 class="statement-title">Pasivos y Capital</h2>
            <table class="statement"><tbody id="liabeq"></tbody></table>
        </div>
    </div>

    <div class="card report-chart" id="bsChartCard" hidden style="margin-top:1rem;">
        <h2>Estructura financiera</h2>
        <div id="bsChart"></div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, format, guard, loading, charts } = DF;

        async function run() {
            if (!await guard.ensureAuth()) return;
            const asOf = document.getElementById('as_of').value;
            loading.skeleton('#assets', { rows: 4, cols: 2 });
            loading.skeleton('#liabeq', { rows: 5, cols: 2 });
            try {
                const r = await http.get(`/reports/balance-sheet?as_of=${asOf}`);
                const line = (x) => `<tr><td>${x.name}</td><td class="amount num">${format.money(x.amount)}</td></tr>`;

                document.getElementById('assets').innerHTML =
                    r.assets.map(line).join('') +
                    `<tr class="total"><td>Total activos</td><td class="amount num">${format.money(r.totals.assets)}</td></tr>`;

                document.getElementById('liabeq').innerHTML =
                    r.liabilities.map(line).join('') +
                    `<tr class="subtotal"><td>Total pasivos</td><td class="amount num">${format.money(r.totals.liabilities)}</td></tr>` +
                    r.equity.map(line).join('') +
                    `<tr><td>Resultado del ejercicio</td><td class="amount num ${format.signClass(r.net_income)}">${format.money(r.net_income)}</td></tr>` +
                    `<tr class="subtotal"><td>Total capital</td><td class="amount num">${format.money(r.totals.equity_with_result)}</td></tr>` +
                    `<tr class="total"><td>Pasivo + Capital</td><td class="amount num">${format.money(r.totals.liabilities_plus_equity)}</td></tr>`;

                const pill = document.getElementById('balancedPill');
                pill.hidden = false;
                pill.className = 'pill ' + (r.balanced ? 'ok' : 'bad');
                pill.textContent = r.balanced ? 'Cuadrado' : 'Descuadre';

                const slices = [
                    { name: 'Activos', value: Number(r.totals.assets) || 0 },
                    { name: 'Pasivos', value: Number(r.totals.liabilities) || 0 },
                    { name: 'Capital', value: Number(r.totals.equity_with_result) || 0 },
                ].filter(s => s.value > 0);
                const card = document.getElementById('bsChartCard');
                card.hidden = slices.length === 0;
                if (slices.length) charts.donut('#bsChart', { slices });
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
