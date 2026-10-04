<div class="toolbar">
    <div class="field"><label for="from">Desde</label><input class="form-control" id="from" type="date"></div>
    <div class="field"><label for="to">Hasta</label><input class="form-control" id="to" type="date"></div>
    <button class="btn btn-primary" id="genBtn">Generar</button>
</div>

<script type="module">
    (function () {
        const today = new Date().toISOString().slice(0, 10);
        const yearStart = today.slice(0, 4) + '-01-01';
        document.getElementById('from').value = yearStart;
        document.getElementById('to').value = today;
        document.getElementById('genBtn').addEventListener('click', () => window.__runReport?.());
    })();
</script>
