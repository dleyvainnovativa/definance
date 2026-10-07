@extends('layouts.app')

@section('title', 'Balanza de comprobación · DeFinance')
@section('heading', 'Balanza de comprobación')

@section('content')
    <div class="page-head"><div><h1>Balanza de comprobación</h1><p>Saldo inicial, movimientos y saldo final por cuenta.</p></div></div>

    @include('partials.period-filter')

    <div class="card report-chart" id="tbChartCard" hidden>
        <h2>Cuentas con mayor saldo</h2>
        <div id="tbChart"></div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="ledger ledger--cards">
                <thead><tr><th>Código</th><th>Cuenta</th><th class="amount">Inicial</th><th class="amount">Cargos</th><th class="amount">Abonos</th><th class="amount">Final</th></tr></thead>
                <tbody id="rows"><tr><td colspan="6" class="empty">Elige un periodo y genera el reporte.</td></tr></tbody>
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
            loading.skeleton('#rows', { rows: 6 });
            try {
                const r = await http.get(`/reports/trial-balance?from=${from}&to=${to}`);
                const body = document.getElementById('rows');
                const chartCard = document.getElementById('tbChartCard');
                if (!r.rows.length) {
                    body.innerHTML = '<tr><td colspan="6" class="empty">Sin movimientos en el periodo.</td></tr>';
                    document.getElementById('foot').innerHTML = '';
                    chartCard.hidden = true;
                    return;
                }
                body.innerHTML = r.rows.map(x => `
                    <tr><td data-label="Código" class="num code">${x.code}</td><td data-label="Cuenta">${x.name}</td>
                        <td data-label="Inicial" class="amount num">${format.money(x.opening)}</td>
                        <td data-label="Cargos" class="amount num">${format.money(x.debit)}</td>
                        <td data-label="Abonos" class="amount num">${format.money(x.credit)}</td>
                        <td data-label="Final" class="amount num">${format.money(x.closing)}</td></tr>`).join('');
                document.getElementById('foot').innerHTML = `
                    <tr><td colspan="3"></td>
                        <td class="amount num">${format.money(r.totals.debit)}</td>
                        <td class="amount num">${format.money(r.totals.credit)}</td>
                        <td class="amount num">${r.totals.debit === r.totals.credit ? '✓' : '≠'}</td></tr>`;

                const top = r.rows
                    .map(x => ({ name: `${x.code} · ${x.name}`, value: Math.abs(Number(x.closing) || 0) }))
                    .filter(x => x.value > 0)
                    .sort((a, b) => b.value - a.value)
                    .slice(0, 8);
                chartCard.hidden = top.length === 0;
                if (top.length) charts.bars('#tbChart', { rows: top });
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
