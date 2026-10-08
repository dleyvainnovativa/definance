/*
 | Formatting helpers — MXN money, numbers, dates. Amounts come from the API
 | as decimal strings (e.g. "1500.0000"); format them for display only.
 */
const money0 = new Intl.NumberFormat('es-MX', {
    style: 'currency', currency: 'MXN', minimumFractionDigits: 2, maximumFractionDigits: 2,
});
const dateFmt = new Intl.DateTimeFormat('es-MX', { year: 'numeric', month: 'short', day: '2-digit' });

export const format = {
    /** "1500.0000" -> "$1,500.00" */
    money(value) {
        return money0.format(Number(value ?? 0));
    },
    /** signed, for deltas: +/−$ */
    moneySigned(value) {
        const n = Number(value ?? 0);
        return (n < 0 ? '−' : '+') + money0.format(Math.abs(n));
    },
    number(value, digits = 2) {
        return new Intl.NumberFormat('es-MX', { minimumFractionDigits: digits, maximumFractionDigits: digits })
            .format(Number(value ?? 0));
    },
    date(iso) {
        if (!iso) return '';
        // Date-only strings ("YYYY-MM-DD") parse as UTC midnight, which renders a
        // day earlier in negative-offset zones (e.g. 2026-09-01 → "31 ago" in
        // America/Mexico_City). Build them as LOCAL dates so the day is exact.
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso));
        const d = m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : new Date(iso);
        return dateFmt.format(d);
    },
    /** CSS class for a signed amount */
    signClass(value) {
        const n = Number(value ?? 0);
        return n < 0 ? 'neg' : n > 0 ? 'pos' : '';
    },
};
