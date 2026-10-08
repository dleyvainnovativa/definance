@extends('layouts.app')

@section('title', 'Pólizas · DeFinance')
@section('heading', 'Pólizas')

@section('content')
    <style>
        .offcanvas { background: var(--c-surface); color: var(--c-text); }
    </style>

    <div class="page-head">
        <div><h1>Pólizas</h1><p>Asientos contables. Cada póliza debe cuadrar: cargos = abonos.</p></div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-ghost" id="chartBtn" disabled><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Ver gráficas</button>
            <button class="btn btn-primary" id="newEntryBtn"><i class="fa-solid fa-plus" aria-hidden="true"></i> Nuevo movimiento</button>
        </div>
    </div>

    <div class="toolbar">
        <div class="field">
            <label>Periodo</label>
            <div class="pf-tabs" role="tablist">
                <button type="button" class="pf-tab active" data-mode="month">Mes</button>
                <button type="button" class="pf-tab" data-mode="range">Rango</button>
            </div>
        </div>
        <div class="field pf-f-month"><label for="pfMonth">Mes</label><select class="form-select" id="pfMonth"></select></div>
        <div class="field pf-f-year"><label for="pfYear">Año</label><select class="form-select" id="pfYear"></select></div>
        <div class="field pf-f-from" hidden><label for="pfFrom">Desde</label><input class="form-control" type="date" id="pfFrom"></div>
        <div class="field pf-f-to" hidden><label for="pfTo">Hasta</label><input class="form-control" type="date" id="pfTo"></div>
        <input type="hidden" id="from"><input type="hidden" id="to">
        <div class="field"><label for="status">Estado</label>
            <select class="form-select" id="status"><option value="">Todos</option><option value="posted">Contabilizado</option><option value="draft">Borrador</option><option value="void">Cancelado</option></select>
        </div>
        <div class="field" style="flex:1 1 200px;"><label for="search">Buscar</label><input class="form-control" id="search" type="search" placeholder="Descripción o referencia"></div>
        <button class="btn btn-ghost" id="advBtn" data-bs-toggle="offcanvas" data-bs-target="#advCanvas">Avanzado</button>
        <button class="btn btn-ghost" id="applyBtn">Filtrar</button>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="ledger ledger--cards">
                <thead><tr><th>Fecha</th><th>Descripción</th><th>Cuentas</th><th>Referencia</th><th>Estado</th><th class="amount">Importe</th><th class="amount">Acciones</th></tr></thead>
                <tbody id="rows"><tr><td colspan="7" class="empty">Cargando…</td></tr></tbody>
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

    {{-- Edit metadata of a POSTED entry (date / reference / description only). --}}
    <div class="modal fade" id="metaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="metaForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar datos de la póliza</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p style="color:var(--c-text-muted);font-size:.84rem;margin:0 0 1rem;">
                            Sólo se puede cambiar la fecha, la referencia y la descripción. Los importes de una póliza
                            contabilizada no se editan: para corregir montos, cancela la póliza y vuelve a registrarla.
                        </p>
                        <div class="row g-3">
                            <div class="col-sm-5"><label class="form-label" for="meta_date">Fecha</label><input class="form-control" id="meta_date" type="date" required></div>
                            <div class="col-sm-7"><label class="form-label" for="meta_reference">Referencia</label><input class="form-control" id="meta_reference" placeholder="Opcional"></div>
                            <div class="col-12"><label class="form-label" for="meta_description">Descripción</label><input class="form-control" id="meta_description" placeholder="Opcional"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="saveMetaBtn" data-loading-text="Guardando…">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, modal, format, guard, select, charts, chartModal } = DF;
        let metaId = null;

        function wireChart(title, views) {
            const btn = document.getElementById('chartBtn');
            btn.disabled = !views.length;
            btn.onclick = views.length ? () => chartModal.open({ title, views }) : null;
        }

        function buildChart(rows) {
            const byAcct = {};
            rows.forEach(e => (e.lines || []).forEach(l => {
                const d = Number(l.debit) || 0;
                if (d > 0) { const k = `${l.account_code} · ${l.account_name}`; byAcct[k] = (byAcct[k] || 0) + d; }
            }));
            const acctBars = Object.entries(byAcct).map(([name, value]) => ({ name, value }))
                .sort((a, b) => b.value - a.value).slice(0, 10);
            const polizaBars = rows.map(e => ({ name: `${format.date(e.entry_date)} · ${(e.description || '').slice(0, 18)}`, value: Number(e.totals?.debit || 0) }))
                .filter(x => x.value > 0).slice(0, 12);
            wireChart('Pólizas · página actual', acctBars.length ? [
                { label: 'Importe por cuenta (cargo)', render: (el) => charts.bars(el, { rows: acctBars }) },
                { label: 'Importe por póliza', render: (el) => charts.bars(el, { rows: polizaBars }) },
            ] : []);
        }
        let page = 1, lastPage = 1;
        let advFilters = { debit_account_id: '', credit_account_id: '' };

        // Period filter — month/year default (current month), toggle to custom range.
        // Drives hidden #from/#to that loadEntries reads. Exposes window.__pfCompute().
        function setupPeriod() {
            const now = new Date();
            const Y = now.getFullYear(), M = now.getMonth() + 1;
            const names = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            const monthSel = document.getElementById('pfMonth');
            const yearSel = document.getElementById('pfYear');
            const pad = (n) => String(n).padStart(2, '0');
            const lastDay = (y, m) => new Date(y, m, 0).getDate(); // m: 1-12

            monthSel.innerHTML = names.map((n, i) => `<option value="${i + 1}">${n}</option>`).join('') +
                '<option value="0">Todo el año</option>';
            monthSel.value = String(M);
            const years = [];
            for (let y = Y + 1; y >= Y - 6; y--) years.push(y);
            yearSel.innerHTML = years.map((y) => `<option value="${y}">${y}</option>`).join('');
            yearSel.value = String(Y);

            const pfFrom = document.getElementById('pfFrom');
            const pfTo = document.getElementById('pfTo');
            pfFrom.value = `${Y}-${pad(M)}-01`;
            pfTo.value = now.toISOString().slice(0, 10);

            const hFrom = document.getElementById('from');
            const hTo = document.getElementById('to');
            let mode = 'month';

            function compute() {
                if (mode === 'month') {
                    const m = Number(monthSel.value), y = Number(yearSel.value);
                    if (m === 0) { hFrom.value = `${y}-01-01`; hTo.value = `${y}-12-31`; }
                    else { hFrom.value = `${y}-${pad(m)}-01`; hTo.value = `${y}-${pad(m)}-${pad(lastDay(y, m))}`; }
                } else {
                    hFrom.value = pfFrom.value; hTo.value = pfTo.value;
                }
            }
            function setMode(m) {
                mode = m;
                document.querySelectorAll('.toolbar .pf-tab').forEach((t) => t.classList.toggle('active', t.dataset.mode === m));
                const month = m === 'month';
                document.querySelector('.pf-f-month').hidden = !month;
                document.querySelector('.pf-f-year').hidden = !month;
                document.querySelector('.pf-f-from').hidden = month;
                document.querySelector('.pf-f-to').hidden = month;
            }
            document.querySelectorAll('.toolbar .pf-tab').forEach((t) => t.addEventListener('click', () => setMode(t.dataset.mode)));
            window.__pfCompute = compute;
            setMode('month');
            compute();
        }

        async function init() {
            if (!await guard.ensureAuth()) return;
            setupPeriod();
            try {
                const acc = await http.get('/accounts?postable=1');
                const opts = '<option value="">Cualquiera</option>' + acc.data.map(a => `<option value="${a.id}">${a.code} · ${a.name}</option>`).join('');
                document.getElementById('fDebit').innerHTML = opts;
                document.getElementById('fCredit').innerHTML = opts;
                select.mount('#fDebit'); select.mount('#fCredit');
            } catch (e) { notify.error(e.message); }

            document.getElementById('applyBtn').addEventListener('click', () => { page = 1; loadEntries(); });
            document.getElementById('prevBtn').addEventListener('click', () => { if (page > 1) { page--; loadEntries(); } });
            document.getElementById('nextBtn').addEventListener('click', () => { if (page < lastPage) { page++; loadEntries(); } });
            document.getElementById('newEntryBtn').addEventListener('click', () => window.DFEntry?.open());
            document.getElementById('advApply').addEventListener('click', () => {
                advFilters.debit_account_id = document.getElementById('fDebit').value;
                advFilters.credit_account_id = document.getElementById('fCredit').value;
                page = 1; loadEntries();
            });
            document.getElementById('advClear').addEventListener('click', () => {
                select.setValue('#fDebit', ''); select.setValue('#fCredit', '');
                advFilters = { debit_account_id: '', credit_account_id: '' };
                page = 1; loadEntries();
            });
            document.getElementById('metaForm').addEventListener('submit', (e) => { e.preventDefault(); saveMeta(); });
            // Reload the list whenever the shared entry modal saves something.
            document.addEventListener('df:entry-saved', () => { page = 1; loadEntries(); });
            loadEntries();
        }

        async function loadEntries() {
            window.__pfCompute?.();
            const p = new URLSearchParams({ per_page: '15', page });
            ['from', 'to', 'status', 'search'].forEach(id => { const v = document.getElementById(id).value; if (v) p.set(id, v); });
            if (advFilters.debit_account_id) p.set('debit_account_id', advFilters.debit_account_id);
            if (advFilters.credit_account_id) p.set('credit_account_id', advFilters.credit_account_id);
            loading.skeleton('#rows', { rows: 6 });
            try {
                const res = await http.get('/entries?' + p.toString());
                lastPage = res.meta.last_page;
                renderRows(res.data);
                document.getElementById('pagerInfo').textContent = `Página ${res.meta.current_page} de ${res.meta.last_page} · ${res.meta.total} pólizas`;
            } catch (e) { notify.error(e.message); }
        }

        function accountsCell(e) {
            const lines = e.lines || [];
            const debits = lines.filter(l => Number(l.debit) > 0);
            const credits = lines.filter(l => Number(l.credit) > 0);
            if (lines.length === 2 && debits.length === 1 && credits.length === 1) {
                const title = `Cargo: ${debits[0].account_code} ${debits[0].account_name} · Abono: ${credits[0].account_code} ${credits[0].account_name}`;
                return `<span class="acct-flow" title="${title}"><span class="code">${debits[0].account_code}</span><i class="fa-solid fa-arrow-right-long"></i><span class="code">${credits[0].account_code}</span></span>`;
            }
            const title = lines.map(l => `${l.account_code} ${Number(l.debit) > 0 ? '(cargo)' : '(abono)'}`).join(' · ');
            return `<span class="code" title="${title}">${lines.length} cuentas</span>`;
        }

        function renderRows(rows) {
            const body = document.getElementById('rows');
            if (!rows.length) { body.innerHTML = '<tr><td colspan="7" class="empty">Sin pólizas en este rango.</td></tr>'; wireChart('', []); return; }
            buildChart(rows);
            const STATUS = { posted: 'Contabilizado', void: 'Cancelado', draft: 'Borrador' };
            body.innerHTML = rows.map(e => `
                <tr>
                    <td data-label="Fecha" class="num code">${format.date(e.entry_date)}</td>
                    <td data-label="Descripción">${e.description ?? ''}</td>
                    <td data-label="Cuentas">${accountsCell(e)}</td>
                    <td data-label="Referencia" class="code">${e.reference ?? ''}</td>
                    <td data-label="Estado"><span class="status ${e.status}">${STATUS[e.status] ?? e.status}</span></td>
                    <td data-label="Importe" class="amount num">${format.money(e.totals?.debit ?? 0)}</td>
                    <td class="amount"><span class="row-actions">
                        <a class="btn-icon" href="/voucher?id=${e.id}" title="Ver comprobante"><i class="fa-solid fa-receipt"></i></a>
                        ${e.status === 'draft' ? `<button class="btn-icon" data-edit="${e.id}" title="Editar borrador"><i class="fa-solid fa-pen"></i></button>
                            <button class="btn-icon" data-post="${e.id}" title="Contabilizar"><i class="fa-solid fa-check"></i></button>` : ''}
                        ${e.status === 'posted' ? `<button class="btn-icon" data-meta="${e.id}" title="Editar fecha / datos"><i class="fa-solid fa-pen"></i></button>
                            <button class="btn-icon danger" data-void="${e.id}" title="Cancelar póliza"><i class="fa-solid fa-ban"></i></button>` : ''}
                    </span></td>
                </tr>`).join('');
            body.querySelectorAll('[data-void]').forEach(b => b.addEventListener('click', () => voidEntry(b.dataset.void)));
            body.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => window.DFEntry?.open({ edit: rows.find(r => r.id == b.dataset.edit) })));
            body.querySelectorAll('[data-post]').forEach(b => b.addEventListener('click', () => postDraft(b.dataset.post)));
            body.querySelectorAll('[data-meta]').forEach(b => b.addEventListener('click', () => openMeta(rows.find(r => r.id == b.dataset.meta))));
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

        // ---- posted-entry metadata edit (date / reference / description only) ----
        function openMeta(e) {
            if (!e) return;
            metaId = e.id;
            document.getElementById('meta_date').value = e.entry_date;
            document.getElementById('meta_reference').value = e.reference ?? '';
            document.getElementById('meta_description').value = e.description ?? '';
            modal.open('#metaModal');
        }

        async function saveMeta() {
            const btn = document.getElementById('saveMetaBtn');
            await loading.withLoading(btn, async () => {
                try {
                    await http.patch(`/entries/${metaId}/meta`, {
                        entry_date: document.getElementById('meta_date').value,
                        reference: document.getElementById('meta_reference').value || null,
                        description: document.getElementById('meta_description').value || null,
                    });
                    modal.close('#metaModal');
                    notify.success('Póliza actualizada.');
                    loadEntries();
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
