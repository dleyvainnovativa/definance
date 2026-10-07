@extends('layouts.app')

@section('title', 'Etiquetas · DeFinance')
@section('heading', 'Etiquetas')

@section('content')
    <div class="page-head">
        <div><h1>Etiquetas</h1><p>Agrupa cuentas con etiquetas para ver reportes por categoría (ej. «suscripciones»).</p></div>
        <button class="btn btn-primary" id="newLabelBtn"><i class="fa-solid fa-plus" aria-hidden="true"></i> Nueva etiqueta</button>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="ledger ledger--cards">
                <thead><tr><th>Etiqueta</th><th>Color</th><th class="amount">Cuentas</th><th class="amount">Acciones</th></tr></thead>
                <tbody id="rows"><tr><td colspan="4" class="empty">Cargando…</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="labelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="labelForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="labelModalTitle">Nueva etiqueta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="labelId">
                        <div class="row g-3">
                            <div class="col-8"><label class="form-label" for="labelName">Nombre</label><input class="form-control" id="labelName" required></div>
                            <div class="col-4"><label class="form-label" for="labelColor">Color</label><input class="form-control" id="labelColor" type="color" value="#406dab" style="height:42px;padding:.25rem"></div>
                        </div>
                        <p class="simple-hint">Asigna la etiqueta a cuentas desde el catálogo de cuentas.</p>
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
        const { http, notify, loading, modal, guard } = DF;
        let labels = [];

        async function load() {
            if (!await guard.ensureAuth()) return;
            await refresh();
            document.getElementById('newLabelBtn').addEventListener('click', openNew);
            document.getElementById('labelForm').addEventListener('submit', save);
        }

        async function refresh() {
            loading.skeleton('#rows', { rows: 4, cols: 4 });
            try { const r = await http.get('/labels'); labels = r.data; render(); }
            catch (e) { notify.error(e.message); }
        }

        function render() {
            const body = document.getElementById('rows');
            if (!labels.length) { body.innerHTML = '<tr><td colspan="4" class="empty">Aún no tienes etiquetas. Crea la primera.</td></tr>'; return; }
            body.innerHTML = labels.map(l => `
                <tr>
                    <td data-label="Etiqueta"><span class="tag-chip" style="--chip:${l.color || 'var(--c-primary)'}">${l.name}</span></td>
                    <td data-label="Color"><span class="swatch" style="background:${l.color || 'var(--c-primary)'}"></span></td>
                    <td data-label="Cuentas" class="amount num">${l.accounts_count ?? 0}</td>
                    <td class="amount"><span class="row-actions">
                        <button class="btn-icon" data-edit="${l.id}" title="Editar"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn-icon danger" data-del="${l.id}" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                    </span></td>
                </tr>`).join('');
            body.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openEdit(b.dataset.edit)));
            body.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => remove(b.dataset.del)));
        }

        function openNew() {
            document.getElementById('labelModalTitle').textContent = 'Nueva etiqueta';
            document.getElementById('labelForm').reset();
            document.getElementById('labelId').value = '';
            document.getElementById('labelColor').value = '#406dab';
            modal.open('#labelModal');
        }

        function openEdit(id) {
            const l = labels.find(x => x.id == id);
            if (!l) return;
            document.getElementById('labelModalTitle').textContent = 'Editar etiqueta';
            document.getElementById('labelId').value = l.id;
            document.getElementById('labelName').value = l.name;
            document.getElementById('labelColor').value = l.color || '#406dab';
            modal.open('#labelModal');
        }

        async function save(e) {
            e.preventDefault();
            const btn = e.currentTarget.querySelector('button[type="submit"]');
            const id = document.getElementById('labelId').value;
            const payload = { name: document.getElementById('labelName').value, color: document.getElementById('labelColor').value };
            await loading.withLoading(btn, async () => {
                try {
                    if (id) await http.put(`/labels/${id}`, payload);
                    else await http.post('/labels', payload);
                    modal.close('#labelModal');
                    notify.success('Etiqueta guardada.');
                    await refresh();
                } catch (e) {
                    const msg = e.body?.errors ? Object.values(e.body.errors)[0][0] : e.message;
                    notify.error(msg);
                }
            });
        }

        async function remove(id) {
            const l = labels.find(x => x.id == id);
            if (!window.confirm(`¿Eliminar la etiqueta «${l.name}»? Se quitará de sus cuentas.`)) return;
            try { await http.del(`/labels/${id}`); notify.success('Etiqueta eliminada.'); await refresh(); }
            catch (e) { notify.error(e.message); }
        }

        load();
    </script>
    @endpush
@endsection
