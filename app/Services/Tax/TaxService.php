<?php

namespace App\Services\Tax;

use App\Models\ChartOfAccount;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * IVA helpers: resolve the acreditable/trasladado accounts for a user, expose
 * the configured rates, and compute the IVA declaration for a period.
 *
 * IVA legs themselves are posted as ordinary journal lines (carrying tax_code,
 * tax_rate, tax_base); this service is the read/config side plus the report.
 */
class TaxService
{
    /** @return array<string,float> code => rate */
    public function rates(): array
    {
        return config('taxes.iva.rates', []);
    }

    /**
     * The user's IVA accounts, or null when the chart doesn't define them.
     *
     * @return array{acreditable:?ChartOfAccount,trasladado:?ChartOfAccount}
     */
    public function accounts(int $userId): array
    {
        $codes = config('taxes.iva.accounts');

        $found = ChartOfAccount::query()
            ->where('user_id', $userId)
            ->whereIn('code', [$codes['acreditable_code'], $codes['trasladado_code']])
            ->get()
            ->keyBy('code');

        return [
            'acreditable' => $found->get($codes['acreditable_code']),
            'trasladado' => $found->get($codes['trasladado_code']),
        ];
    }

    /** Compute IVA from a base amount and a rate (both as numbers/strings), 2 dp. */
    public function iva(string $base, float $rate): string
    {
        return bcmul(Money::of($base), (string) $rate, Money::SCALE);
    }

    /**
     * IVA declaration for a period:
     *   trasladado (cobrado) − acreditable (pagado) = a cargo (>0) o a favor (<0).
     *
     * @return array<string,mixed>
     */
    public function ivaReport(int $userId, string $from, string $to): array
    {
        $accounts = $this->accounts($userId);
        $acreditable = $this->netOnAccount($userId, $accounts['acreditable']?->id, $from, $to, 'debit');
        $trasladado = $this->netOnAccount($userId, $accounts['trasladado']?->id, $from, $to, 'credit');

        $neto = Money::sub($trasladado, $acreditable);

        return [
            'from' => $from,
            'to' => $to,
            'trasladado' => $trasladado,            // IVA cobrado a clientes
            'acreditable' => $acreditable,          // IVA pagado a proveedores
            'neto' => $neto,
            'resultado' => Money::isNegative($neto) ? 'a_favor' : 'a_cargo',
            'accounts_configured' => $accounts['acreditable'] !== null && $accounts['trasladado'] !== null,
        ];
    }

    /** Net movement on an account for the period, signed by the requested side. */
    private function netOnAccount(int $userId, ?int $accountId, string $from, string $to, string $side): string
    {
        if (! $accountId) {
            return Money::of(0);
        }

        $sums = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.user_id', $userId)
            ->where('l.account_id', $accountId)
            ->whereIn('e.status', ['posted', 'void'])
            ->whereDate('e.entry_date', '>=', $from)
            ->whereDate('e.entry_date', '<=', $to)
            ->selectRaw('COALESCE(SUM(l.debit),0) as d, COALESCE(SUM(l.credit),0) as c')
            ->first();

        return $side === 'debit'
            ? Money::sub(Money::of($sums->d), Money::of($sums->c))
            : Money::sub(Money::of($sums->c), Money::of($sums->d));
    }
}
