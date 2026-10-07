@extends('layouts.app')

@section('title', 'Balance general · DeFinance')
@section('heading', 'Balance general')

@section('content')
    <div class="page-head">
        <div><h1>Balance general</h1><p>Activos = Pasivos + Capital, a una fecha.</p></div>
        <div class="d-flex align-items-center gap-2">
            <span class="pill" id="balancedPill" hidden></span>
            <button class="btn btn-ghost" id="chartBtn" disabled><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Ver gráficas</button>
        </div>
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

    @push('scripts')
    <script type="module">
        const { http, notify, format, guard, loading, charts, chartModal } = DF;
        function wireChart(title, views) {
            const btn = document.getElementById('chartBtn');
            btn.disabled = !views.length;
            btn.onclick = views.length ? () => chartModal.open({ title, views }) : null;
        }
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

                const structure = [
                    { name: 'Activos', value: Number(r.totals.assets) || 0 },
                    { name: 'Pasivos', value: Number(r.totals.liabilities) || 0 },
                    { name: 'Capital', value: Number(r.totals.equity_with_result) || 0 },
                ].filter(s => s.value > 0);
                const bars = (rows) => rows.map(x => ({ name: `${x.code} · ${x.name}`, value: Math.abs(Number(x.amount) || 0) }))
                    .filter(x => x.value > 0).sort((a, b) => b.value - a.value).slice(0, 10);
                const views = [];
                if (structure.length) views.push({ label: 'Estructura financiera', render: (el) => charts.donut(el, { slices: structure }) });
                if (r.assets.length) views.push({ label: 'Activos por cuenta', render: (el) => charts.bars(el, { rows: bars(r.assets) }) });
                if (r.liabilities.length) views.push({ label: 'Pasivos por cuenta', render: (el) => charts.bars(el, { rows: bars(r.liabilities) }) });
                wireChart(`Balance general · al ${asOf}`, structure.length ? views : []);
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
