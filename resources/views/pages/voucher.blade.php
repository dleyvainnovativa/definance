@extends('layouts.app')

@section('title', 'Comprobante · DeFinance')
@section('heading', 'Comprobante de póliza')

@section('content')
    <style>
        .voucher-meta { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1rem; }
        .voucher-meta .k { font-size: .74rem; color: var(--c-text-muted); }
        .voucher-meta .v { font-weight: 600; }
        @media (max-width: 640px) { .voucher-meta { grid-template-columns: 1fr 1fr; } }
        @media print {
            .sidebar, .topbar, .scrim, .toolbar, #printBtn, .page-head p { display: none !important; }
            .app { display: block; }
            .content { padding: 0; max-width: none; }
            .card { box-shadow: none; border: none; }
        }
    </style>

    <div class="page-head">
        <div><h1>Comprobante de póliza</h1><p>Presentación imprimible de una póliza (cargos y abonos).</p></div>
        <button class="btn btn-ghost" id="printBtn" type="button">Imprimir</button>
    </div>

    <div class="toolbar">
        <div class="field"><label for="entryId">Póliza #</label><input class="form-control" id="entryId" type="number" min="1" style="max-width:140px;"></div>
        <button class="btn btn-primary" id="loadBtn" type="button">Cargar</button>
    </div>

    <div class="card card-pad" id="voucher" hidden>
        <div class="voucher-meta">
            <div><div class="k">Póliza</div><div class="v num" id="vId"></div></div>
            <div><div class="k">Fecha</div><div class="v" id="vDate"></div></div>
            <div><div class="k">Referencia</div><div class="v" id="vRef"></div></div>
            <div><div class="k">Estado</div><div class="v"><span class="status" id="vStatus"></span></div></div>
        </div>
        <div style="margin-bottom:1rem;"><div class="k" style="font-size:.74rem;color:var(--c-text-muted);">Descripción</div><div id="vDesc"></div></div>
        <table class="ledger">
            <thead><tr><th>Código</th><th>Cuenta</th><th>Concepto</th><th class="amount">Cargo</th><th class="amount">Abono</th></tr></thead>
            <tbody id="vLines"></tbody>
            <tfoot id="vFoot"></tfoot>
        </table>
    </div>

    <div class="card" id="emptyState"><div class="empty">Indica el número de póliza para ver su comprobante.</div></div>

    @push('scripts')
    <script type="module">
        const { http, notify, format, guard } = DF;
        const STATUS = { posted: 'Contabilizado', void: 'Cancelado', draft: 'Borrador' };

        const initialId = new URLSearchParams(location.search).get('id');
        if (initialId) document.getElementById('entryId').value = initialId;

        async function load() {
            if (!await guard.ensureAuth()) return;
            const id = document.getElementById('entryId').value;
            if (!id) { notify.error('Indica el número de póliza.'); return; }
            try {
                const { data } = await http.get(`/entries/${id}`);
                render(data);
            } catch (e) { notify.error(e.status === 404 ? 'Póliza no encontrada.' : e.message); }
        }

        function render(e) {
            document.getElementById('voucher').hidden = false;
            document.getElementById('emptyState').hidden = true;
            document.getElementById('vId').textContent = '#' + e.id;
            document.getElementById('vDate').textContent = format.date(e.entry_date);
            document.getElementById('vRef').textContent = e.reference || '—';
            const st = document.getElementById('vStatus');
            st.textContent = STATUS[e.status] ?? e.status;
            st.className = 'status ' + e.status;
            document.getElementById('vDesc').textContent = e.description || '—';

            const lines = e.lines || [];
            document.getElementById('vLines').innerHTML = lines.map(l => `
                <tr>
                    <td class="num code">${l.account?.code ?? ''}</td>
                    <td>${l.account?.name ?? ''}</td>
                    <td>${l.line_description ?? ''}</td>
                    <td class="amount num">${l.debit ? format.money(l.debit) : ''}</td>
                    <td class="amount num">${l.credit ? format.money(l.credit) : ''}</td>
                </tr>`).join('');
            document.getElementById('vFoot').innerHTML = `
                <tr><td colspan="3" style="font-weight:600;">Totales</td>
                    <td class="amount num">${format.money(e.totals?.debit ?? 0)}</td>
                    <td class="amount num">${format.money(e.totals?.credit ?? 0)}</td></tr>`;
        }

        document.getElementById('loadBtn').addEventListener('click', load);
        document.getElementById('printBtn').addEventListener('click', () => window.print());
        if (initialId) load(); else guard.ensureAuth();
    </script>
    @endpush
@endsection
