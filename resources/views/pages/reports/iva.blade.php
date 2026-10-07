@extends('layouts.app')

@section('title', 'IVA · DeFinance')
@section('heading', 'Declaración de IVA')

@section('content')
    <div class="page-head">
        <div><h1>Declaración de IVA</h1><p>IVA trasladado (cobrado) menos IVA acreditable (pagado) en el periodo.</p></div>
        <button class="btn btn-ghost" id="chartBtn" disabled><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Ver gráficas</button>
    </div>

    @include('partials.period-filter')

    <div class="card" style="max-width:560px;">
        <table class="statement" id="statement">
            <tbody><tr><td class="empty">Genera el reporte para ver el IVA del periodo.</td></tr></tbody>
        </table>
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
            const from = document.getElementById('from').value, to = document.getElementById('to').value;
            loading.skeleton('#statement', { rows: 3, cols: 2 });
            try {
                const r = await http.get(`/reports/iva?from=${from}&to=${to}`);
                const body = document.querySelector('#statement tbody');
                if (!r.accounts_configured) {
                    body.innerHTML = '<tr><td class="empty">Configura las cuentas 118 (IVA Acreditable) y 213 (IVA Trasladado) en tu catálogo.</td></tr>';
                    wireChart('', []);
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
                const has = bars[0].value > 0 || bars[1].value > 0;
                wireChart(`IVA · ${from} → ${to}`, has ? [{ label: 'Trasladado vs acreditable', render: (el) => charts.bars(el, { rows: bars }) }] : []);
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
