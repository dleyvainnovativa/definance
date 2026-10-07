{{--
    Period filter — month/year by default (current month), with a toggle to a
    custom range. Exposes hidden #from/#to (and #as_of) that report pages read,
    so the page JS is unchanged. Pass ['target' => 'asof'] for point-in-time
    reports (balance sheet): then only #as_of is driven and the custom tab is a
    single date.
--}}
@php($target = $target ?? 'period')

<div class="toolbar period-filter" data-target="{{ $target }}">
    <div class="field">
        <label>Vista</label>
        <div class="pf-tabs" role="tablist">
            <button type="button" class="pf-tab active" data-mode="month">Mes</button>
            <button type="button" class="pf-tab" data-mode="range">{{ $target === 'asof' ? 'Fecha' : 'Rango' }}</button>
        </div>
    </div>
    <div class="field pf-f-month"><label for="pfMonth">Mes</label>
        <select class="form-select" id="pfMonth"></select></div>
    <div class="field pf-f-year"><label for="pfYear">Año</label>
        <select class="form-select" id="pfYear"></select></div>
    <div class="field pf-f-from" hidden><label for="pfFrom">{{ $target === 'asof' ? 'Al' : 'Desde' }}</label>
        <input class="form-control" type="date" id="pfFrom"></div>
    <div class="field pf-f-to" hidden><label for="pfTo">Hasta</label>
        <input class="form-control" type="date" id="pfTo"></div>
    <button class="btn btn-primary" id="genBtn">Generar</button>

    {{-- canonical values the report pages read --}}
    <input type="hidden" id="from"><input type="hidden" id="to"><input type="hidden" id="as_of">
</div>

<script type="module">
    (function () {
        const root = document.querySelector('.period-filter');
        const target = root.dataset.target;
        const now = new Date();
        const Y = now.getFullYear(), M = now.getMonth() + 1;

        const names = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const monthSel = document.getElementById('pfMonth');
        const yearSel = document.getElementById('pfYear');

        monthSel.innerHTML = names.map((n, i) => `<option value="${i + 1}">${n}</option>`).join('') +
            (target === 'asof' ? '' : '<option value="0">Todo el año</option>');
        monthSel.value = String(M);

        const years = [];
        for (let y = Y + 1; y >= Y - 6; y--) years.push(y);
        yearSel.innerHTML = years.map((y) => `<option value="${y}">${y}</option>`).join('');
        yearSel.value = String(Y);

        const pfFrom = document.getElementById('pfFrom');
        const pfTo = document.getElementById('pfTo');
        const pad = (n) => String(n).padStart(2, '0');
        pfFrom.value = `${Y}-${pad(M)}-01`;
        pfTo.value = now.toISOString().slice(0, 10);

        const hFrom = document.getElementById('from');
        const hTo = document.getElementById('to');
        const hAsOf = document.getElementById('as_of');
        const lastDay = (y, m) => new Date(y, m, 0).getDate(); // m: 1-12

        let mode = 'month';

        function compute() {
            if (mode === 'month') {
                const m = Number(monthSel.value), y = Number(yearSel.value);
                if (m === 0) {
                    hFrom.value = `${y}-01-01`; hTo.value = `${y}-12-31`; hAsOf.value = `${y}-12-31`;
                } else {
                    const end = `${y}-${pad(m)}-${pad(lastDay(y, m))}`;
                    hFrom.value = `${y}-${pad(m)}-01`; hTo.value = end; hAsOf.value = end;
                }
            } else if (target === 'asof') {
                hAsOf.value = pfFrom.value;
            } else {
                hFrom.value = pfFrom.value; hTo.value = pfTo.value;
            }
        }

        function setMode(m) {
            mode = m;
            document.querySelectorAll('.pf-tab').forEach((t) => t.classList.toggle('active', t.dataset.mode === m));
            const month = m === 'month';
            root.querySelector('.pf-f-month').hidden = !month;
            root.querySelector('.pf-f-year').hidden = !month;
            root.querySelector('.pf-f-from').hidden = month;
            root.querySelector('.pf-f-to').hidden = month || target === 'asof';
        }

        document.querySelectorAll('.pf-tab').forEach((t) => t.addEventListener('click', () => setMode(t.dataset.mode)));
        document.getElementById('genBtn').addEventListener('click', () => { compute(); window.__runReport?.(); });

        // Seed current-month values now, before the page's own run() (which runs
        // after this script) reads the hidden inputs.
        setMode('month');
        compute();
    })();
</script>
