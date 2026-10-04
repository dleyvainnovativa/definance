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
        return dateFmt.format(new Date(iso));
    },
    /** CSS class for a signed amount */
    signClass(value) {
        const n = Number(value ?? 0);
        return n < 0 ? 'neg' : n > 0 ? 'pos' : '';
    },
};
