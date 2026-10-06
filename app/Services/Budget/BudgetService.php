<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Services\Ledger\LedgerService;
use App\Services\Support\PlanActualService;
use App\Support\Money;

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
}
