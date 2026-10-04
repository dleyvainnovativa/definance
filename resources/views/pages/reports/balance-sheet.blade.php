@extends('layouts.app')

@section('title', 'Balance general · DeFinance')
@section('heading', 'Balance general')

@section('content')
    <div class="page-head">
        <div><h1>Balance general</h1><p>Activos = Pasivos + Capital, a una fecha.</p></div>
        <span class="pill" id="balancedPill" hidden></span>
    </div>

    <div class="toolbar">
        <div class="field"><label for="asof">Al</label><input class="form-control" id="asof" type="date"></div>
        <button class="btn btn-primary" id="genBtn">Generar</button>
    </div>

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
        const { http, notify, format, guard } = DF;
        document.getElementById('asof').value = new Date().toISOString().slice(0, 10);

        async function run() {
            if (!await guard.ensureAuth()) return;
            const asOf = document.getElementById('asof').value;
            try {
                const r = await http.get(`/reports/balance-sheet?as_of=${asOf}`);
                const line = (x) => `<tr><td>${x.name}</td><td class="amount num">${format.money(x.amount)}</td></tr>`;

                document.getElementById('assets').innerHTML =
                    r.assets.map(line).join('') +
                    `<tr class="total"><td>Total activos</td><td class="amount num">${format.money(r.totals.assets)}</td></tr>`;

                let le = r.liabilities.map(line).join('') +
                    `<tr class="subtotal"><td>Total pasivos</td><td class="amount num">${format.money(r.totals.liabilities)}</td></tr>` +
                    r.equity.map(line).join('') +
                    `<tr><td>Resultado del ejercicio</td><td class="amount num ${format.signClass(r.net_income)}">${format.money(r.net_income)}</td></tr>` +
                    `<tr class="subtotal"><td>Total capital</td><td class="amount num">${format.money(r.totals.equity_with_result)}</td></tr>` +
                    `<tr class="total"><td>Pasivo + Capital</td><td class="amount num">${format.money(r.totals.liabilities_plus_equity)}</td></tr>`;
                document.getElementById('liabeq').innerHTML = le;

                const pill = document.getElementById('balancedPill');
                pill.hidden = false;
                pill.className = 'pill ' + (r.balanced ? 'ok' : 'bad');
                pill.textContent = r.balanced ? 'Cuadrado' : 'Descuadre';
            } catch (e) { notify.error(e.message); }
        }
        document.getElementById('genBtn').addEventListener('click', run);
        run();
    </script>
    @endpush
@endsection
