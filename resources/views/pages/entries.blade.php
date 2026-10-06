@extends('layouts.app')

@section('title', 'Pólizas · DeFinance')
@section('heading', 'Pólizas')

@section('content')
    <style>
        .leg-head, .leg-row { grid-template-columns: 1fr 120px 120px 96px 34px; }
        .leg-row.auto .acct-fixed { display: flex; align-items: center; gap: .45rem; font-size: .85rem; color: var(--c-text-muted); padding: .35rem .2rem; }
        .leg-row.auto input { background: var(--c-surface-2); }
        .iva-tag { font-size: .68rem; font-weight: 600; color: var(--c-accent); white-space: nowrap; }
        @media (max-width: 640px) { .leg-head, .leg-row { grid-template-columns: 1fr 1fr; } .leg-row .acct, .leg-row .acct-fixed { grid-column: 1 / -1; } }
    </style>

    <div class="page-head">
        <div><h1>Pólizas</h1><p>Asientos contables. Cada póliza debe cuadrar: cargos = abonos.</p></div>
        <button class="btn btn-primary" id="newEntryBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nueva póliza
        </button>
    </div>

    <div class="toolbar">
        <div class="field"><label for="from">Desde</label><input class="form-control" id="from" type="date"></div>
        <div class="field"><label for="to">Hasta</label><input class="form-control" id="to" type="date"></div>
        <div class="field"><label for="status">Estado</label>
            <select class="form-select" id="status"><option value="">Todos</option><option value="posted">Contabilizado</option><option value="void">Cancelado</option></select>
        </div>
        <div class="field" style="flex:1 1 200px;"><label for="search">Buscar</label><input class="form-control" id="search" type="search" placeholder="Descripción o referencia"></div>
        <button class="btn btn-ghost" id="applyBtn">Filtrar</button>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="ledger">
                <thead><tr><th>Fecha</th><th>Descripción</th><th>Referencia</th><th>Estado</th><th class="amount">Importe</th><th class="amount">Acciones</th></tr></thead>
                <tbody id="rows"><tr><td colspan="6" class="empty">Cargando…</td></tr></tbody>
            </table>
        </div>
        <div class="pager"><span id="pagerInfo"></span><span class="btns"><button class="btn btn-ghost" id="prevBtn">Anterior</button><button class="btn btn-ghost" id="nextBtn">Siguiente</button></span></div>
    </div>

    <div class="modal fade" id="entryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="entryForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Nueva póliza</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-sm-4"><label class="form-label" for="entry_date">Fecha</label><input class="form-control" id="entry_date" type="date" required></div>
                            <div class="col-sm-4"><label class="form-label" for="reference">Referencia</label><input class="form-control" id="reference" placeholder="Opcional"></div>
                            <div class="col-sm-4"><label class="form-label" for="description">Descripción</label><input class="form-control" id="description" placeholder="Opcional"></div>
                        </div>

                        <div class="leg-head"><span>Cuenta</span><span class="amount">Cargo</span><span class="amount">Abono</span><span>IVA</span><span></span></div>
                        <div class="entry-legs" id="legs"></div>
                        <button type="button" class="btn btn-ghost mt-2" id="addLegBtn">+ Agregar línea</button>
                        <p class="mt-2 mb-0" id="ivaNote" style="color:var(--c-text-muted);font-size:.8rem;" hidden>
                            Configura las cuentas de IVA (118 Acreditable y 213 Trasladado) para calcular el IVA automáticamente.
                        </p>

                        <div class="totbar">
                            <div class="tot"><span class="k">Cargos</span><span class="v num" id="totDebit">$0.00</span></div>
                            <div class="tot"><span class="k">Abonos</span><span class="v num" id="totCredit">$0.00</span></div>
                            <div class="tot diff"><span class="k">Diferencia</span><span class="v num bad" id="totDiff">$0.00</span></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="saveEntryBtn" data-loading-text="Guardando…" disabled>Guardar póliza</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, modal, format, guard } = DF;
        let optionsHtml = '';
        let tax = { rates: {}, accounts: { acreditable: null, trasladado: null } };
        let page = 1, lastPage = 1;

        async function init() {
            if (!await guard.ensureAuth()) return;
            try {
                const [acc, taxes] = await Promise.all([http.get('/accounts?postable=1'), http.get('/taxes')]);
                optionsHtml = '<option value="">Selecciona…</option>' +
                    acc.data.filter(a => a.is_postable).map(a => `<option value="${a.id}">${a.code} · ${a.name}</option>`).join('');
                tax = taxes;
            } catch (e) { notify.error(e.message); }

            document.getElementById('applyBtn').addEventListener('click', () => { page = 1; loadEntries(); });
            document.getElementById('prevBtn').addEventListener('click', () => { if (page > 1) { page--; loadEntries(); } });
            document.getElementById('nextBtn').addEventListener('click', () => { if (page < lastPage) { page++; loadEntries(); } });
            document.getElementById('newEntryBtn').addEventListener('click', openNew);
            document.getElementById('addLegBtn').addEventListener('click', () => { addLeg(); });
            document.getElementById('entryForm').addEventListener('submit', save);
            loadEntries();
        }

        async function loadEntries() {
            const p = new URLSearchParams({ per_page: '15', page });
            ['from', 'to', 'status', 'search'].forEach(id => { const v = document.getElementById(id).value; if (v) p.set(id, v); });
            try {
                const res = await http.get('/entries?' + p.toString());
                lastPage = res.meta.last_page;
                renderRows(res.data);
                document.getElementById('pagerInfo').textContent = `Página ${res.meta.current_page} de ${res.meta.last_page} · ${res.meta.total} pólizas`;
            } catch (e) { notify.error(e.message); }
        }

        function renderRows(rows) {
            const body = document.getElementById('rows');
            if (!rows.length) { body.innerHTML = '<tr><td colspan="6" class="empty">Sin pólizas en este rango.</td></tr>'; return; }
            const STATUS = { posted: 'Contabilizado', void: 'Cancelado', draft: 'Borrador' };
            body.innerHTML = rows.map(e => `
                <tr>
                    <td class="num code">${format.date(e.entry_date)}</td>
                    <td>${e.description ?? ''}</td>
                    <td class="code">${e.reference ?? ''}</td>
                    <td><span class="status ${e.status}">${STATUS[e.status] ?? e.status}</span></td>
                    <td class="amount num">${format.money(e.totals?.debit ?? 0)}</td>
                    <td class="amount"><span class="row-actions">
                        <a class="btn-icon" href="/voucher?id=${e.id}" title="Ver comprobante">🧾</a>
                        ${e.status === 'posted' ? `<button class="btn-icon danger" data-void="${e.id}" title="Cancelar póliza">⊘</button>` : ''}
                    </span></td>
                </tr>`).join('');
            body.querySelectorAll('[data-void]').forEach(b => b.addEventListener('click', () => voidEntry(b.dataset.void)));
        }

        async function voidEntry(id) {
            if (!window.confirm('¿Cancelar esta póliza? Se registrará una póliza de reversa.')) return;
            try { await http.post(`/entries/${id}/void`); notify.success('Póliza cancelada (reversada).'); loadEntries(); }
            catch (e) { notify.error(e.message); }
        }

        // ---- entry form ----
        function ivaOptions() {
            return '<option value="">—</option>' +
                Object.keys(tax.rates).map(code => `<option value="${code}">IVA ${code}%</option>`).join('');
        }

        function openNew() {
            document.getElementById('entryForm').reset();
            document.getElementById('entry_date').value = new Date().toISOString().slice(0, 10);
            document.getElementById('legs').innerHTML = '';
            document.getElementById('ivaNote').hidden = Boolean(tax.accounts.acreditable && tax.accounts.trasladado);
            addLeg(); addLeg();
            syncIva();
            modal.open('#entryModal');
        }

        function addLeg() {
            const row = document.createElement('div');
            row.className = 'leg-row';
            row.innerHTML = `
                <span class="acct"><select class="form-select leg-account">${optionsHtml}</select></span>
                <span class="amount"><input class="form-control leg-debit num" type="number" step="0.01" min="0" placeholder="0.00"></span>
                <span class="amount"><input class="form-control leg-credit num" type="number" step="0.01" min="0" placeholder="0.00"></span>
                <span><select class="form-select leg-iva">${ivaOptions()}</select></span>
                <button type="button" class="btn-icon leg-remove" title="Quitar">✕</button>`;
            const debit = row.querySelector('.leg-debit');
            const credit = row.querySelector('.leg-credit');
            debit.addEventListener('input', () => { if (debit.value) credit.value = ''; syncIva(); });
            credit.addEventListener('input', () => { if (credit.value) debit.value = ''; syncIva(); });
            row.querySelector('.leg-iva').addEventListener('change', syncIva);
            row.querySelector('.leg-remove').addEventListener('click', () => { row.remove(); syncIva(); });
            document.getElementById('legs').appendChild(row);
        }

        function appendAuto(acct, amount, isDebit, code, rate, base) {
            const row = document.createElement('div');
            row.className = 'leg-row auto';
            row.dataset.accountId = acct.id;
            row.dataset.taxCode = 'IVA' + code;
            row.dataset.taxRate = rate;
            row.dataset.taxBase = base;
            row.innerHTML = `
                <span class="acct-fixed">${acct.code} · ${acct.name} <span class="iva-tag">IVA ${code}%</span></span>
                <span class="amount"><input class="form-control leg-debit num" value="${isDebit ? amount.toFixed(2) : ''}" readonly></span>
                <span class="amount"><input class="form-control leg-credit num" value="${isDebit ? '' : amount.toFixed(2)}" readonly></span>
                <span></span><span></span>`;
            document.getElementById('legs').appendChild(row);
        }

        function syncIva() {
            document.querySelectorAll('#legs .leg-row.auto').forEach(r => r.remove());
            const canIva = tax.accounts.acreditable && tax.accounts.trasladado;
            if (canIva) {
                document.querySelectorAll('#legs .leg-row:not(.auto)').forEach(base => {
                    const code = base.querySelector('.leg-iva')?.value;
                    const rate = code ? tax.rates[code] : 0;
                    if (!(rate > 0)) return;
                    const dv = parseFloat(base.querySelector('.leg-debit').value) || 0;
                    const cv = parseFloat(base.querySelector('.leg-credit').value) || 0;
                    const amount = dv || cv;
                    if (!(amount > 0)) return;
                    const iva = Math.round(amount * rate * 100) / 100;
                    const isDebit = dv > 0;
                    appendAuto(isDebit ? tax.accounts.acreditable : tax.accounts.trasladado, iva, isDebit, code, rate, amount);
                });
            }
            recompute();
        }

        function recompute() {
            let d = 0, c = 0, legs = 0, valid = true;
            document.querySelectorAll('#legs .leg-row').forEach(r => {
                const isAuto = r.classList.contains('auto');
                const acc = isAuto ? r.dataset.accountId : r.querySelector('.leg-account').value;
                const dv = parseFloat(r.querySelector('.leg-debit').value) || 0;
                const cv = parseFloat(r.querySelector('.leg-credit').value) || 0;
                d += dv; c += cv; legs++;
                if (!acc || (dv > 0) === (cv > 0)) valid = false;
            });
            const diff = Math.round((d - c) * 100) / 100;
            document.getElementById('totDebit').textContent = format.money(d);
            document.getElementById('totCredit').textContent = format.money(c);
            const diffEl = document.getElementById('totDiff');
            diffEl.textContent = format.money(diff);
            diffEl.className = 'v num ' + (diff === 0 ? 'ok' : 'bad');
            document.getElementById('saveEntryBtn').disabled = !(valid && legs >= 2 && diff === 0 && d > 0);
        }

        async function save(e) {
            e.preventDefault();
            const legs = [];
            document.querySelectorAll('#legs .leg-row').forEach(r => {
                const isAuto = r.classList.contains('auto');
                const account_id = isAuto ? r.dataset.accountId : r.querySelector('.leg-account').value;
                const dv = parseFloat(r.querySelector('.leg-debit').value) || 0;
                const cv = parseFloat(r.querySelector('.leg-credit').value) || 0;
                if (!account_id || (dv <= 0 && cv <= 0)) return;
                const leg = dv > 0 ? { account_id, debit: dv } : { account_id, credit: cv };
                if (isAuto) { leg.tax_code = r.dataset.taxCode; leg.tax_rate = Number(r.dataset.taxRate); leg.tax_base = Number(r.dataset.taxBase); }
                legs.push(leg);
            });
            const payload = {
                entry_date: document.getElementById('entry_date').value,
                reference: document.getElementById('reference').value || null,
                description: document.getElementById('description').value || null,
                legs,
            };
            const btn = document.getElementById('saveEntryBtn');
            await loading.withLoading(btn, async () => {
                try {
                    await http.post('/entries', payload);
                    modal.close('#entryModal');
                    notify.success('Póliza registrada.');
                    page = 1; loadEntries();
                } catch (err) {
                    const msg = err.body?.errors ? Object.values(err.body.errors)[0][0] : err.message;
                    notify.error(msg);
                }
            });
        }

        init();
    </script>
    @endpush
@endsection
