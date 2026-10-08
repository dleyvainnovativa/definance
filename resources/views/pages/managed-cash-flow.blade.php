@extends('layouts.app')

@section('title', 'Flujo ajustado · DeFinance')
@section('heading', 'Flujo ajustado')

@section('content')
    <style>
        .grid-table .grid-input {
            width: 100%; max-width: 150px; margin-left: auto; text-align: right;
            padding: .3rem .5rem; font-size: .85rem;
        }
        .grid-table td, .grid-table th { white-space: nowrap; }
        .carry-note { font-size: .82rem; color: var(--c-text-muted); margin: .2rem 0 1rem; }
    </style>

    <div class="page-head">
        <div>
            <h1>Flujo ajustado</h1>
            <p>Flujo de efectivo editable: parte del flujo real y proyecta por mes. El saldo final se arrastra al mes siguiente.</p>
        </div>
    </div>

    <div class="toolbar">
        <div class="field"><label for="period">Mes</label><input class="form-control" id="period" type="month"></div>
        <button class="btn btn-ghost" id="genBtn" type="button">Generar</button>
        <div class="spacer"></div>
        <button class="btn btn-primary" id="saveBtn" type="button" data-loading-text="Guardando…" disabled>Guardar proyección</button>
    </div>

    <p class="carry-note" id="carryNote" hidden></p>

    <div id="kpis"></div>

    <div class="card">
        <div class="table-wrap" id="grid">
            <div class="empty">Elige un mes y genera la proyección.</div>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, grid, format, guard, kpis } = DF;
        let controller = null;

        function renderKpis(t) {
            const actualClosing = (Number(t.opening) || 0) + (Number(t.actual) || 0);
            kpis.render('#kpis', [
                { title: 'Saldo inicial', subtitle: 'Efectivo al inicio del mes', value: t.opening, tone: 'auto', icon: 'fa-wallet' },
                { title: 'Movimiento del periodo', value: t.actual, projected: t.planned, icon: 'fa-arrow-trend-up' },
                { title: 'Saldo final', value: actualClosing, projected: t.closing, icon: 'fa-money-bill-wave' },
            ]);
        }

        document.getElementById('period').value = new Date().toISOString().slice(0, 7);

        function recompute(r) {
            const planned = parseFloat(r.planned) || 0;
            const actual = parseFloat(r.actual) || 0;
            const opening = parseFloat(r.opening) || 0;
            r.variance = planned - actual;
            r.closing = opening + planned;
            return r;
        }

        async function run() {
            if (!await guard.ensureAuth()) return;
            const period = document.getElementById('period').value;
            if (!period) { notify.error('Elige un mes.'); return; }
            try {
                const r = await http.get(`/managed-cash-flow?period=${period}`);
                render(r);
            } catch (e) { notify.error(e.message); }
        }

        function render(r) {
            const el = document.getElementById('grid');
            if (!r.has_cash_accounts) {
                el.innerHTML = '<div class="empty">No hay cuentas marcadas como efectivo (is_cash). Marca tus cuentas de caja/bancos en el catálogo.</div>';
                document.getElementById('saveBtn').disabled = true;
                document.getElementById('carryNote').hidden = true;
                kpis.clear('#kpis');
                return;
            }
            renderKpis(r.totals);
            controller = grid.mount('#grid', {
                columns: [
                    { key: 'code', label: 'Código', cls: 'code' },
                    { key: 'name', label: 'Cuenta' },
                    { key: 'opening', label: 'Inicial', align: 'right', type: 'money' },
                    { key: 'actual', label: 'Real', align: 'right', type: 'money' },
                    { key: 'planned', label: 'Planeado', align: 'right', editable: true },
                    { key: 'variance', label: 'Variación', align: 'right', type: 'money' },
                    { key: 'closing', label: 'Final', align: 'right', type: 'money' },
                ],
                rows: r.rows,
                totals: ['opening', 'actual', 'planned', 'variance', 'closing'],
                recompute,
            });
            document.getElementById('saveBtn').disabled = false;
            const note = document.getElementById('carryNote');
            note.hidden = false;
            note.textContent = `Saldo final proyectado del periodo: ${format.money(r.totals.closing)} — se arrastra como saldo inicial del mes siguiente.`;
        }

        async function save() {
            if (!controller) return;
            const period = document.getElementById('period').value;
            const rows = controller.rows().map(r => ({
                account_id: r.account_id,
                planned_amount: parseFloat(r.planned) || 0,
                note: r.note ?? null,
            }));
            const btn = document.getElementById('saveBtn');
            await loading.withLoading(btn, async () => {
                try {
                    const r = await http.post('/managed-cash-flow', { period, rows });
                    render(r);
                    notify.success('Proyección guardada.');
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
