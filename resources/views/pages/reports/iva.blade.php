@extends('layouts.app')

@section('title', 'IVA · DeFinance')
@section('heading', 'Declaración de IVA')

@section('content')
    <div class="page-head"><div><h1>Declaración de IVA</h1><p>IVA trasladado (cobrado) menos IVA acreditable (pagado) en el periodo.</p></div></div>

    @include('partials.period-filter')

    <div class="report-cols">
        <div class="card" style="max-width:560px;">
            <table class="statement" id="statement">
                <tbody><tr><td class="empty">Genera el reporte para ver el IVA del periodo.</td></tr></tbody>
            </table>
        </div>
        <div class="card report-chart" id="ivaChartCard" hidden>
            <h2>Trasladado vs acreditable</h2>
            <div id="ivaChart"></div>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, format, guard, loading, charts } = DF;
        async function run() {
            if (!await guard.ensureAuth()) return;
            const from = document.getElementById('from').value, to = document.getElementById('to').value;
            loading.skeleton('#statement', { rows: 3, cols: 2 });
            try {
                const r = await http.get(`/reports/iva?from=${from}&to=${to}`);
                const body = document.querySelector('#statement tbody');
                const card = document.getElementById('ivaChartCard');
                if (!r.accounts_configured) {
                    body.innerHTML = '<tr><td class="empty">Configura las cuentas 118 (IVA Acreditable) y 213 (IVA Trasladado) en tu catálogo.</td></tr>';
                    card.hidden = true;
                    return;
                }
                const aCargo = r.resultado === 'a_cargo';
                const netoAbs = Math.abs(Number(r.neto));
                body.innerHTML = `
                    <tr><td>IVA trasladado (cobrado)</td><td class="amount num">${format.money(r.trasladado)}</td></tr>
                    <tr><td>IVA acreditable (pagado)</td><td class="amount num">${format.money(r.acreditable)}</td></tr>
                    <tr class="total"><td>IVA ${aCargo ? 'a cargo' : 'a favor'}</td>
                        <td class="amount num ${aCargo ? 'neg' : 'pos'}">${format.money(netoAbs)}</td></tr>`;

                const bars = [
                    { name: 'Trasladado', value: Number(r.trasladado) || 0 },
                    { name: 'Acreditable', value: Number(r.acreditable) || 0 },
                ];
                card.hidden = (bars[0].value === 0 && bars[1].value === 0);
                if (!card.hidden) charts.bars('#ivaChart', { rows: bars });
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
