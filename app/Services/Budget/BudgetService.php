<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Services\Ledger\LedgerService;
use App\Services\Support\PlanActualService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Annual budget vs actual, per income/expense account.
 *
 * Actuals come from LedgerService (income statement for the year); budgets are
 * the saved figures. Variance = budget − actual ("Diferencia" / remaining):
 * for an expense account a positive variance means under budget, for a revenue
 * account a positive variance means under target.
 *
 * Unbudgeted accounts show a budget of 0 (we pre-fill zeros so the shared
 * PlanActualService — which otherwise mirrors the actual — does not here).
 */
class BudgetService
{
    public const ANNUAL = 0;

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PlanActualService $planActual,
    ) {
    }

    /** @return array<string,mixed> */
    public function annual(int $userId, int $year): array
    {
        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);

        // Actuals per income/expense account (signed, from the income statement).
        $statement = $this->ledger->incomeStatement($userId, $from, $to);
        $actualsById = [];
        foreach (array_merge($statement['revenue'], $statement['expenses']) as $line) {
            $actualsById[$line['account_id']] = $line['amount'];
        }

        $accounts = ChartOfAccount::query()
            ->where('user_id', $userId)
            ->whereIn('type', ['income', 'expense'])
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $savedBudgets = Budget::query()
            ->where('user_id', $userId)
            ->where('year', $year)
            ->where('month', self::ANNUAL)
            ->get()
            ->keyBy('account_id');

        // Pre-fill planned with 0 for every shown account; saved budgets override.
        $plannedById = [];
        $actualForShown = [];
        foreach ($accounts as $a) {
            $plannedById[$a->id] = $savedBudgets->has($a->id) ? Money::of($savedBudgets[$a->id]->amount) : '0';
            $actualForShown[$a->id] = $actualsById[$a->id] ?? '0';
        }

        $merged = $this->planActual->merge($actualForShown, $plannedById);

        $revenue = [];
        $expenses = [];
        $tot = [
            'revenue' => ['budget' => '0', 'actual' => '0', 'variance' => '0'],
            'expenses' => ['budget' => '0', 'actual' => '0', 'variance' => '0'],
        ];

        foreach ($accounts as $a) {
            $m = $merged[$a->id];
            $row = [
                'account_id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'budget' => $m['planned'],
                'actual' => $m['actual'],
                'variance' => $m['variance'],
            ];

            $bucket = $a->type->value === 'income' ? 'revenue' : 'expenses';
            if ($bucket === 'income') {
                $revenue[] = $row;
            } else {
                $expenses[] = $row;
            }
            $tot[$bucket]['budget'] = Money::add($tot[$bucket]['budget'], $m['planned']);
            $tot[$bucket]['actual'] = Money::add($tot[$bucket]['actual'], $m['actual']);
            $tot[$bucket]['variance'] = Money::add($tot[$bucket]['variance'], $m['variance']);
        }

        $netBudget = Money::sub($tot['revenue']['budget'], $tot['expenses']['budget']);
        $netActual = Money::sub($tot['revenue']['actual'], $tot['expenses']['actual']);

        return [
            'year' => $year,
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'totals' => $tot + [
                'net' => [
                    'budget' => $netBudget,
                    'actual' => $netActual,
                    'variance' => Money::sub($netBudget, $netActual),
                ],
            ],
        ];
    }

    /**
     * Upsert annual budgets, then return the recomputed view.
     *
     * @param  array<int,array{account_id:int,amount:int|float|string}>  $rows
     * @return array<string,mixed>
     */
    public function saveAnnual(int $userId, int $year, array $rows): array
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $year, $rows) {
            foreach ($rows as $row) {
                Budget::updateOrCreate(
                    ['user_id' => $userId, 'year' => $year, 'month' => self::ANNUAL, 'account_id' => (int) $row['account_id']],
                    ['amount' => Money::of($row['amount'])],
                );
            }
        });

        return $this->annual($userId, $year);
    }

    /**
     * Monthly budget vs actual: a 12-column matrix per income/expense account.
     * budget[0..11] from saved rows (month 1–12); actual[0..11] from the income
     * statement per month; variance = budget − actual. Plus per-section and net
     * totals (per month and for the year).
     *
     * @return array<string,mixed>
     */
    public function monthly(int $userId, int $year): array
    {
        $accounts = ChartOfAccount::query()
            ->where('user_id', $userId)
            ->whereIn('type', ['income', 'expense'])
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        // Saved monthly budgets: account_id => [month => amount].
        $budgetByAcctMonth = [];
        foreach (Budget::query()->where('user_id', $userId)->where('year', $year)->whereBetween('month', [1, 12])->get() as $b) {
            $budgetByAcctMonth[$b->account_id][(int) $b->month] = Money::of($b->amount);
        }

        // Actuals per account per month (one income statement per month).
        $actualByAcctMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $from = sprintf('%04d-%02d-01', $year, $m);
            $to = Carbon::parse($from)->endOfMonth()->toDateString();
            $statement = $this->ledger->incomeStatement($userId, $from, $to);
            foreach (array_merge($statement['revenue'], $statement['expenses']) as $line) {
                $actualByAcctMonth[$line['account_id']][$m] = $line['amount'];
            }
        }

        $sections = ['revenue' => [], 'expenses' => []];
        $tot = [
            'revenue' => ['budget' => array_fill(0, 12, '0'), 'actual' => array_fill(0, 12, '0'), 'variance' => array_fill(0, 12, '0')],
            'expenses' => ['budget' => array_fill(0, 12, '0'), 'actual' => array_fill(0, 12, '0'), 'variance' => array_fill(0, 12, '0')],
        ];

        foreach ($accounts as $a) {
            $budget = [];
            $actual = [];
            $variance = [];
            $bt = $at = $vt = '0';

            for ($m = 1; $m <= 12; $m++) {
                // Normalize every cell to ledger scale (empty months → "0.0000").
                $b = Money::of($budgetByAcctMonth[$a->id][$m] ?? 0);
                $ac = Money::of($actualByAcctMonth[$a->id][$m] ?? 0);
                $v = Money::sub($b, $ac);
                $budget[] = $b;
                $actual[] = $ac;
                $variance[] = $v;
                $bt = Money::add($bt, $b);
                $at = Money::add($at, $ac);
                $vt = Money::add($vt, $v);
            }

            $bucket = $a->type->value === 'income' ? 'revenue' : 'expenses';
            $sections[$bucket][] = [
                'account_id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'budget' => $budget,
                'actual' => $actual,
                'variance' => $variance,
                'totals' => ['budget' => $bt, 'actual' => $at, 'variance' => $vt],
            ];

            for ($i = 0; $i < 12; $i++) {
                $tot[$bucket]['budget'][$i] = Money::add($tot[$bucket]['budget'][$i], $budget[$i]);
                $tot[$bucket]['actual'][$i] = Money::add($tot[$bucket]['actual'][$i], $actual[$i]);
                $tot[$bucket]['variance'][$i] = Money::add($tot[$bucket]['variance'][$i], $variance[$i]);
            }
        }

        // Net per month = revenue − expenses, for each series.
        $net = ['budget' => [], 'actual' => [], 'variance' => []];
        foreach (['budget', 'actual', 'variance'] as $k) {
            for ($i = 0; $i < 12; $i++) {
                $net[$k][] = Money::sub($tot['revenue'][$k][$i], $tot['expenses'][$k][$i]);
            }
        }

        $sumArr = fn (array $arr): string => array_reduce($arr, fn ($c, $x) => Money::add($c, $x), '0');
        $withTotal = fn (array $s): array => $s + ['total' => [
            'budget' => $sumArr($s['budget']), 'actual' => $sumArr($s['actual']), 'variance' => $sumArr($s['variance']),
        ]];

        return [
            'year' => $year,
            'months' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
            'revenue' => $sections['revenue'],
            'expenses' => $sections['expenses'],
            'totals' => [
                'revenue' => $withTotal($tot['revenue']),
                'expenses' => $withTotal($tot['expenses']),
                'net' => $withTotal($net),
            ],
        ];
    }

    /**
     * Upsert monthly budgets (month 1–12), then return the recomputed matrix.
     *
     * @param  array<int,array{account_id:int,month:int,amount:int|float|string}>  $rows
     * @return array<string,mixed>
     */
    public function saveMonthly(int $userId, int $year, array $rows): array
    {
        DB::transaction(function () use ($userId, $year, $rows) {
            foreach ($rows as $row) {
                Budget::updateOrCreate(
                    ['user_id' => $userId, 'year' => $year, 'month' => (int) $row['month'], 'account_id' => (int) $row['account_id']],
                    ['amount' => Money::of($row['amount'])],
                );
            }
        });

        return $this->monthly($userId, $year);
    }
}
