@extends('layouts.app')

@section('title', 'Resumen · DeFinance')
@section('heading', 'Resumen')

@section('content')
    <div class="page-head d-flex align-items-start justify-content-between flex-wrap gap-2">
        <div>
            <h1>Resumen</h1>
            <p id="periodLabel">Cargando periodo…</p>
        </div>
        <span class="pill ok" id="balancedPill" hidden>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Contabilidad cuadrada
        </span>
    </div>

    <section class="stats mb-4">
        <div class="stat">
            <div class="label">Activos
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </div>
            <div class="value num" id="statAssets">—</div>
        </div>
        <div class="stat">
            <div class="label">Pasivos
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
            </div>
            <div class="value num" id="statLiabilities">—</div>
        </div>
        <div class="stat">
            <div class="label">Capital
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 10h6"/></svg>
            </div>
            <div class="value num" id="statEquity">—</div>
        </div>
        <div class="stat accent">
            <div class="label">Resultado del mes
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 17 9 11 13 15 21 7"/></svg>
            </div>
            <div class="value num" id="statResult">—</div>
            <div class="delta" id="statResultLabel"></div>
        </div>
    </section>

    <div class="report-cols" style="margin-bottom:1.5rem;">
        <div class="card card-pad">
            <h2 style="font-size:1.02rem;margin-bottom:.6rem;">Resultado mensual <span style="color:var(--c-text-muted);font-weight:500;font-size:.85rem;">(6 meses)</span></h2>
            <div id="trendChart"><div class="empty">Cargando…</div></div>
        </div>
        <div class="card card-pad">
            <h2 style="font-size:1.02rem;margin-bottom:.6rem;">Gastos por cuenta <span style="color:var(--c-text-muted);font-weight:500;font-size:.85rem;">(año)</span></h2>
            <div id="expenseChart"><div class="empty">Cargando…</div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-pad d-flex align-items-center justify-content-between">
            <h2 style="font-size:1.02rem;">Movimientos recientes</h2>
            <a class="btn btn-ghost" href="{{ route('entries') }}">Ver pólizas</a>
        </div>
        <div class="table-wrap">
            <table class="ledger">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Referencia</th>
                        <th class="amount">Importe</th>
                    </tr>
                </thead>
                <tbody id="recentRows">
                    <tr><td colspan="4" style="color:var(--c-text-muted);padding:1.2rem .9rem;">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, format, guard, notify, charts } = DF;

        const todayISO = new Date().toISOString().slice(0, 10);
        const monthStart = todayISO.slice(0, 8) + '01';

        function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }

        async function load() {
            const user = await guard.ensureAuth();
            if (!user) return;

            try {
                const [bs, is, entries, dash] = await Promise.all([
                    http.get(`/reports/balance-sheet?as_of=${todayISO}`),
                    http.get(`/reports/income-statement?from=${monthStart}&to=${todayISO}`),
                    http.get('/entries?per_page=6'),
                    http.get('/dashboard'),
                ]);

                charts.line('#trendChart', { points: (dash.trend || []).map(t => ({ label: t.label, value: t.net })) });
                charts.bars('#expenseChart', { rows: (dash.expense_breakdown || []).map(e => ({ name: e.name, value: e.amount })) });

                setText('periodLabel', `Al ${format.date(todayISO)}`);

                setText('statAssets', format.money(bs.totals.assets));
                setText('statLiabilities', format.money(bs.totals.liabilities));
                setText('statEquity', format.money(bs.totals.equity_with_result));

                const net = is.totals.net_income;
                const resultEl = document.getElementById('statResult');
                resultEl.textContent = format.money(net);
                resultEl.classList.add(format.signClass(net));
                setText('statResultLabel', Number(net) >= 0 ? 'Utilidad' : 'Pérdida');

                const pill = document.getElementById('balancedPill');
                if (bs.balanced) { pill.hidden = false; }
                else {
                    pill.hidden = false; pill.classList.remove('ok'); pill.classList.add('bad');
                    pill.lastChild.textContent = ' Descuadre detectado';
                }

                renderRecent(entries.data);
            } catch (err) {
                notify.error(err.message || 'No se pudieron cargar los datos.');
            }
        }

        function renderRecent(rows) {
            const body = document.getElementById('recentRows');
            if (!rows || rows.length === 0) {
                body.innerHTML = '<tr><td colspan="4" style="color:var(--c-text-muted);padding:1.2rem .9rem;">Aún no hay pólizas registradas.</td></tr>';
                return;
            }
            body.innerHTML = rows.map((e) => `
                <tr>
                    <td class="num code">${format.date(e.entry_date)}</td>
                    <td>${e.description ?? ''}</td>
                    <td class="code">${e.reference ?? ''}</td>
                    <td class="amount num">${format.money(e.totals?.debit ?? 0)}</td>
                </tr>`).join('');
        }

        load();
    </script>
    @endpush
@endsection
