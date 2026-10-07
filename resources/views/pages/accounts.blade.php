@extends('layouts.app')

@section('title', 'Catálogo de cuentas · DeFinance')
@section('heading', 'Catálogo de cuentas')

@section('content')
    <div class="page-head">
        <div>
            <h1>Catálogo de cuentas</h1>
            <p>Tu plan de cuentas. Sólo las cuentas de detalle reciben movimientos.</p>
        </div>
        <button class="btn btn-primary" id="newAccountBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nueva cuenta
        </button>
    </div>

    <div class="toolbar">
        <div class="field">
            <label for="search">Buscar</label>
            <input class="form-control" id="search" type="search" placeholder="Código o nombre">
        </div>
        <div class="field">
            <label for="typeFilter">Tipo</label>
            <select class="form-select" id="typeFilter">
                <option value="">Todos</option>
                <option value="asset">Activo</option>
                <option value="liability">Pasivo</option>
                <option value="equity">Capital</option>
                <option value="income">Ingresos</option>
                <option value="expense">Gastos</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="ledger ledger--cards">
                <thead>
                    <tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Naturaleza</th><th>Detalle</th><th class="amount">Acciones</th></tr>
                </thead>
                <tbody id="rows"><tr><td colspan="6" class="empty">Cargando…</td></tr></tbody>
            </table>
        </div>
    </div>

    {{-- Modal --}}
    <div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="accountForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="accountModalTitle">Nueva cuenta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="accountId">
                        <div class="row g-3">
                            <div class="col-4"><label class="form-label" for="code">Código</label><input class="form-control" id="code" name="code" required></div>
                            <div class="col-8"><label class="form-label" for="name">Nombre</label><input class="form-control" id="name" name="name" required></div>
                            <div class="col-6"><label class="form-label" for="type">Tipo</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="asset">Activo</option>
                                    <option value="liability">Pasivo</option>
                                    <option value="equity">Capital</option>
                                    <option value="income">Ingresos</option>
                                    <option value="expense">Gastos</option>
                                </select>
                            </div>
                            <div class="col-6"><label class="form-label" for="parent_id">Cuenta padre</label>
                                <select class="form-select" id="parent_id" name="parent_id"><option value="">— Ninguna —</option></select>
                            </div>
                            <div class="col-12 d-flex gap-4">
                                <label class="d-flex align-items-center gap-2"><input type="checkbox" id="is_postable" checked> Cuenta de detalle (recibe movimientos)</label>
                                <label class="d-flex align-items-center gap-2"><input type="checkbox" id="is_active" checked> Activa</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" data-loading-text="Guardando…">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, modal, guard, select } = DF;
        const TYPE_LABEL = { asset:'Activo', liability:'Pasivo', equity:'Capital', income:'Ingresos', expense:'Gastos' };
        let accounts = [];

        async function load() {
            if (!await guard.ensureAuth()) return;
            await refresh();
            document.getElementById('search').addEventListener('input', render);
            document.getElementById('typeFilter').addEventListener('change', render);
            // Auto-assign the next child code when a parent is chosen (create only).
            document.getElementById('parent_id').addEventListener('change', suggestCode);
        }

        async function refresh() {
            loading.skeleton('#rows', { rows: 6 });
            try {
                const res = await http.get('/accounts');
                accounts = res.data;
                fillParents();
                render();
            } catch (e) { notify.error(e.message); }
        }

        function fillParents() {
            const opts = [{ value: '', label: '— Ninguna —' }]
                .concat(accounts.map(a => ({ value: a.id, label: `${a.code} · ${a.name}` })));
            select.setOptions('#parent_id', opts);
        }

        async function suggestCode() {
            const parentId = document.getElementById('parent_id').value;
            const isNew = !document.getElementById('accountId').value;
            if (!isNew || !parentId) return;
            try {
                const r = await http.get(`/accounts/next-code?parent_id=${parentId}`);
                if (r.next_code) document.getElementById('code').value = r.next_code;
            } catch (e) { /* leave the code field as-is */ }
        }

        function render() {
            const q = document.getElementById('search').value.toLowerCase();
            const type = document.getElementById('typeFilter').value;
            const list = accounts.filter(a =>
                (!type || a.type === type) &&
                (!q || a.code.toLowerCase().includes(q) || a.name.toLowerCase().includes(q)));

            const body = document.getElementById('rows');
            if (!list.length) { body.innerHTML = '<tr><td colspan="6" class="empty">Sin cuentas que coincidan.</td></tr>'; return; }
            body.innerHTML = list.map(a => `
                <tr>
                    <td data-label="Código" class="num code">${a.code}</td>
                    <td data-label="Nombre">${a.name}</td>
                    <td data-label="Tipo">${TYPE_LABEL[a.type]}</td>
                    <td data-label="Naturaleza"><span class="badge-nature ${a.normal_balance}">${a.nature_label}</span></td>
                    <td data-label="Detalle">${a.is_postable ? 'Detalle' : 'Grupo'}</td>
                    <td class="amount">
                        <span class="row-actions">
                            <button class="btn-icon" data-edit="${a.id}" title="Editar" ${a.is_editable ? '' : 'disabled'}>✎</button>
                            <button class="btn-icon danger" data-del="${a.id}" title="Eliminar" ${a.is_deletable ? '' : 'disabled'}>🗑</button>
                        </span>
                    </td>
                </tr>`).join('');

            body.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openEdit(b.dataset.edit)));
            body.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => remove(b.dataset.del)));
        }

        function openNew() {
            document.getElementById('accountModalTitle').textContent = 'Nueva cuenta';
            document.getElementById('accountForm').reset();
            document.getElementById('accountId').value = '';
            document.getElementById('type').disabled = false;
            select.setValue('#parent_id', '');
            document.getElementById('is_postable').checked = true;
            document.getElementById('is_active').checked = true;
            modal.open('#accountModal');
        }

        function openEdit(id) {
            const a = accounts.find(x => x.id == id);
            if (!a) return;
            document.getElementById('accountModalTitle').textContent = 'Editar cuenta';
            document.getElementById('accountId').value = a.id;
            document.getElementById('code').value = a.code;
            document.getElementById('name').value = a.name;
            document.getElementById('type').value = a.type;
            document.getElementById('type').disabled = true; // immutable
            select.setValue('#parent_id', a.parent_id ?? '');
            document.getElementById('is_postable').checked = a.is_postable;
            document.getElementById('is_active').checked = a.is_active;
            modal.open('#accountModal');
        }

        async function remove(id) {
            const a = accounts.find(x => x.id == id);
            if (!window.confirm(`¿Eliminar la cuenta ${a.code} · ${a.name}?`)) return;
            try {
                await http.del(`/accounts/${id}`);
                notify.success('Cuenta eliminada.');
                await refresh();
            } catch (e) {
                notify.error(e.status === 409 ? 'La cuenta tiene movimientos; desactívala en lugar de eliminarla.' : e.message);
            }
        }

        document.getElementById('newAccountBtn').addEventListener('click', openNew);

        document.getElementById('accountForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.currentTarget.querySelector('button[type="submit"]');
            const id = document.getElementById('accountId').value;
            const payload = {
                code: document.getElementById('code').value,
                name: document.getElementById('name').value,
                type: document.getElementById('type').value,
                parent_id: document.getElementById('parent_id').value || null,
                is_postable: document.getElementById('is_postable').checked,
                is_active: document.getElementById('is_active').checked,
            };
            await loading.withLoading(btn, async () => {
                try {
                    if (id) await http.put(`/accounts/${id}`, payload);
                    else await http.post('/accounts', payload);
                    modal.close('#accountModal');
                    notify.success('Cuenta guardada.');
                    await refresh();
                } catch (e) {
                    const msg = e.body?.errors ? Object.values(e.body.errors)[0][0] : e.message;
                    notify.error(msg);
                }
            });
        });

        load();
    </script>
    @endpush
@endsection
