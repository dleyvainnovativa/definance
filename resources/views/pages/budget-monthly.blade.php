@extends('layouts.app')

@section('title', 'Presupuesto mensual · DeFinance')
@section('heading', 'Presupuesto mensual')

@section('content')
    <style>
        .grid-table .grid-input { width: 72px; text-align: right; padding: .25rem .4rem; font-size: .8rem; }
        .grid-table td, .grid-table th { white-space: nowrap; font-size: .82rem; padding: .45rem .6rem; }
        .budget-section-title { font-size: 1rem; padding: .2rem .1rem .6rem; }
        .seg { display: inline-flex; border: 1px solid var(--c-border); border-radius: var(--radius); overflow: hidden; }
        .seg button { border: 0; background: var(--c-surface); color: var(--c-text-muted); padding: .45rem .8rem; cursor: pointer; font-weight: 600; font-size: .85rem; }
        .seg button.active { background: var(--c-primary); color: var(--c-on-primary); }
        .net-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1rem; }
        @media (max-width: 640px) { .net-summary { grid-template-columns: 1fr; } }
    </style>

    <div class="page-head">
        <div>
            <h1>Presupuesto mensual</h1>
            <p>Presupuesto por mes y cuenta, contra el estado de resultados mensual.</p>
        </div>
    </div>

    <div class="toolbar">
        <div class="field"><label for="year">Año</label><input class="form-control" id="year" type="number" min="2000" max="2100" style="max-width:120px;"></div>
        <button class="btn btn-ghost" id="genBtn" type="button">Generar</button>
        <div class="field"><label>Vista</label>
            <div class="seg" id="modeSeg">
                <button data-mode="budget" class="active" type="button">Presupuesto</button>
                <button data-mode="actual" type="button">Real</button>
                <button data-mode="variance" type="button">Diferencia</button>
            </div>
        </div>
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
            <div class="stat"><div class="label">Utilidad presupuestada (año)</div><div class="value num" id="netBudget">—</div></div>
            <div class="stat"><div class="label">Utilidad real (año)</div><div class="value num" id="netActual">—</div></div>
            <div class="stat accent"><div class="label">Diferencia (año)</div><div class="value num" id="netVar">—</div></div>
        </div>
    </div>

    <div class="card" id="emptyState"><div class="empty">Elige un año y genera el presupuesto mensual.</div></div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, grid, format, guard } = DF;
        let data = null, mode = 'budget', revCtl = null, expCtl = null;

        document.getElementById('year').value = new Date().getFullYear();

        function columns() {
            const c = [{ key: 'code', label: 'Cód', cls: 'code' }, { key: 'name', label: 'Cuenta' }];
            (data?.months || []).forEach((mn, i) => c.push({
                key: 'm' + i, label: mn, align: 'right',
                editable: mode === 'budget',
                type: mode === 'budget' ? undefined : 'money',
            }));
            c.push({ key: 'total', label: 'Total', align: 'right', type: 'money' });
            return c;
        }

        // Flatten a section's rows for the active mode into grid rows.
        function flatten(section) {
            return section.map(r => {
                const o = { account_id: r.account_id, code: r.code, name: r.name, total: r.totals[mode] };
                r[mode].forEach((v, i) => { o['m' + i] = v; });
                return o;
            });
        }

        function recompute(r) {
            if (mode !== 'budget') return r; // actual/variance totals come from the server
            let t = 0;
            for (let i = 0; i < 12; i++) t += parseFloat(r['m' + i]) || 0;
            r.total = t;
            return r;
        }

        async function run() {
            if (!await guard.ensureAuth()) return;
            const year = document.getElementById('year').value;
            try {
                data = await http.get(`/budgets/monthly?year=${year}`);
                renderGrids();
            } catch (e) { notify.error(e.message); }
        }

        function renderGrids() {
            document.getElementById('report').hidden = false;
            document.getElementById('emptyState').hidden = true;
            const totalsKeys = [...Array(12)].map((_, i) => 'm' + i).concat('total');
            const cols = columns();
            revCtl = grid.mount('#revGrid', { columns: cols, rows: flatten(data.revenue), totals: totalsKeys, recompute });
            expCtl = grid.mount('#expGrid', { columns: cols, rows: flatten(data.expenses), totals: totalsKeys, recompute });
            const net = data.totals.net.total;
            document.getElementById('netBudget').textContent = format.money(net.budget);
            document.getElementById('netActual').textContent = format.money(net.actual);
            const nv = document.getElementById('netVar');
            nv.textContent = format.money(net.variance);
            nv.className = 'value num ' + format.signClass(net.variance);
            document.getElementById('saveBtn').disabled = mode !== 'budget';
        }

        function setMode(m) {
            mode = m;
            document.querySelectorAll('#modeSeg button').forEach(b => b.classList.toggle('active', b.dataset.mode === m));
            if (data) renderGrids();
        }

        async function save() {
            if (!data || mode !== 'budget') return;
            const year = Number(document.getElementById('year').value);
            const rows = [];
            [...(revCtl?.rows() || []), ...(expCtl?.rows() || [])].forEach(r => {
                for (let i = 0; i < 12; i++) rows.push({ account_id: r.account_id, month: i + 1, amount: parseFloat(r['m' + i]) || 0 });
            });
            const btn = document.getElementById('saveBtn');
            await loading.withLoading(btn, async () => {
                try {
                    data = await http.post('/budgets/monthly', { year, rows });
                    renderGrids();
                    notify.success('Presupuesto mensual guardado.');
                } catch (e) {
                    const msg = e.body?.errors ? Object.values(e.body.errors)[0][0] : e.message;
                    notify.error(msg);
                }
            });
        }

        document.getElementById('genBtn').addEventListener('click', run);
        document.getElementById('saveBtn').addEventListener('click', save);
        document.querySelectorAll('#modeSeg button').forEach(b => b.addEventListener('click', () => setMode(b.dataset.mode)));
        run();
    </script>
    @endpush
@endsection
