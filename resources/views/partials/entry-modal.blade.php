{{--
    Shared entry modal — Simple "movimiento" (6 fields) by default, with an
    Avanzado toggle to the full N-leg/IVA póliza. Included app-wide so the
    topbar button and the Pólizas button both open it. Exposes:
        DFEntry.open()              → new movement (simple)
        DFEntry.open({edit: entry}) → edit a draft (advanced, pre-filled)
    and fires a document event `df:entry-saved` on success.
--}}
<div class="modal fade" id="dfEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="dfEntryForm">
                <div class="modal-header" style="gap:1rem; align-items:center; flex-wrap:wrap;">
                    <h5 class="modal-title" id="meTitle">Nuevo movimiento</h5>
                    <div class="mode-tabs" id="meModeTabs">
                        <button type="button" class="mode-tab active" data-mode="simple">Simple</button>
                        <button type="button" class="mode-tab" data-mode="advanced">Avanzado</button>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-1">
                        <div class="col-sm-4"><label class="form-label" for="me_date">Fecha</label><input class="form-control" id="me_date" type="date" required></div>
                    </div>

                    {{-- SIMPLE --}}
                    <div id="me_simple">
                        <div class="row g-3 mt-0">
                            <div class="col-sm-4"><label class="form-label" for="me_type">Tipo de movimiento</label>
                                <select class="form-select" id="me_type">
                                    <option value="egreso">Egreso</option>
                                    <option value="ingreso">Ingreso</option>
                                    <option value="traspaso">Traspaso</option>
                                </select>
                            </div>
                            <div class="col-sm-4"><label class="form-label" for="me_amount">Monto</label><input class="form-control num" id="me_amount" type="number" step="0.01" min="0" placeholder="0.00"></div>
                            <div class="col-sm-4"><label class="form-label" for="me_concept">Concepto</label><input class="form-control" id="me_concept" placeholder="Opcional"></div>
                            <div class="col-sm-6"><label class="form-label" id="me_cashLabel" for="me_cash">Cuenta que paga</label><select class="form-select" id="me_cash"></select></div>
                            <div class="col-sm-6"><label class="form-label" id="me_otherLabel" for="me_other">Categoría / motivo</label><select class="form-select" id="me_other"></select></div>
                        </div>
                        <p class="simple-hint" id="me_simpleHint"></p>
                    </div>

                    {{-- ADVANCED --}}
                    <div id="me_advanced" hidden>
                        <div class="row g-3 mt-0 mb-3">
                            <div class="col-sm-6"><label class="form-label" for="me_reference">Referencia</label><input class="form-control" id="me_reference" placeholder="Opcional"></div>
                            <div class="col-sm-6"><label class="form-label" for="me_description">Descripción</label><input class="form-control" id="me_description" placeholder="Opcional"></div>
                        </div>
                        <div class="leg-head"><span>Cuenta</span><span class="amount">Cargo</span><span class="amount">Abono</span><span>IVA</span><span></span></div>
                        <div class="entry-legs" id="me_legs"></div>
                        <button type="button" class="btn btn-ghost mt-2" id="me_addLeg">+ Agregar línea</button>
                        <p class="mt-2 mb-0" id="me_ivaNote" style="color:var(--c-text-muted);font-size:.8rem;" hidden>
                            Configura las cuentas de IVA (118 Acreditable y 213 Trasladado) para calcular el IVA automáticamente.
                        </p>
                        <div class="totbar">
                            <div class="tot"><span class="k">Cargos</span><span class="v num" id="me_totDebit">$0.00</span></div>
                            <div class="tot"><span class="k">Abonos</span><span class="v num" id="me_totCredit">$0.00</span></div>
                            <div class="tot diff"><span class="k">Diferencia</span><span class="v num bad" id="me_totDiff">$0.00</span></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-ghost" id="me_draftBtn" data-loading-text="Guardando…">Guardar borrador</button>
                    <button type="submit" class="btn btn-primary" id="me_saveBtn" data-loading-text="Guardando…">Guardar movimiento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="module">
    (function () {
        const root = document.getElementById('dfEntryModal');
        if (!root) return;
        const { http, notify, loading, modal, format, select, guard } = DF;

        let loaded = false;
        let accounts = [];
        let tax = { rates: {}, accounts: { acreditable: null, trasladado: null } };
        let optionsHtml = '';
        let mode = 'simple';
        let editingId = null;

        const $ = (id) => document.getElementById(id);
        const today = () => new Date().toISOString().slice(0, 10);

        async function ensureData() {
            if (loaded) return;
            const [acc, taxes] = await Promise.all([http.get('/accounts?postable=1'), http.get('/taxes')]);
            accounts = acc.data.filter(a => a.is_postable);
            tax = taxes;
            optionsHtml = '<option value="">Selecciona…</option>' +
                accounts.map(a => `<option value="${a.id}">${a.code} · ${a.name}</option>`).join('');
            loaded = true;
        }
        const asOpts = (list) => list.map(a => ({ value: a.id, label: `${a.code} · ${a.name}` }));
        const cashOpts = () => asOpts(accounts.filter(a => a.is_cash));
        const allOpts = () => asOpts(accounts);

        // ---------------------------------------------------------------- mode
        function setMode(m) {
            mode = m;
            document.querySelectorAll('#meModeTabs .mode-tab').forEach(t => t.classList.toggle('active', t.dataset.mode === m));
            $('me_simple').hidden = m !== 'simple';
            $('me_advanced').hidden = m === 'simple';
            $('me_saveBtn').textContent = m === 'simple' ? 'Guardar movimiento'
                : (editingId ? 'Contabilizar' : 'Guardar póliza');
            revalidate();
        }

        // -------------------------------------------------------------- simple
        const SIMPLE = {
            egreso:   { cash: 'Cuenta que paga',   other: 'Categoría / motivo',  otherCash: false, hint: 'Sale dinero de la cuenta que paga hacia la categoría/motivo.' },
            ingreso:  { cash: 'Cuenta que recibe', other: 'Origen (ingreso)',     otherCash: false, hint: 'Entra dinero a la cuenta que recibe desde el origen.' },
            traspaso: { cash: 'De la cuenta',      other: 'A la cuenta',          otherCash: true,  hint: 'Mueve dinero entre dos cuentas de efectivo o banco.' },
        };

        function refreshSimple() {
            const t = $('me_type').value;
            const cfg = SIMPLE[t];
            $('me_cashLabel').textContent = cfg.cash;
            $('me_otherLabel').textContent = cfg.other;
            $('me_simpleHint').textContent = cfg.hint;
            select.setOptions('#me_cash', cashOpts(), $('me_cash').value);
            select.setOptions('#me_other', cfg.otherCash ? cashOpts() : allOpts(), $('me_other').value);
            revalidate();
        }

        function simpleValid() {
            const amt = parseFloat($('me_amount').value) || 0;
            const cash = $('me_cash').value, other = $('me_other').value;
            return amt > 0 && cash && other && cash !== other;
        }

        function buildSimplePayload() {
            const t = $('me_type').value;
            const amt = parseFloat($('me_amount').value) || 0;
            const cash = $('me_cash').value, other = $('me_other').value;
            let legs;
            if (t === 'ingreso') legs = [{ account_id: cash, debit: amt }, { account_id: other, credit: amt }];
            else legs = [{ account_id: other, debit: amt }, { account_id: cash, credit: amt }]; // egreso & traspaso
            return { entry_date: $('me_date').value, reference: null, description: $('me_concept').value || null, legs };
        }

        // ------------------------------------------------------------ advanced
        function ivaOptions() {
            return '<option value="">—</option>' +
                Object.keys(tax.rates).map(code => `<option value="${code}">IVA ${code}%</option>`).join('');
        }
        function resetAdvanced() {
            $('me_legs').innerHTML = '';
            $('me_reference').value = ''; $('me_description').value = '';
            $('me_ivaNote').hidden = Boolean(tax.accounts.acreditable && tax.accounts.trasladado);
        }
        function addLeg() {
            const row = document.createElement('div');
            row.className = 'leg-row';
            row.innerHTML = `
                <span class="acct"><select class="form-select leg-account">${optionsHtml}</select></span>
                <span class="amount"><input class="form-control leg-debit num" type="number" step="0.01" min="0" placeholder="0.00"></span>
                <span class="amount"><input class="form-control leg-credit num" type="number" step="0.01" min="0" placeholder="0.00"></span>
                <span><select class="form-select leg-iva">${ivaOptions()}</select></span>
                <button type="button" class="btn-icon leg-remove" title="Quitar"><i class="fa-solid fa-xmark"></i></button>`;
            const debit = row.querySelector('.leg-debit'), credit = row.querySelector('.leg-credit');
            debit.addEventListener('input', () => { if (debit.value) credit.value = ''; syncIva(); });
            credit.addEventListener('input', () => { if (credit.value) debit.value = ''; syncIva(); });
            row.querySelector('.leg-account').addEventListener('change', recompute);
            row.querySelector('.leg-iva').addEventListener('change', syncIva);
            row.querySelector('.leg-remove').addEventListener('click', () => { row.remove(); syncIva(); });
            $('me_legs').appendChild(row);
            select.mount(row.querySelector('.leg-account'), { placeholder: 'Selecciona…' });
            return row;
        }
        function appendAuto(acct, amount, isDebit, code, rate, base) {
            const row = document.createElement('div');
            row.className = 'leg-row auto';
            row.dataset.accountId = acct.id; row.dataset.taxCode = 'IVA' + code; row.dataset.taxRate = rate; row.dataset.taxBase = base;
            row.innerHTML = `
                <span class="acct-fixed">${acct.code} · ${acct.name} <span class="iva-tag">IVA ${code}%</span></span>
                <span class="amount"><input class="form-control leg-debit num" value="${isDebit ? amount.toFixed(2) : ''}" readonly></span>
                <span class="amount"><input class="form-control leg-credit num" value="${isDebit ? '' : amount.toFixed(2)}" readonly></span>
                <span></span><span></span>`;
            $('me_legs').appendChild(row);
        }
        function syncIva() {
            document.querySelectorAll('#me_legs .leg-row.auto').forEach(r => r.remove());
            if (tax.accounts.acreditable && tax.accounts.trasladado) {
                document.querySelectorAll('#me_legs .leg-row:not(.auto)').forEach(base => {
                    const code = base.querySelector('.leg-iva')?.value;
                    const rate = code ? tax.rates[code] : 0;
                    if (!(rate > 0)) return;
                    const dv = parseFloat(base.querySelector('.leg-debit').value) || 0;
                    const cv = parseFloat(base.querySelector('.leg-credit').value) || 0;
                    const amount = dv || cv;
                    if (!(amount > 0)) return;
                    const iva = Math.round(amount * rate * 100) / 100;
                    appendAuto(dv > 0 ? tax.accounts.acreditable : tax.accounts.trasladado, iva, dv > 0, code, rate, amount);
                });
            }
            recompute();
        }
        function recompute() {
            let d = 0, c = 0, legs = 0, valid = true;
            document.querySelectorAll('#me_legs .leg-row').forEach(r => {
                const isAuto = r.classList.contains('auto');
                const acc = isAuto ? r.dataset.accountId : r.querySelector('.leg-account').value;
                const dv = parseFloat(r.querySelector('.leg-debit').value) || 0;
                const cv = parseFloat(r.querySelector('.leg-credit').value) || 0;
                d += dv; c += cv; legs++;
                if (!acc || (dv > 0) === (cv > 0)) valid = false;
            });
            const diff = Math.round((d - c) * 100) / 100;
            $('me_totDebit').textContent = format.money(d);
            $('me_totCredit').textContent = format.money(c);
            const diffEl = $('me_totDiff');
            diffEl.textContent = format.money(diff);
            diffEl.className = 'v num ' + (diff === 0 ? 'ok' : 'bad');
            window.__advOk = valid && legs >= 2 && diff === 0 && d > 0;
            revalidate();
        }
        function buildAdvancedPayload() {
            const legs = [];
            document.querySelectorAll('#me_legs .leg-row').forEach(r => {
                const isAuto = r.classList.contains('auto');
                const account_id = isAuto ? r.dataset.accountId : r.querySelector('.leg-account').value;
                const dv = parseFloat(r.querySelector('.leg-debit').value) || 0;
                const cv = parseFloat(r.querySelector('.leg-credit').value) || 0;
                if (!account_id || (dv <= 0 && cv <= 0)) return;
                const leg = dv > 0 ? { account_id, debit: dv } : { account_id, credit: cv };
                if (isAuto) { leg.tax_code = r.dataset.taxCode; leg.tax_rate = Number(r.dataset.taxRate); leg.tax_base = Number(r.dataset.taxBase); }
                legs.push(leg);
            });
            return { entry_date: $('me_date').value, reference: $('me_reference').value || null, description: $('me_description').value || null, legs };
        }
        function fillAdvanced(entry) {
            resetAdvanced();
            $('me_reference').value = entry.reference ?? '';
            $('me_description').value = entry.description ?? '';
            (entry.lines || []).forEach(l => {
                const row = addLeg();
                select.setValue(row.querySelector('.leg-account'), l.account_id);
                if (l.debit) row.querySelector('.leg-debit').value = Number(l.debit);
                if (l.credit) row.querySelector('.leg-credit').value = Number(l.credit);
            });
            syncIva();
        }

        // -------------------------------------------------------- shared save
        function revalidate() {
            const ok = mode === 'simple' ? simpleValid() : !!window.__advOk;
            $('me_saveBtn').disabled = !ok;
            $('me_draftBtn').disabled = !ok;
        }
        async function run(btnId, fn, okMsg) {
            await loading.withLoading($(btnId), async () => {
                try {
                    await fn();
                    modal.close('#dfEntryModal');
                    notify.success(okMsg);
                    document.dispatchEvent(new CustomEvent('df:entry-saved'));
                } catch (err) {
                    const msg = err.body?.errors ? Object.values(err.body.errors)[0][0] : err.message;
                    notify.error(msg);
                }
            });
        }
        function save(status) {
            const payload = mode === 'simple' ? buildSimplePayload() : buildAdvancedPayload();
            const btn = status === 'draft' ? 'me_draftBtn' : 'me_saveBtn';
            if (editingId) {
                run(btn, async () => {
                    await http.put(`/entries/${editingId}`, payload);
                    if (status === 'posted') await http.post(`/entries/${editingId}/post`);
                }, status === 'draft' ? 'Borrador actualizado.' : 'Póliza contabilizada.');
            } else {
                run(btn, () => http.post('/entries', { ...payload, status }),
                    status === 'draft' ? 'Borrador guardado.'
                        : (mode === 'simple' ? 'Movimiento registrado.' : 'Póliza contabilizada.'));
            }
        }

        // --------------------------------------------------------------- open
        async function open(opts = {}) {
            if (!await guard.ensureAuth()) return;
            try { await ensureData(); } catch (e) { notify.error(e.message); return; }

            editingId = opts.edit ? opts.edit.id : null;
            $('dfEntryForm').reset();
            resetAdvanced();
            $('me_date').value = opts.edit ? opts.edit.entry_date : today();
            window.__advOk = false;

            const editing = !!opts.edit;
            $('meModeTabs').hidden = editing;                 // drafts edit in advanced only
            $('meTitle').textContent = editing ? `Editar borrador #${opts.edit.id}` : 'Nuevo movimiento';

            if (editing) { setMode('advanced'); fillAdvanced(opts.edit); }
            else { $('me_type').value = 'egreso'; refreshSimple(); addLeg(); addLeg(); syncIva(); setMode('simple'); }

            modal.open('#dfEntryModal');
        }

        // wiring (markup is above this inline module, so elements exist)
        document.querySelectorAll('#meModeTabs .mode-tab').forEach(t => t.addEventListener('click', () => { if (!editingId) setMode(t.dataset.mode); }));
        $('me_type').addEventListener('change', refreshSimple);
        ['me_amount', 'me_concept'].forEach(id => $(id).addEventListener('input', revalidate));
        $('me_cash').addEventListener('change', revalidate);
        $('me_other').addEventListener('change', revalidate);
        $('me_addLeg').addEventListener('click', () => addLeg());
        $('me_draftBtn').addEventListener('click', () => save('draft'));
        $('dfEntryForm').addEventListener('submit', (e) => { e.preventDefault(); save('posted'); });

        // Any element with [data-quick-entry] (e.g. the topbar button) opens a new movement.
        document.querySelectorAll('[data-quick-entry]').forEach(b => b.addEventListener('click', () => open()));

        window.DFEntry = { open };
    })();
</script>
