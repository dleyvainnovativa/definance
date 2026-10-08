@extends('layouts.app')

@section('title', 'Cierre de ejercicio · DeFinance')
@section('heading', 'Cierre de ejercicio')

@section('content')
    <style>
        .warn-bar { background: var(--c-neg-soft); color: var(--c-neg); border: 1px solid var(--c-neg); border-radius: var(--radius); padding: .7rem 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .yr-badge { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .76rem; font-weight: 600; }
        .yr-open   { background: var(--c-pos-soft); color: var(--c-pos); }
        .yr-closed { background: var(--c-surface-2, rgba(128,128,128,.14)); color: var(--c-text-muted); }
        #closeInfo { font-size: .9rem; color: var(--c-text-muted); }
        #closeInfo b { color: var(--c-text); }
    </style>

    <div class="page-head">
        <div>
            <h1>Cierre de ejercicio</h1>
            <p>Cierra un año: los ingresos y gastos se saldan contra la cuenta de resultado. Un ejercicio cerrado queda bloqueado.</p>
        </div>
        <button class="btn btn-ghost" id="settingsBtn" type="button"><i class="fa-solid fa-gear" aria-hidden="true"></i> Configurar</button>
    </div>

    <div class="warn-bar" id="warnBar" hidden>
        <span>Configura la cuenta de resultado (p. ej. 300.2) para poder cerrar un ejercicio.</span>
        <button class="btn btn-primary" id="warnConfig" type="button">Configurar</button>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="ledger">
                <thead>
                    <tr>
                        <th>Ejercicio</th>
                        <th class="amount">Resultado</th>
                        <th>Estado</th>
                        <th class="amount">Acción</th>
                    </tr>
                </thead>
                <tbody id="rows"><tr><td colspan="4" class="empty">Cargando…</td></tr></tbody>
            </table>
        </div>
    </div>

    {{-- Result-account settings modal --}}
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="settingsForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Configurar cierre</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="resultAccount">Cuenta de resultado (capital)</label>
                        <select class="form-select" id="resultAccount" required></select>
                        <p class="mt-2 mb-0" style="color:var(--c-text-muted);font-size:.8rem;">
                            La utilidad o pérdida del ejercicio se traslada a esta cuenta de capital al cerrar el año.
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

    {{-- Confirm-close modal --}}
    <div class="modal fade" id="closeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cerrar ejercicio <span id="closeYear"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p id="closeInfo"></p>
                    <p style="font-size:.84rem;color:var(--c-text-muted);margin:.6rem 0 0;">
                        Se registrará una póliza de cierre con fecha 31-dic y el ejercicio quedará bloqueado
                        (no se podrán registrar ni editar pólizas con esa fecha o anterior). Podrás reabrirlo después.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmClose" data-loading-text="Cerrando…">Cerrar ejercicio</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, modal, format, guard } = DF;
        let pendingYear = null;

        async function init() {
            if (!await guard.ensureAuth()) return;
            try {
                const acc = await http.get('/accounts?type=equity&postable=1');
                document.getElementById('resultAccount').innerHTML =
                    '<option value="">Selecciona…</option>' +
                    acc.data.map(a => `<option value="${a.id}">${a.code} · ${a.name}</option>`).join('');
            } catch (e) { notify.error(e.message); }
            await load();
        }

        async function load() {
            try {
                const r = await http.get('/closes');
                render(r);
            } catch (e) { notify.error(e.message); }
        }

        function render(r) {
            document.getElementById('warnBar').hidden = r.settings.configured;
            if (r.settings.result_account) {
                document.getElementById('resultAccount').value = r.settings.result_account.id;
            }
            const body = document.getElementById('rows');
            if (!r.years.length) {
                body.innerHTML = '<tr><td colspan="4" class="empty">Aún no hay pólizas registradas.</td></tr>';
                return;
            }
            body.innerHTML = r.years.map(y => {
                const badge = y.closed
                    ? '<span class="yr-badge yr-closed">Cerrado</span>'
                    : '<span class="yr-badge yr-open">Abierto</span>';
                let action = '<span style="color:var(--c-text-muted)">—</span>';
                if (y.closeable) action = `<button class="btn btn-sm btn-primary" data-close="${y.year}">Cerrar</button>`;
                else if (y.reopenable) action = `<button class="btn btn-sm btn-ghost" data-reopen="${y.year}">Reabrir</button>`;
                return `<tr>
                    <td class="code">${y.year}</td>
                    <td class="amount num ${format.signClass(y.net_result)}">${format.money(y.net_result)}</td>
                    <td>${badge}${y.closed && y.entry_id ? ` · póliza #${y.entry_id}` : ''}</td>
                    <td class="amount">${action}</td>
                </tr>`;
            }).join('');
            body.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => askClose(r, b.dataset.close)));
            body.querySelectorAll('[data-reopen]').forEach(b => b.addEventListener('click', () => reopen(b.dataset.reopen)));
        }

        function askClose(r, year) {
            if (!r.settings.configured) { modal.open('#settingsModal'); return; }
            pendingYear = Number(year);
            const row = r.years.find(y => y.year === pendingYear);
            document.getElementById('closeYear').textContent = year;
            const net = Number(row.net_result);
            document.getElementById('closeInfo').innerHTML =
                `Ingresos <b>${format.money(row.revenue)}</b> − Gastos <b>${format.money(row.expenses)}</b> = ` +
                `<b class="${format.signClass(net)}">${format.money(net)}</b> (${net >= 0 ? 'utilidad' : 'pérdida'}) ` +
                `→ ${r.settings.result_account.code} · ${r.settings.result_account.name}`;
            modal.open('#closeModal');
        }

        document.getElementById('confirmClose').addEventListener('click', async (e) => {
            await loading.withLoading(e.currentTarget, async () => {
                try {
                    await http.post('/closes', { year: pendingYear });
                    modal.close('#closeModal');
                    notify.success(`Ejercicio ${pendingYear} cerrado.`);
                    await load();
                } catch (err) {
                    notify.error(err.body?.message || (err.body?.errors ? Object.values(err.body.errors)[0][0] : err.message));
                }
            });
        });

        async function reopen(year) {
            if (!window.confirm(`¿Reabrir el ejercicio ${year}? Se cancelará su póliza de cierre.`)) return;
            try {
                await http.del(`/closes/${year}`);
                notify.success(`Ejercicio ${year} reabierto.`);
                await load();
            } catch (err) {
                notify.error(err.body?.message || err.message);
            }
        }

        document.getElementById('settingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('resultAccount').value;
            if (!id) { notify.error('Selecciona la cuenta de resultado.'); return; }
            await loading.withLoading(e.currentTarget.querySelector('button[type="submit"]'), async () => {
                try {
                    await http.put('/closes/settings', { result_account_id: Number(id) });
                    modal.close('#settingsModal');
                    notify.success('Configuración guardada.');
                    await load();
                } catch (err) {
                    notify.error(err.body?.errors ? Object.values(err.body.errors)[0][0] : err.message);
                }
            });
        });

        document.getElementById('settingsBtn').addEventListener('click', () => modal.open('#settingsModal'));
        document.getElementById('warnConfig').addEventListener('click', () => modal.open('#settingsModal'));

        init();
    </script>
    @endpush
@endsection
