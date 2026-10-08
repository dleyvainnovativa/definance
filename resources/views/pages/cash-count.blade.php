@extends('layouts.app')

@section('title', 'Arqueo de caja · DeFinance')
@section('heading', 'Arqueo de caja')

@section('content')
    <style>
        .grid-table .grid-input { width: 100%; max-width: 160px; margin-left: auto; text-align: right; padding: .3rem .5rem; font-size: .85rem; }
        .grid-table td, .grid-table th { white-space: nowrap; }
        .warn-bar { background: var(--c-neg-soft); color: var(--c-neg); border: 1px solid var(--c-neg); border-radius: var(--radius); padding: .7rem 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .recent { margin-top: 1.4rem; }
        .recent h2 { font-size: 1rem; margin-bottom: .6rem; }
        .recent-item { display: flex; align-items: center; justify-content: space-between; padding: .6rem .9rem; border: 1px solid var(--c-border); border-radius: var(--radius); margin-bottom: .5rem; font-size: .88rem; }
    </style>

    <div class="page-head">
        <div>
            <h1>Arqueo de caja</h1>
            <p>Cuenta el efectivo real contra el saldo en libros. Las diferencias generan una póliza de ajuste.</p>
        </div>
        <button class="btn btn-ghost" id="settingsBtn" type="button">Configurar</button>
    </div>

    <div class="warn-bar" id="warnBar" hidden>
        <span>Configura la cuenta de “Diferencia en Arqueo” para poder guardar.</span>
        <button class="btn btn-primary" id="warnConfig" type="button">Configurar</button>
    </div>

    <div class="toolbar">
        <div class="field"><label for="date">Fecha</label><input class="form-control" id="date" type="date"></div>
        <button class="btn btn-ghost" id="genBtn" type="button">Generar</button>
        <div class="field" style="flex:1 1 220px;"><label for="note">Nota</label><input class="form-control" id="note" placeholder="Opcional"></div>
        <button class="btn btn-primary" id="saveBtn" type="button" data-loading-text="Guardando…" disabled>Guardar arqueo</button>
    </div>

    <div class="card">
        <div class="table-wrap" id="grid"><div class="empty">Elige una fecha y genera el arqueo.</div></div>
    </div>

    <div class="recent" id="recentWrap" hidden>
        <h2>Arqueos recientes</h2>
        <div id="recent"></div>
    </div>

    {{-- Settings modal --}}
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="settingsForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Configurar arqueo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="diffAccount">Cuenta “Diferencia en Arqueo”</label>
                        <select class="form-select" id="diffAccount" required></select>
                        <p class="mt-2 mb-0" style="color:var(--c-text-muted);font-size:.8rem;">
                            Sobrantes se abonan y faltantes se cargan a esta cuenta. Se cuentan las cuentas marcadas como efectivo (is_cash).
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" data-loading-text="Guardando…">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Denomination counter modal (MXN bills & coins) --}}
    <div class="modal fade" id="denomModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Contar efectivo — <span id="denomAccount"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <table class="ledger grid-table">
                        <thead><tr><th>Denominación</th><th class="amount">Cantidad</th><th class="amount">Subtotal</th></tr></thead>
                        <tbody id="denomRows"></tbody>
                        <tfoot><tr><td colspan="2"><b>Total contado</b></td><td class="amount num" id="denomTotal">$0.00</td></tr></tfoot>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="denomApply">Aplicar al contado</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, modal, grid, format, guard } = DF;
        let controller = null, accounts = [], denomState = {};

        document.getElementById('date').value = new Date().toISOString().slice(0, 10);

        const recompute = (r) => { r.difference = (parseFloat(r.counted) || 0) - (parseFloat(r.book) || 0); return r; };

        async function init() {
            if (!await guard.ensureAuth()) return;
            try {
                const acc = await http.get('/accounts?postable=1');
                accounts = acc.data.filter(a => a.is_postable);
                fillDiffOptions();
            } catch (e) { notify.error(e.message); }
            await run();
        }

        function fillDiffOptions() {
            document.getElementById('diffAccount').innerHTML =
                '<option value="">Selecciona…</option>' +
                accounts.map(a => `<option value="${a.id}">${a.code} · ${a.name}</option>`).join('');
        }

        async function run() {
            const date = document.getElementById('date').value;
            try {
                const r = await http.get(`/cash-count?date=${date}`);
                render(r);
            } catch (e) { notify.error(e.message); }
        }

        function render(r) {
            denomState = {}; // fresh count on each generate
            document.getElementById('warnBar').hidden = r.configured;
            if (r.difference_account) {
                document.getElementById('diffAccount').value = r.difference_account.id;
            }
            const el = document.getElementById('grid');
            if (!r.rows.length) {
                el.innerHTML = '<div class="empty">No hay cuentas de efectivo (is_cash). Márcalas en el catálogo.</div>';
                document.getElementById('saveBtn').disabled = true;
            } else {
                controller = grid.mount('#grid', {
                    columns: [
                        { key: 'code', label: 'Código', cls: 'code' },
                        { key: 'name', label: 'Cuenta' },
                        { key: 'book', label: 'En libros', align: 'right', type: 'money' },
                        { key: 'counted', label: 'Contado', align: 'right', editable: true },
                        { key: 'difference', label: 'Diferencia', align: 'right', type: 'money' },
                        { key: '_count', label: '', align: 'right', type: 'action', actionLabel: 'Contar efectivo' },
                    ],
                    rows: r.rows,
                    totals: ['book', 'counted', 'difference'],
                    recompute,
                    onAction: (row, i) => openDenom(row, i),
                });
                document.getElementById('saveBtn').disabled = !r.configured;
            }
            renderRecent(r.recent);
        }

        function renderRecent(recent) {
            const wrap = document.getElementById('recentWrap');
            if (!recent || !recent.length) { wrap.hidden = true; return; }
            wrap.hidden = false;
            document.getElementById('recent').innerHTML = recent.map(c => `
                <div class="recent-item">
                    <span>${c.count_date}${c.note ? ' · ' + escapeHtml(c.note) : ''}</span>
                    <span class="num ${format.signClass(c.total_difference)}">${format.money(c.total_difference)}${c.journal_entry_id ? ` · póliza #${c.journal_entry_id}` : ' · sin ajuste'}</span>
                </div>`).join('');
        }

        function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

        // ---- MXN denomination counter -----------------------------------------
        const DENOMS = [
            { value: 1000, label: '$1,000' }, { value: 500, label: '$500' }, { value: 200, label: '$200' },
            { value: 100, label: '$100' }, { value: 50, label: '$50' }, { value: 20, label: '$20' },
            { value: 10, label: '$10' }, { value: 5, label: '$5' }, { value: 2, label: '$2' },
            { value: 1, label: '$1' }, { value: 0.5, label: '50¢' },
        ];
        let denomCtxAccount = null, denomCtxIndex = null;

        function openDenom(row, i) {
            denomCtxAccount = row.account_id;
            denomCtxIndex = i;
            document.getElementById('denomAccount').textContent = `${row.code} · ${row.name}`;
            const existing = denomState[row.account_id]?.map || {};
            document.getElementById('denomRows').innerHTML = DENOMS.map(d => `
                <tr>
                    <td>${d.label}</td>
                    <td class="amount"><input class="form-control grid-input num denom-qty" type="number" min="0" step="1" inputmode="numeric" data-value="${d.value}" value="${existing[d.value] || ''}"></td>
                    <td class="amount num denom-sub">${format.money((existing[d.value] || 0) * d.value)}</td>
                </tr>`).join('');
            document.querySelectorAll('#denomRows .denom-qty').forEach(inp => inp.addEventListener('input', recomputeDenom));
            recomputeDenom();
            modal.open('#denomModal');
        }

        function recomputeDenom() {
            let total = 0;
            document.querySelectorAll('#denomRows tr').forEach(tr => {
                const inp = tr.querySelector('.denom-qty');
                if (!inp) return;
                const val = Number(inp.dataset.value), qty = parseInt(inp.value) || 0;
                tr.querySelector('.denom-sub').textContent = format.money(val * qty);
                total += val * qty;
            });
            document.getElementById('denomTotal').textContent = format.money(total);
        }

        function applyDenom() {
            const map = {}, list = [];
            document.querySelectorAll('#denomRows .denom-qty').forEach(inp => {
                const value = Number(inp.dataset.value), qty = parseInt(inp.value) || 0;
                if (qty > 0) { map[value] = qty; list.push({ value, qty }); }
            });
            const total = list.reduce((a, x) => a + x.value * x.qty, 0);
            denomState[denomCtxAccount] = { map, list, total };
            if (denomCtxIndex !== null && controller) controller.setValue(denomCtxIndex, 'counted', total);
            modal.close('#denomModal');
        }

        async function save() {
            if (!controller) return;
            const date = document.getElementById('date').value;
            const note = document.getElementById('note').value || null;
            const counts = controller.rows().map(r => {
                const d = denomState[r.account_id];
                return (d && d.list.length)
                    ? { account_id: r.account_id, counted: d.total, denominations: d.list }
                    : { account_id: r.account_id, counted: parseFloat(r.counted) || 0 };
            });
            const btn = document.getElementById('saveBtn');
            await loading.withLoading(btn, async () => {
                try {
                    const r = await http.post('/cash-count', { date, note, counts });
                    notify.success(r.posted ? `Arqueo guardado. Póliza de ajuste #${r.entry_id}.` : 'Arqueo guardado (sin diferencias).');
                    document.getElementById('note').value = '';
                    await run();
                } catch (e) {
                    const msg = e.body?.errors ? Object.values(e.body.errors)[0][0] : e.message;
                    notify.error(msg);
                }
            });
        }

        document.getElementById('settingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.currentTarget.querySelector('button[type="submit"]');
            const id = document.getElementById('diffAccount').value;
            if (!id) { notify.error('Selecciona la cuenta de diferencia.'); return; }
            await loading.withLoading(btn, async () => {
                try {
                    await http.put('/cash-count/settings', { difference_account_id: Number(id) });
                    modal.close('#settingsModal');
                    notify.success('Configuración guardada.');
                    await run();
                } catch (e) {
                    const msg = e.body?.errors ? Object.values(e.body.errors)[0][0] : e.message;
                    notify.error(msg);
                }
            });
        });

        document.getElementById('genBtn').addEventListener('click', run);
        document.getElementById('saveBtn').addEventListener('click', save);
        document.getElementById('denomApply').addEventListener('click', applyDenom);
        document.getElementById('settingsBtn').addEventListener('click', () => modal.open('#settingsModal'));
        document.getElementById('warnConfig').addEventListener('click', () => modal.open('#settingsModal'));

        init();
    </script>
    @endpush
@endsection
