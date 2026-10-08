<?php

namespace App\Services\Ledger;

use App\Enums\NormalBalance;
use App\Models\ChartOfAccount;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-side of the ledger. Every figure is COMPUTED from posted/void lines —
 * no stored balances, no synthetic opening entries. Replaces the old
 * recalculateForward machinery and the three view-stacked report queries.
 */
class LedgerService
{
    /**
     * Signed balance of one account as of a date (inclusive), in its nature
     * (positive = normal side).
     */
    public function balance(int $userId, int $accountId, ?string $asOf = null): string
    {
        $account = ChartOfAccount::query()->where('user_id', $userId)->findOrFail($accountId);

        $sums = $this->sumQuery($userId)
            ->where('l.account_id', $accountId)
            ->when($asOf, fn ($q) => $q->whereDate('e.entry_date', '<=', $asOf))
            ->selectRaw('COALESCE(SUM(l.debit),0) as d, COALESCE(SUM(l.credit),0) as c')
            ->first();

        return $this->signed($account->normal_balance, Money::of($sums->d), Money::of($sums->c));
    }

    /**
     * Trial balance for a period: opening, period movement, closing per account.
     *
     * @return array{from:string,to:string,rows:array<int,array<string,mixed>>,totals:array{debit:string,credit:string}}
     */
    public function trialBalance(int $userId, string $from, string $to): array
    {
        $opening = $this->groupedSums($userId, null, Carbon::parse($from)->subDay()->toDateString());
        $period = $this->groupedSums($userId, $from, $to);

        $rows = [];
        $totalDebit = '0';
        $totalCredit = '0';

        // Closing-balance snapshot grouped by account type, for the header cards
        // (Activos / Pasivos / Capital / Ingresos / Egresos / Remanente).
        $byType = ['asset' => '0', 'liability' => '0', 'equity' => '0', 'income' => '0', 'expense' => '0'];

        foreach ($this->accounts($userId) as $account) {
            $open = $opening[$account->id] ?? ['d' => '0', 'c' => '0'];
            $per = $period[$account->id] ?? ['d' => '0', 'c' => '0'];

            $openingBalance = $this->signed($account->normal_balance, $open['d'], $open['c']);
            $periodDebit = Money::of($per['d']);
            $periodCredit = Money::of($per['c']);

            $movement = $account->normal_balance === NormalBalance::Debit
                ? Money::sub($periodDebit, $periodCredit)
                : Money::sub($periodCredit, $periodDebit);
            $closing = Money::add($openingBalance, $movement);

            if (isset($byType[$account->type->value])) {
                $byType[$account->type->value] = Money::add($byType[$account->type->value], $closing);
            }

            if (Money::isZero($openingBalance) && Money::isZero($periodDebit) && Money::isZero($periodCredit)) {
                continue;
            }

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'normal_balance' => $account->normal_balance->value,
                'opening' => $openingBalance,
                'debit' => $periodDebit,
                'credit' => $periodCredit,
                'closing' => $closing,
            ];

            $totalDebit = Money::add($totalDebit, $periodDebit);
            $totalCredit = Money::add($totalCredit, $periodCredit);
        }

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => ['debit' => $totalDebit, 'credit' => $totalCredit],
            'summary' => [
                'assets' => $byType['asset'],
                'liabilities' => $byType['liability'],
                'equity' => $byType['equity'],
                'income' => $byType['income'],
                'expenses' => $byType['expense'],
                'net' => Money::sub($byType['income'], $byType['expense']),
            ],
        ];
    }

    /**
     * Income statement (Estado de Resultados) for a period.
     *
     * @return array<string,mixed>
     */
    public function incomeStatement(int $userId, string $from, string $to): array
    {
        // Exclude year-end closing entries (reference "cierre:YYYY"): they are a
        // bookkeeping mechanism dated Dec-31, not P&L activity. Keeping them would
        // zero a closed year's statement and distort the dashboard/budget trends.
        // The balance sheet DOES include them (so results fold into equity).
        $period = $this->groupedSums($userId, $from, $to, excludeClosing: true);

        // Accounts tagged with a reserved "financieros" label feed the two
        // non-operating header cards. They stay inside operating revenue/expense
        // too (informational highlight), so the bottom line is unchanged.
        $finIncomeIds = $this->labeledAccountIds($userId, ['productos financieros', 'otros productos financieros', 'ingresos financieros']);
        $finExpenseIds = $this->labeledAccountIds($userId, ['gastos financieros', 'otros gastos financieros']);

        $revenue = [];
        $expenses = [];
        $revenueTotal = '0';
        $expenseTotal = '0';
        $financialIncome = '0';
        $financialExpense = '0';

        foreach ($this->accounts($userId) as $account) {
            $sums = $period[$account->id] ?? ['d' => '0', 'c' => '0'];
            $amount = $this->signed($account->normal_balance, $sums['d'], $sums['c']);

            if ($account->type->value === 'income' && ! Money::isZero($amount)) {
                $revenue[] = $this->line($account, $amount);
                $revenueTotal = Money::add($revenueTotal, $amount);
                if (isset($finIncomeIds[$account->id])) {
                    $financialIncome = Money::add($financialIncome, $amount);
                }
            } elseif ($account->type->value === 'expense' && ! Money::isZero($amount)) {
                $expenses[] = $this->line($account, $amount);
                $expenseTotal = Money::add($expenseTotal, $amount);
                if (isset($finExpenseIds[$account->id])) {
                    $financialExpense = Money::add($financialExpense, $amount);
                }
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'totals' => [
                'revenue' => $revenueTotal,
                'expenses' => $expenseTotal,
                'net_income' => Money::sub($revenueTotal, $expenseTotal),
                'financial_income' => $financialIncome,
                'financial_expense' => $financialExpense,
                'resultado_del_ejercicio' => Money::sub($revenueTotal, $expenseTotal),
            ],
        ];
    }

    /**
     * Balance sheet (Balance General) as of a date. Net income of open periods
     * is folded into equity so Assets = Liabilities + Equity.
     *
     * @return array<string,mixed>
     */
    public function balanceSheet(int $userId, string $asOf): array
    {
        $upto = $this->groupedSums($userId, null, $asOf);

        $assets = [];
        $liabilities = [];
        $equity = [];
        $assetTotal = '0';
        $assetCurrentTotal = '0';
        $assetFixedTotal = '0';
        $liabilityTotal = '0';
        $equityTotal = '0';
        $incomeTotal = '0';
        $expenseTotal = '0';

        foreach ($this->accounts($userId) as $account) {
            $sums = $upto[$account->id] ?? ['d' => '0', 'c' => '0'];
            $amount = $this->signed($account->normal_balance, $sums['d'], $sums['c']);

            switch ($account->type->value) {
                case 'asset':
                    if (! Money::isZero($amount)) {
                        $assets[] = $this->line($account, $amount);
                    }
                    $assetTotal = Money::add($assetTotal, $amount);
                    // Fixed assets = code prefixed "110" (Activo fijo); everything
                    // else under asset is current (circulante).
                    if (self::isFixedAssetCode((string) $account->code)) {
                        $assetFixedTotal = Money::add($assetFixedTotal, $amount);
                    } else {
                        $assetCurrentTotal = Money::add($assetCurrentTotal, $amount);
                    }
                    break;
                case 'liability':
                    if (! Money::isZero($amount)) {
                        $liabilities[] = $this->line($account, $amount);
                    }
                    $liabilityTotal = Money::add($liabilityTotal, $amount);
                    break;
                case 'equity':
                    if (! Money::isZero($amount)) {
                        $equity[] = $this->line($account, $amount);
                    }
                    $equityTotal = Money::add($equityTotal, $amount);
                    break;
                case 'income':
                    $incomeTotal = Money::add($incomeTotal, $amount);
                    break;
                case 'expense':
                    $expenseTotal = Money::add($expenseTotal, $amount);
                    break;
            }
        }

        $netIncome = Money::sub($incomeTotal, $expenseTotal);
        $equityWithResult = Money::add($equityTotal, $netIncome);
        $liabilitiesPlusEquity = Money::add($liabilityTotal, $equityWithResult);

        return [
            'as_of' => $asOf,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'net_income' => $netIncome,
            'totals' => [
                'assets' => $assetTotal,
                'assets_current' => $assetCurrentTotal,
                'assets_fixed' => $assetFixedTotal,
                'liabilities' => $liabilityTotal,
                'equity' => $equityTotal,
                'equity_with_result' => $equityWithResult,
                'liabilities_plus_equity' => $liabilitiesPlusEquity,
            ],
            'balanced' => Money::cmp($assetTotal, $liabilitiesPlusEquity) === 0,
        ];
    }

    /**
     * Cash flow for a period: movement across cash/equivalent accounts
     * (is_cash). Opening + inflows − outflows = closing.
     *
     * @return array<string,mixed>
     */
    public function cashFlow(int $userId, string $from, string $to): array
    {
        $cashAccounts = $this->accounts($userId)->where('is_cash', true);
        $cashIds = $cashAccounts->pluck('id')->all();

        $opening = $this->groupedSums($userId, null, Carbon::parse($from)->subDay()->toDateString());
        $period = $this->groupedSums($userId, $from, $to);

        $rows = [];
        $openingTotal = '0';
        $inflowTotal = '0';
        $outflowTotal = '0';

        foreach ($cashAccounts as $account) {
            $open = $opening[$account->id] ?? ['d' => '0', 'c' => '0'];
            $per = $period[$account->id] ?? ['d' => '0', 'c' => '0'];

            $openingBal = $this->signed($account->normal_balance, $open['d'], $open['c']);
            $inflow = Money::of($per['d']);   // debits increase cash (asset)
            $outflow = Money::of($per['c']);
            $closingBal = Money::add($openingBal, Money::sub($inflow, $outflow));

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'opening' => $openingBal,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'closing' => $closingBal,
            ];

            $openingTotal = Money::add($openingTotal, $openingBal);
            $inflowTotal = Money::add($inflowTotal, $inflow);
            $outflowTotal = Money::add($outflowTotal, $outflow);
        }

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'opening' => $openingTotal,
                'inflow' => $inflowTotal,
                'outflow' => $outflowTotal,
                'net_change' => Money::sub($inflowTotal, $outflowTotal),
                'closing' => Money::add($openingTotal, Money::sub($inflowTotal, $outflowTotal)),
            ],
        ];
    }

    /**
     * Averages (Promedios): income-statement figures per account divided by the
     * number of calendar months in the range (inclusive). Read-only.
     *
     * @return array<string,mixed>
     */
    public function averages(int $userId, string $from, string $to): array
    {
        $months = $this->monthsBetween($from, $to);
        $divisor = (string) $months;
        $statement = $this->incomeStatement($userId, $from, $to);

        $toAvg = fn (array $rows): array => array_map(fn (array $r) => [
            'account_id' => $r['account_id'],
            'code' => $r['code'],
            'name' => $r['name'],
            'total' => $r['amount'],
            'average' => bcdiv($r['amount'], $divisor, Money::SCALE),
        ], $rows);

        $revTotal = $statement['totals']['revenue'];
        $expTotal = $statement['totals']['expenses'];
        $net = $statement['totals']['net_income'];

        return [
            'from' => $from,
            'to' => $to,
            'months' => $months,
            'revenue' => $toAvg($statement['revenue']),
            'expenses' => $toAvg($statement['expenses']),
            'totals' => [
                'revenue' => ['total' => $revTotal, 'average' => bcdiv($revTotal, $divisor, Money::SCALE)],
                'expenses' => ['total' => $expTotal, 'average' => bcdiv($expTotal, $divisor, Money::SCALE)],
                'net' => ['total' => $net, 'average' => bcdiv($net, $divisor, Money::SCALE)],
            ],
        ];
    }

    /**
     * Signed period movement per account (positive = normal side of the
     * account's nature), keyed by account id. Used by cross-cutting roll-ups
     * such as the label report.
     *
     * @return array<int,string>
     */
    public function accountMovements(int $userId, string $from, string $to): array
    {
        $period = $this->groupedSums($userId, $from, $to);

        $out = [];
        foreach ($this->accounts($userId) as $account) {
            $sums = $period[$account->id] ?? ['d' => '0', 'c' => '0'];
            $out[$account->id] = $this->signed($account->normal_balance, $sums['d'], $sums['c']);
        }

        return $out;
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Fixed-asset classifier for the balance-sheet header cards. Fixed =
     * code "110" or a descendant ("110.x"); everything else under the asset
     * type is current (circulante).
     */
    private static function isFixedAssetCode(string $code): bool
    {
        return $code === '110' || str_starts_with($code, '110.');
    }

    /**
     * Account IDs tagged with any etiqueta whose (accent/case-normalized) name
     * matches one of $names. Returned as a set keyed by account id for isset().
     *
     * @param  array<int,string>  $names
     * @return array<int,true>
     */
    private function labeledAccountIds(int $userId, array $names): array
    {
        $wanted = array_map(fn ($n) => $this->normalizeLabel($n), $names);

        $rows = DB::table('account_etiqueta as ae')
            ->join('etiquetas as t', 't.id', '=', 'ae.etiqueta_id')
            ->where('t.user_id', $userId)
            ->get(['ae.account_id', 't.name']);

        $ids = [];
        foreach ($rows as $row) {
            if (in_array($this->normalizeLabel((string) $row->name), $wanted, true)) {
                $ids[(int) $row->account_id] = true;
            }
        }

        return $ids;
    }

    /** lowercase, trim, strip Spanish accents, collapse inner whitespace. */
    private function normalizeLabel(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);

        return preg_replace('/\s+/', ' ', $s);
    }

    /** Whole calendar months spanned by [from, to], inclusive (min 1). */
    private function monthsBetween(string $from, string $to): int
    {
        $a = Carbon::parse($from);
        $b = Carbon::parse($to);

        return max(1, ($b->year - $a->year) * 12 + ($b->month - $a->month) + 1);
    }

    /** @return \Illuminate\Support\Collection<int,ChartOfAccount> */
    private function accounts(int $userId): \Illuminate\Support\Collection
    {
        return ChartOfAccount::query()
            ->where('user_id', $userId)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'normal_balance', 'is_cash']);
    }

    /** @return array<string,mixed> */
    private function line(ChartOfAccount $account, string $amount): array
    {
        return [
            'account_id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'amount' => $amount,
        ];
    }

    private function signed(NormalBalance $normal, string $debit, string $credit): string
    {
        return $normal === NormalBalance::Debit
            ? Money::sub($debit, $credit)
            : Money::sub($credit, $debit);
    }

    /**
     * Debit/credit sums grouped by account for an optional date window.
     *
     * @return array<int,array{d:string,c:string}>
     */
    private function groupedSums(int $userId, ?string $from, ?string $to, bool $excludeClosing = false): array
    {
        $rows = $this->sumQuery($userId, $excludeClosing)
            ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.entry_date', '<=', $to))
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, COALESCE(SUM(l.debit),0) as d, COALESCE(SUM(l.credit),0) as c')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->account_id] = ['d' => Money::of($row->d), 'c' => Money::of($row->c)];
        }

        return $map;
    }

    /**
     * Base join. Includes `posted` AND `void` entries (only `draft` excluded):
     * a voided entry and its reversing entry net to zero, so counting both
     * keeps balances correct and the audit trail intact.
     */
    private function sumQuery(int $userId, bool $excludeClosing = false): \Illuminate\Database\Query\Builder
    {
        return DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.user_id', $userId)
            ->whereIn('e.status', ['posted', 'void'])
            // P&L reports exclude year-end closing entries (reference "cierre:YYYY").
            ->when($excludeClosing, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('e.reference')
                ->orWhere('e.reference', 'not like', 'cierre:%')
            ));
    }
}
