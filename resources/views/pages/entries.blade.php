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
        .offcanvas { background: var(--c-surface); color: var(--c-text); }
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
            <select class="form-select" id="status"><option value="">Todos</option><option value="posted">Contabilizado</option><option value="draft">Borrador</option><option value="void">Cancelado</option></select>
        </div>
        <div class="field" style="flex:1 1 200px;"><label for="search">Buscar</label><input class="form-control" id="search" type="search" placeholder="Descripción o referencia"></div>
        <button class="btn btn-ghost" id="advBtn" data-bs-toggle="offcanvas" data-bs-target="#advCanvas">Avanzado</button>
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

    {{-- Advanced filters offcanvas --}}
    <div class="offcanvas offcanvas-end" id="advCanvas" tabindex="-1" aria-labelledby="advTitle">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="advTitle">Filtros avanzados</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <div class="field mb-3"><label for="fDebit">Cuenta cargada (debe)</label><select class="form-select" id="fDebit"></select></div>
            <div class="field mb-3"><label for="fCredit">Cuenta abonada (haber)</label><select class="form-select" id="fCredit"></select></div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" id="advApply" data-bs-dismiss="offcanvas">Aplicar</button>
                <button class="btn btn-ghost" id="advClear" data-bs-dismiss="offcanvas">Limpiar</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="entryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="entryForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="entryTitle">Nueva póliza</h5>
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
                        <button type="button" class="btn btn-ghost" id="draftBtn" data-loading-text="Guardando…" disabled>Guardar borrador</button>
                        <button type="submit" class="btn btn-primary" id="saveEntryBtn" data-loading-text="Guardando…" disabled>Guardar póliza</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, modal, format, guard, bootstrap } = DF;
        let optionsHtml = '';
        let tax = { rates: {}, accounts: { acreditable: null, trasladado: null } };
        let page = 1, lastPage = 1;
        let editingId = null;                       // draft being edited, or null (create)
        let advFilters = { debit_account_id: '', credit_account_id: '' };

        async function init() {
            if (!await guard.ensureAuth()) return;
            try {
                const [acc, taxes] = await Promise.all([http.get('/accounts?postable=1'), http.get('/taxes')]);
                optionsHtml = '<option value="">Selecciona…</option>' +
                    acc.data.filter(a => a.is_postable).map(a => `<option value="${a.id}">${a.code} · ${a.name}</option>`).join('');
                tax = taxes;
                document.getElementById('fDebit').innerHTML = '<option value="">Cualquiera</option>' + acc.data.map(a => `<option value="${a.id}">${a.code} · ${a.name}</option>`).join('');
                document.getElementById('fCredit').innerHTML = document.getElementById('fDebit').innerHTML;
            } catch (e) { notify.error(e.message); }

            document.getElementById('applyBtn').addEventListener('click', () => { page = 1; loadEntries(); });
            document.getElementById('prevBtn').addEventListener('click', () => { if (page > 1) { page--; loadEntries(); } });
            document.getElementById('nextBtn').addEventListener('click', () => { if (page < lastPage) { page++; loadEntries(); } });
            document.getElementById('newEntryBtn').addEventListener('click', openNew);
            document.getElementById('addLegBtn').addEventListener('click', () => { addLeg(); });
            document.getElementById('entryForm').addEventListener('submit', (e) => { e.preventDefault(); savePosted(); });
            document.getElementById('draftBtn').addEventListener('click', saveDraft);
            document.getElementById('advApply').addEventListener('click', () => {
                advFilters.debit_account_id = document.getElementById('fDebit').value;
                advFilters.credit_account_id = document.getElementById('fCredit').value;
                page = 1; loadEntries();
            });
            document.getElementById('advClear').addEventListener('click', () => {
                document.getElementById('fDebit').value = ''; document.getElementById('fCredit').value = '';
                advFilters = { debit_account_id: '', credit_account_id: '' };
                page = 1; loadEntries();
            });
            loadEntries();
        }

        async function loadEntries() {
            const p = new URLSearchParams({ per_page: '15', page });
            ['from', 'to', 'status', 'search'].forEach(id => { const v = document.getElementById(id).value; if (v) p.set(id, v); });
            if (advFilters.debit_account_id) p.set('debit_account_id', advFilters.debit_account_id);
            if (advFilters.credit_account_id) p.set('credit_account_id', advFilters.credit_account_id);
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
                        ${e.status === 'draft' ? `<button class="btn-icon" data-edit="${e.id}" title="Editar borrador">✎</button>
                            <button class="btn-icon" data-post="${e.id}" title="Contabilizar">✓</button>` : ''}
                        ${e.status === 'posted' ? `<button class="btn-icon danger" data-void="${e.id}" title="Cancelar póliza">⊘</button>` : ''}
                    </span></td>
                </tr>`).join('');
            body.querySelectorAll('[data-void]').forEach(b => b.addEventListener('click', () => voidEntry(b.dataset.void)));
            body.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openEdit(rows.find(r => r.id == b.dataset.edit))));
            body.querySelectorAll('[data-post]').forEach(b => b.addEventListener('click', () => postDraft(b.dataset.post)));
        }

        async function voidEntry(id) {
            if (!window.confirm('¿Cancelar esta póliza? Se registrará una póliza de reversa.')) return;
            try { await http.post(`/entries/${id}/void`); notify.success('Póliza cancelada (reversada).'); loadEntries(); }
            catch (e) { notify.error(e.message); }
        }

        async function postDraft(id) {
            if (!window.confirm('¿Contabilizar esta póliza? Dejará de ser un borrador.')) return;
            try { await http.post(`/entries/${id}/post`); notify.success('Póliza contabilizada.'); loadEntries(); }
            catch (e) { notify.error(e.message); }
        }

        // ---- entry form ----
        function ivaOptions() {
            return '<option value="">—</option>' +
                Object.keys(tax.rates).map(code => `<option value="${code}">IVA ${code}%</option>`).join('');
        }

        function resetForm() {
            document.getElementById('entryForm').reset();
            document.getElementById('legs').innerHTML = '';
            document.getElementById('ivaNote').hidden = Boolean(tax.accounts.acreditable && tax.accounts.trasladado);
        }

        function openNew() {
            editingId = null;
            resetForm();
            document.getElementById('entryTitle').textContent = 'Nueva póliza';
            document.getElementById('draftBtn').textContent = 'Guardar borrador';
            document.getElementById('saveEntryBtn').textContent = 'Guardar póliza';
            document.getElementById('entry_date').value = new Date().toISOString().slice(0, 10);
            addLeg(); addLeg();
            syncIva();
            modal.open('#entryModal');
        }

        function openEdit(e) {
            if (!e) return;
            editingId = e.id;
            resetForm();
            document.getElementById('entryTitle').textContent = `Editar borrador #${e.id}`;
            document.getElementById('draftBtn').textContent = 'Actualizar borrador';
            document.getElementById('saveEntryBtn').textContent = 'Contabilizar';
            document.getElementById('entry_date').value = e.entry_date;
            document.getElementById('reference').value = e.reference ?? '';
            document.getElementById('description').value = e.description ?? '';
            (e.lines || []).forEach(l => {
                addLeg();
                const row = document.getElementById('legs').lastElementChild;
                row.querySelector('.leg-account').value = l.account_id;
                if (l.debit) row.querySelector('.leg-debit').value = Number(l.debit);
                if (l.credit) row.querySelector('.leg-credit').value = Number(l.credit);
            });
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
            row.querySelector('.leg-account').addEventListener('change', recompute);
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
            const ok = valid && legs >= 2 && diff === 0 && d > 0;
            document.getElementById('saveEntryBtn').disabled = !ok;
            document.getElementById('draftBtn').disabled = !ok;
        }

        function buildPayload() {
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
            return {
                entry_date: document.getElementById('entry_date').value,
                reference: document.getElementById('reference').value || null,
                description: document.getElementById('description').value || null,
                legs,
            };
        }

        async function submit(btnId, fn, successMsg) {
            const btn = document.getElementById(btnId);
            await loading.withLoading(btn, async () => {
                try {
                    await fn();
                    modal.close('#entryModal');
                    notify.success(successMsg);
                    page = 1; loadEntries();
                } catch (err) {
                    const msg = err.body?.errors ? Object.values(err.body.errors)[0][0] : err.message;
                    notify.error(msg);
                }
            });
        }

        function saveDraft() {
            const payload = buildPayload();
            submit('draftBtn', async () => {
                if (editingId) await http.put(`/entries/${editingId}`, payload);
                else await http.post('/entries', { ...payload, status: 'draft' });
            }, editingId ? 'Borrador actualizado.' : 'Borrador guardado.');
        }

        function savePosted() {
            const payload = buildPayload();
            submit('saveEntryBtn', async () => {
                if (editingId) { await http.put(`/entries/${editingId}`, payload); await http.post(`/entries/${editingId}/post`); }
                else await http.post('/entries', { ...payload, status: 'posted' });
            }, 'Póliza contabilizada.');
        }

        init();
    </script>
    @endpush
@endsection
