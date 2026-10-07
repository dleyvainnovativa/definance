<?php

namespace App\Services\Label;

use App\Models\Etiqueta;
use App\Services\Ledger\LedgerService;
use App\Support\Money;

/**
 * Rolls up ledger movement by label: each label's total is the sum of the
 * period movement of every account tagged with it (computed, never stored).
 * An account can carry several labels, so totals across labels can overlap.
 */
class LabelReportService
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function report(int $userId, string $from, string $to): array
    {
        $movements = $this->ledger->accountMovements($userId, $from, $to);

        $labels = Etiqueta::query()
            ->where('user_id', $userId)
            ->with('accounts:id,code,name')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($labels as $label) {
            $total = '0';
            $accounts = [];

            foreach ($label->accounts as $account) {
                $amount = $movements[$account->id] ?? '0';
                $total = Money::add($total, $amount);

                if (! Money::isZero($amount)) {
                    $accounts[] = [
                        'account_id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'amount' => $amount,
                    ];
                }
            }

            // biggest contributor first
            usort($accounts, fn ($a, $b) => Money::cmp((string) abs((float) $b['amount']), (string) abs((float) $a['amount'])));

            $rows[] = [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color,
                'total' => $total,
                'accounts' => $accounts,
            ];
        }

        // most active label first
        usort($rows, fn ($a, $b) => Money::cmp((string) abs((float) $b['total']), (string) abs((float) $a['total'])));

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ];
    }
}
