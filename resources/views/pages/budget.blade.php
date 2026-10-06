@extends('layouts.app')

@section('title', 'Presupuesto · DeFinance')
@section('heading', 'Presupuesto anual')

@section('content')
    <style>
        .grid-table .grid-input { width: 100%; max-width: 150px; margin-left: auto; text-align: right; padding: .3rem .5rem; font-size: .85rem; }
        .grid-table td, .grid-table th { white-space: nowrap; }
        .budget-section-title { font-size: 1rem; padding: .2rem .1rem .6rem; }
        .net-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1rem; }
        @media (max-width: 640px) { .net-summary { grid-template-columns: 1fr; } }
    </style>

    <div class="page-head">
        <div>
            <h1>Presupuesto anual</h1>
            <p>Presupuesto por cuenta de ingresos y gastos, comparado contra lo real del año.</p>
        </div>
    </div>

    <div class="toolbar">
        <div class="field"><label for="year">Año</label><input class="form-control" id="year" type="number" min="2000" max="2100" style="max-width:120px;"></div>
        <button class="btn btn-ghost" id="genBtn" type="button">Generar</button>
        <div class="spacer"></div>
        <button class="btn btn-primary" id="saveBtn" type="button" data-loading-text="Guardando…" disabled>Guardar presupuesto</button>
    </div>

    <div id="report" hidden>
        <div class="card card-pad" style="margin-bottom:1rem;">
            <h2 class="budget-section-title">Ingresos</h2>
            <div class="table-wrap" id="revGrid"></div>
        </div>
        <div class="card card-pad">
            <h2 class="budget-section-title">Gastos</h2>
            <div class="table-wrap" id="expGrid"></div>
        </div>

        <div class="net-summary">
            <div class="stat"><div class="label">Utilidad presupuestada</div><div class="value num" id="netBudget">—</div></div>
            <div class="stat"><div class="label">Utilidad real</div><div class="value num" id="netActual">—</div></div>
            <div class="stat accent"><div class="label">Diferencia</div><div class="value num" id="netVar">—</div></div>
        </div>
    </div>

    <div class="card" id="emptyState" hidden><div class="empty">Elige un año y genera el presupuesto.</div></div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, grid, format, guard } = DF;
        let revCtl = null, expCtl = null;

        document.getElementById('year').value = new Date().getFullYear();

        function recompute(r) {
            r.variance = (parseFloat(r.budget) || 0) - (parseFloat(r.actual) || 0);
            return r;
        }

        const columns = [
            { key: 'code', label: 'Código', cls: 'code' },
            { key: 'name', label: 'Cuenta' },
            { key: 'budget', label: 'Presupuesto', align: 'right', editable: true },
            { key: 'actual', label: 'Real', align: 'right', type: 'money' },
            { key: 'variance', label: 'Diferencia', align: 'right', type: 'money' },
        ];

        async function run() {
            if (!await guard.ensureAuth()) return;
            const year = document.getElementById('year').value;
            try {
                const r = await http.get(`/budgets?year=${year}`);
                render(r);
            } catch (e) { notify.error(e.message); }
        }

        function render(r) {
            document.getElementById('report').hidden = false;
            document.getElementById('emptyState').hidden = true;
            revCtl = grid.mount('#revGrid', { columns, rows: r.revenue, totals: ['budget', 'actual', 'variance'], recompute });
            expCtl = grid.mount('#expGrid', { columns, rows: r.expenses, totals: ['budget', 'actual', 'variance'], recompute });
            document.getElementById('netBudget').textContent = format.money(r.totals.net.budget);
            document.getElementById('netActual').textContent = format.money(r.totals.net.actual);
            const nv = document.getElementById('netVar');
            nv.textContent = format.money(r.totals.net.variance);
            nv.className = 'value num ' + format.signClass(r.totals.net.variance);
            document.getElementById('saveBtn').disabled = false;
        }

        async function save() {
            const year = document.getElementById('year').value;
            const rows = [...(revCtl?.rows() || []), ...(expCtl?.rows() || [])]
                .map(r => ({ account_id: r.account_id, amount: parseFloat(r.budget) || 0 }));
            const btn = document.getElementById('saveBtn');
            await loading.withLoading(btn, async () => {
                try {
                    const r = await http.post('/budgets', { year: Number(year), rows });
                    render(r);
                    notify.success('Presupuesto guardado.');
                } catch (e) {
                    const msg = e.body?.errors ? Object.values(e.body.errors)[0][0] : e.message;
                    notify.error(msg);
                }
            });
        }

        document.getElementById('genBtn').addEventListener('click', run);
        document.getElementById('saveBtn').addEventListener('click', save);
        run();
    </script>
    @endpush
@endsection
