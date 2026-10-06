@extends('layouts.app')

@section('title', 'Importar pólizas · DeFinance')
@section('heading', 'Importar pólizas')

@section('content')
    <style>
        .import-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .import-help { font-size: .82rem; color: var(--c-text-muted); }
        .import-help code { background: var(--c-surface-3); padding: .08rem .35rem; border-radius: var(--radius-sm); font-family: var(--font-mono); }
        #raw { width: 100%; min-height: 180px; font-family: var(--font-mono); font-size: .85rem; resize: vertical; }
        .group-card { border: 1px solid var(--c-border); border-radius: var(--radius); overflow: hidden; margin-bottom: .8rem; }
        .group-card.invalid { border-color: var(--c-neg); }
        .group-head { display: flex; align-items: center; justify-content: space-between; gap: .6rem; padding: .55rem .85rem; background: var(--c-surface-2); font-size: .85rem; }
        .group-head .meta { color: var(--c-text-muted); }
        .group-errors { padding: .5rem .85rem; background: var(--c-neg-soft); color: var(--c-neg); font-size: .8rem; }
        .group-errors ul { margin: 0; padding-left: 1.1rem; }
        .mini { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .mini td { padding: .4rem .85rem; border-top: 1px solid var(--c-surface-3); }
        .mini td.amount { text-align: right; }
        .summary-chips { display: flex; gap: .6rem; flex-wrap: wrap; align-items: center; margin: .3rem 0 1rem; }
    </style>

    <div class="page-head">
        <div>
            <h1>Importar pólizas</h1>
            <p>Pega o sube un CSV (una línea por renglón de póliza). Se valida antes de contabilizar.</p>
        </div>
    </div>

    <div class="card card-pad import-grid">
        <div>
            <p class="import-help">
                Columnas (encabezado en la primera fila):
                <code>grupo</code> <code>fecha</code> <code>descripcion</code> <code>referencia</code>
                <code>cuenta</code> <code>cargo</code> <code>abono</code> <code>concepto</code>.
                Las líneas con el mismo <code>grupo</code> forman una póliza. <code>cuenta</code> es el
                <strong>código</strong> del catálogo. Fecha en <code>AAAA-MM-DD</code>. Importes sin separador de miles.
            </p>
            <textarea id="raw" placeholder="grupo,fecha,descripcion,cuenta,cargo,abono&#10;1,2026-10-01,Compra de papelería,5000,116,&#10;1,2026-10-01,,1180,16,&#10;1,2026-10-01,,1020,,132"></textarea>
        </div>

        <div class="toolbar" style="margin-bottom:0;">
            <input type="file" id="file" accept=".csv,.tsv,.txt" class="form-control" style="max-width:240px;">
            <button class="btn btn-ghost" id="exampleBtn" type="button">Cargar ejemplo</button>
            <div class="spacer"></div>
            <button class="btn btn-primary" id="previewBtn" type="button">Previsualizar</button>
        </div>
    </div>

    <div id="previewArea" hidden>
        <div class="summary-chips">
            <span class="pill ok" id="validChip" hidden></span>
            <span class="pill bad" id="invalidChip" hidden></span>
            <div class="spacer"></div>
            <div class="field" style="flex-direction:row;align-items:center;gap:.5rem;">
                <label for="mode" style="margin:0;">Modo</label>
                <select class="form-select" id="mode" style="width:auto;">
                    <option value="all_or_nothing">Todo o nada</option>
                    <option value="skip_invalid">Omitir inválidas</option>
                </select>
            </div>
            <button class="btn btn-primary" id="importBtn" type="button" data-loading-text="Importando…" disabled>Importar</button>
        </div>
        <div id="groups"></div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, format, guard } = DF;

        // Header aliases → canonical field. Accent/case-insensitive match.
        const HEADERS = {
            grupo: 'group', group: 'group', poliza: 'group', 'póliza': 'group',
            fecha: 'entry_date', date: 'entry_date',
            descripcion: 'description', 'descripción': 'description', description: 'description',
            referencia: 'reference', reference: 'reference', ref: 'reference',
            cuenta: 'account_code', account: 'account_code', codigo: 'account_code', 'código': 'account_code',
            cargo: 'debit', debit: 'debit', debe: 'debit',
            abono: 'credit', credit: 'credit', haber: 'credit',
            concepto: 'line_description', line_description: 'line_description',
        };

        const norm = (s) => s.trim().toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, ''); // strip accents

        function parse(text) {
            const lines = text.replace(/\r\n?/g, '\n').split('\n').filter(l => l.trim() !== '');
            if (!lines.length) return [];
            const delim = lines[0].includes('\t') ? '\t' : ',';
            const header = lines[0].split(delim).map(h => HEADERS[norm(h)] ?? null);
            const rows = [];
            for (let i = 1; i < lines.length; i++) {
                const cells = lines[i].split(delim);
                const row = {};
                header.forEach((field, idx) => {
                    if (!field) return;
                    const v = (cells[idx] ?? '').trim();
                    if (v !== '') row[field] = v;
                });
                if (Object.keys(row).length) rows.push(row);
            }
            return rows;
        }

        let lastRows = [];

        async function preview() {
            if (!await guard.ensureAuth()) return;
            const rows = parse(document.getElementById('raw').value);
            if (!rows.length) { notify.error('No hay filas que importar. Revisa el encabezado y los datos.'); return; }
            lastRows = rows;
            try {
                const r = await http.post('/entries/import/preview', { rows });
                renderPreview(r);
            } catch (e) {
                const msg = e.body?.errors ? Object.values(e.body.errors)[0][0] : e.message;
                notify.error(msg);
            }
        }

        function renderPreview(r) {
            document.getElementById('previewArea').hidden = false;
            const valid = document.getElementById('validChip');
            const invalid = document.getElementById('invalidChip');
            valid.hidden = false; valid.textContent = `${r.valid_count} válidas`;
            if (r.invalid_count > 0) { invalid.hidden = false; invalid.textContent = `${r.invalid_count} con errores`; }
            else invalid.hidden = true;

            document.getElementById('groups').innerHTML = r.groups.map(renderGroup).join('');
            document.getElementById('importBtn').disabled = !r.can_import;
        }

        function renderGroup(g) {
            const legs = g.legs.map(l => `
                <tr>
                    <td class="code">${l.account_code || '—'}${l.account_name ? ` · ${l.account_name}` : ''}</td>
                    <td class="amount num">${l.debit ? format.money(l.debit) : ''}</td>
                    <td class="amount num">${l.credit ? format.money(l.credit) : ''}</td>
                </tr>`).join('');
            const badge = g.status === 'posted'
                ? '<span class="pill ok">Contabilizada</span>'
                : g.status === 'skipped'
                    ? '<span class="pill bad">Omitida</span>'
                    : g.valid ? '<span class="pill ok">Cuadra</span>' : '<span class="pill bad">Con errores</span>';
            const errors = g.errors?.length
                ? `<div class="group-errors"><ul>${g.errors.map(e => `<li>${escapeHtml(e)}</li>`).join('')}</ul></div>` : '';
            return `
                <div class="group-card ${g.valid ? '' : 'invalid'}">
                    <div class="group-head">
                        <span><strong>Póliza ${escapeHtml(String(g.group))}</strong>
                            <span class="meta">· ${g.entry_date ?? 'sin fecha'}${g.description ? ' · ' + escapeHtml(g.description) : ''}</span></span>
                        ${badge}
                    </div>
                    <table class="mini"><tbody>${legs}</tbody>
                        <tfoot><tr>
                            <td style="font-weight:600;">Totales</td>
                            <td class="amount num">${format.money(g.totals.debit)}</td>
                            <td class="amount num">${format.money(g.totals.credit)}</td>
                        </tr></tfoot>
                    </table>
                    ${errors}
                </div>`;
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        async function doImport() {
            const mode = document.getElementById('mode').value;
            const btn = document.getElementById('importBtn');
            await loading.withLoading(btn, async () => {
                try {
                    const r = await http.post('/entries/import', { mode, rows: lastRows });
                    renderPreview(r);
                    notify.success(`${r.posted} póliza(s) contabilizada(s)${r.skipped ? `, ${r.skipped} omitida(s)` : ''}.`);
                } catch (e) {
                    // 422 returns the full result with per-group errors — render it.
                    if (e.body && Array.isArray(e.body.groups)) {
                        renderPreview(e.body);
                        notify.error('No se importó nada. Corrige los errores marcados.');
                    } else {
                        notify.error(e.message);
                    }
                }
            });
        }

        document.getElementById('file').addEventListener('change', (e) => {
            const f = e.target.files[0];
            if (!f) return;
            const reader = new FileReader();
            reader.onload = () => { document.getElementById('raw').value = reader.result; };
            reader.readAsText(f);
        });

        document.getElementById('exampleBtn').addEventListener('click', () => {
            document.getElementById('raw').value =
                'grupo,fecha,descripcion,cuenta,cargo,abono\n' +
                '1,2026-10-01,Compra de papelería,5000,116,\n' +
                '1,2026-10-01,,1180,16,\n' +
                '1,2026-10-01,,1020,,132\n' +
                '2,2026-10-02,Venta de contado,1020,232,\n' +
                '2,2026-10-02,,4000,,200\n' +
                '2,2026-10-02,,2130,,32';
        });

        document.getElementById('previewBtn').addEventListener('click', preview);
        document.getElementById('importBtn').addEventListener('click', doImport);

        guard.ensureAuth();
    </script>
    @endpush
@endsection
