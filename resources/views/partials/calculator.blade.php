{{-- Global calculator modal (opened from the topbar). Self-contained; no deps. --}}
<div class="modal fade" id="calcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Calculadora</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="calc">
                    <div class="calc-expr" id="calcExpr">&nbsp;</div>
                    <div class="calc-out num" id="calcOut">0</div>
                    <div class="calc-keys" id="calcKeys">
                        <button class="calc-key fn" data-k="C">C</button>
                        <button class="calc-key fn" data-k="back">⌫</button>
                        <button class="calc-key fn" data-k="%">%</button>
                        <button class="calc-key op" data-k="/">÷</button>
                        <button class="calc-key" data-k="7">7</button>
                        <button class="calc-key" data-k="8">8</button>
                        <button class="calc-key" data-k="9">9</button>
                        <button class="calc-key op" data-k="*">×</button>
                        <button class="calc-key" data-k="4">4</button>
                        <button class="calc-key" data-k="5">5</button>
                        <button class="calc-key" data-k="6">6</button>
                        <button class="calc-key op" data-k="-">−</button>
                        <button class="calc-key" data-k="1">1</button>
                        <button class="calc-key" data-k="2">2</button>
                        <button class="calc-key" data-k="3">3</button>
                        <button class="calc-key op" data-k="+">+</button>
                        <button class="calc-key" data-k="0" style="grid-column: span 2;">0</button>
                        <button class="calc-key" data-k=".">.</button>
                        <button class="calc-key eq" data-k="=">=</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .calc-expr { text-align: right; color: var(--c-text-subtle); font-family: var(--font-mono); font-size: .8rem; min-height: 1.1rem; }
    .calc-out { text-align: right; font-size: 1.8rem; font-weight: 600; margin: .1rem 0 .7rem; overflow-x: auto; }
    .calc-keys { display: grid; grid-template-columns: repeat(4, 1fr); gap: .4rem; }
    .calc-key { border: 1px solid var(--c-border); background: var(--c-surface); color: var(--c-text); border-radius: var(--radius); padding: .7rem 0; font-size: 1rem; font-weight: 600; cursor: pointer; }
    .calc-key:hover { background: var(--c-surface-2); }
    .calc-key.op { color: var(--c-primary); }
    .calc-key.fn { color: var(--c-text-muted); font-weight: 500; }
    .calc-key.eq { background: var(--c-primary); color: var(--c-on-primary); border-color: var(--c-primary); }
</style>

<script type="module">
    (function () {
        const keys = document.getElementById('calcKeys');
        const outEl = document.getElementById('calcOut');
        const exprEl = document.getElementById('calcExpr');
        if (!keys) return;

        let tokens = [];   // mix of number-strings and operators
        let current = '';  // the number being typed

        const isOp = (t) => ['+', '-', '*', '/'].includes(t);

        function render() {
            exprEl.textContent = (tokens.join(' ') + ' ' + current).trim() || ' ';
            outEl.textContent = current || (tokens.length ? tokens[tokens.length - 1] : '0');
        }

        // Shunting-yard → RPN → evaluate (no eval()).
        function evaluate() {
            const all = [...tokens];
            if (current !== '') all.push(current);
            if (!all.length) return 0;
            const prec = { '+': 1, '-': 1, '*': 2, '/': 2 };
            const out = [], ops = [];
            for (const t of all) {
                if (isOp(t)) {
                    while (ops.length && prec[ops[ops.length - 1]] >= prec[t]) out.push(ops.pop());
                    ops.push(t);
                } else {
                    out.push(parseFloat(t));
                }
            }
            while (ops.length) out.push(ops.pop());
            const st = [];
            for (const t of out) {
                if (typeof t === 'number') { st.push(t); continue; }
                const b = st.pop(), a = st.pop();
                st.push(t === '+' ? a + b : t === '-' ? a - b : t === '*' ? a * b : (b === 0 ? NaN : a / b));
            }
            const r = st.pop();
            return Number.isFinite(r) ? Math.round(r * 1e6) / 1e6 : 0;
        }

        function press(k) {
            if (k === 'C') { tokens = []; current = ''; }
            else if (k === 'back') { current = current.slice(0, -1); }
            else if (k === '%') { if (current) current = String((parseFloat(current) || 0) / 100); }
            else if (k === '=') { const r = evaluate(); tokens = []; current = String(r); }
            else if (isOp(k)) {
                if (current === '' && !tokens.length) return;
                if (current !== '') { tokens.push(current); current = ''; }
                if (isOp(tokens[tokens.length - 1])) tokens[tokens.length - 1] = k; // replace trailing op
                else tokens.push(k);
            }
            else if (k === '.') { if (!current.includes('.')) current = (current || '0') + '.'; }
            else { current += k; } // digit
            render();
        }

        keys.addEventListener('click', (e) => { const b = e.target.closest('.calc-key'); if (b) press(b.dataset.k); });

        // Keyboard support while the modal is open.
        document.getElementById('calcModal')?.addEventListener('keydown', (e) => {
            const map = { Enter: '=', '=': '=', Backspace: 'back', Escape: 'C', 'x': '*', 'X': '*' };
            const k = map[e.key] ?? e.key;
            if (/^[0-9.]$/.test(k) || isOp(k) || ['=', 'back', 'C', '%'].includes(k)) { e.preventDefault(); press(k); }
        });

        render();
    })();
</script>
