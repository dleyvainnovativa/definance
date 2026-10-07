@extends('layouts.app')

@section('title', 'Reporte por etiqueta · DeFinance')
@section('heading', 'Reporte por etiqueta')

@section('content')
    <div class="page-head">
        <div><h1>Reporte por etiqueta</h1><p>Movimiento del periodo agrupado por etiqueta (suma de sus cuentas).</p></div>
        <button class="btn btn-ghost" id="chartBtn" disabled><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Ver gráficas</button>
    </div>

    @include('partials.period-filter')

    <div class="card">
        <div class="table-wrap">
            <table class="ledger ledger--cards">
                <thead><tr><th>Etiqueta</th><th>Cuentas</th><th class="amount">Total del periodo</th></tr></thead>
                <tbody id="rows"><tr><td colspan="3" class="empty">Elige un periodo y genera el reporte.</td></tr></tbody>
            </table>
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
            const from = document.getElementById('from').value, to = document.getElementById('to').value;
            loading.skeleton('#rows', { rows: 5, cols: 3 });
            try {
                const r = await http.get(`/reports/by-label?from=${from}&to=${to}`);
                const body = document.getElementById('rows');
                if (!r.rows.length) { body.innerHTML = '<tr><td colspan="3" class="empty">No hay etiquetas. Crea etiquetas y asígnalas a cuentas.</td></tr>'; wireChart('', []); return; }

                body.innerHTML = r.rows.map(x => {
                    const accts = x.accounts.length
                        ? x.accounts.slice(0, 4).map(a => a.code).join(', ') + (x.accounts.length > 4 ? '…' : '')
                        : '<span style="color:var(--c-text-subtle)">sin movimiento</span>';
                    return `<tr>
                        <td data-label="Etiqueta"><span class="tag-chip" style="--chip:${x.color || 'var(--c-primary)'}">${x.name}</span></td>
                        <td data-label="Cuentas" class="code">${accts}</td>
                        <td data-label="Total" class="amount num ${format.signClass(x.total)}">${format.money(x.total)}</td>
                    </tr>`;
                }).join('');

                const slices = r.rows.map(x => ({ name: x.name, value: Math.abs(Number(x.total) || 0), color: x.color || undefined }))
                    .filter(x => x.value > 0);
                const views = slices.length ? [
                    { label: 'Distribución por etiqueta', render: (el) => charts.donut(el, { slices }) },
                    { label: 'Total por etiqueta', render: (el) => charts.bars(el, { rows: slices.map(s => ({ name: s.name, value: s.value })) }) },
                ] : [];
                wireChart(`Por etiqueta · ${from} → ${to}`, views);
            } catch (e) { notify.error(e.message); }
        }
        window.__runReport = run;
        run();
    </script>
    @endpush
@endsection
