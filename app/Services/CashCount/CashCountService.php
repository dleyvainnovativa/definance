<?php

namespace App\Services\CashCount;

use App\Models\CashCount;
use App\Models\CashCountSetting;
use App\Models\ChartOfAccount;
use App\Services\Ledger\LedgerService;
use App\Services\Ledger\PostingService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arqueo de caja — count real cash against the computed book balance of each
 * cash account. Differences post ONE balanced adjusting entry through
 * PostingService:
 *   - overage (counted > book): debit the cash account, credit "Diferencia en Arqueo"
 *   - shortage (counted < book): credit the cash account, debit "Diferencia en Arqueo"
 * Overages and shortages are aggregated to the single difference account (R5)
 * as gross credit/debit legs, so the entry is balanced and auditable and never
 * looks like a cash-to-cash transfer. Every count is recorded for history (R6).
 */
class CashCountService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PostingService $posting,
    ) {
    }

    /** Resolved settings for the UI. @return array<string,mixed> */
    public function settings(int $userId): array
    {
        $s = CashCountSetting::query()->where('user_id', $userId)->first();
        $diff = $s?->difference_account_id
            ? ChartOfAccount::query()->where('user_id', $userId)->find($s->difference_account_id)
            : null;

        return [
            'difference_account' => $diff ? ['id' => $diff->id, 'code' => $diff->code, 'name' => $diff->name] : null,
            'counted_account_ids' => $s?->counted_account_ids,
        ];
    }

    /**
     * The counted cash accounts: the configured subset, or all `is_cash`.
     *
     * @param  array<int,int>|null  $ids
     * @return Collection<int,ChartOfAccount>
     */
    public function countedAccounts(int $userId, ?array $ids): Collection
    {
        $q = ChartOfAccount::query()->where('user_id', $userId)->where('is_postable', true);
        ! empty($ids) ? $q->whereIn('id', $ids) : $q->where('is_cash', true);

        return $q->orderBy('code')->get(['id', 'code', 'name']);
    }

    /** Book balances to count as of a date (counted defaults to book). @return array<string,mixed> */
    public function preview(int $userId, string $date): array
    {
        $settings = $this->settings($userId);
        $accounts = $this->countedAccounts($userId, $settings['counted_account_ids']);

        $rows = [];
        foreach ($accounts as $a) {
            $book = $this->ledger->balance($userId, $a->id, $date);
            $rows[] = [
                'account_id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'book' => $book,
                'counted' => $book,          // default: no difference until edited
                'difference' => Money::of(0),
            ];
        }

        return [
            'date' => $date,
            'rows' => $rows,
            'difference_account' => $settings['difference_account'],
            'configured' => $settings['difference_account'] !== null,
            'recent' => $this->recent($userId),
        ];
    }

    /**
     * Record a count and post the adjusting entry (if any).
     *
     * @param  array<int,array{account_id:int,counted:int|float|string}>  $counts
     * @return array<string,mixed>
     */
    public function save(int $userId, string $date, array $counts, ?string $note = null): array
    {
        $setting = CashCountSetting::query()->where('user_id', $userId)->first();
        $diffAccount = $setting?->difference_account_id
            ? ChartOfAccount::query()->where('user_id', $userId)->where('is_postable', true)->find($setting->difference_account_id)
            : null;

        if (! $diffAccount) {
            throw new \DomainException('Configura la cuenta de "Diferencia en Arqueo" antes de guardar.');
        }

        $countedById = [];
        $denomsById = [];
        foreach ($counts as $c) {
            $aid = (int) $c['account_id'];
            $denoms = $c['denominations'] ?? null;

            if (! empty($denoms)) {
                // Recompute the counted total from the MXN denomination grid
                // (value × qty) server-side — the grid is the source of truth.
                $sum = '0';
                $clean = [];
                foreach ($denoms as $d) {
                    $qty = (int) $d['qty'];
                    if ($qty <= 0) {
                        continue;
                    }
                    $sum = Money::add($sum, bcmul((string) $d['value'], (string) $qty, Money::SCALE));
                    $clean[] = ['value' => Money::of($d['value']), 'qty' => $qty];
                }
                $countedById[$aid] = Money::of($sum);
                $denomsById[$aid] = $clean;
            } else {
                $countedById[$aid] = Money::of($c['counted']);
            }
        }

        $accounts = $this->countedAccounts($userId, $setting->counted_account_ids);

        $breakdown = [];
        $legs = [];
        $sumOver = '0';
        $sumShort = '0';
        $totalDiff = '0';

        foreach ($accounts as $a) {
            if (! array_key_exists($a->id, $countedById)) {
                continue;
            }

            $book = $this->ledger->balance($userId, $a->id, $date);
            $counted = $countedById[$a->id];
            $diff = Money::sub($counted, $book);
            $totalDiff = Money::add($totalDiff, $diff);

            $breakdown[] = [
                'account_id' => $a->id, 'code' => $a->code, 'name' => $a->name,
                'book' => $book, 'counted' => $counted, 'difference' => $diff,
                'denominations' => $denomsById[$a->id] ?? null,
            ];

            if (Money::isPositive($diff)) {
                $legs[] = ['account_id' => $a->id, 'debit' => $diff];
                $sumOver = Money::add($sumOver, $diff);
            } elseif (Money::isNegative($diff)) {
                $short = Money::sub($book, $counted);
                $legs[] = ['account_id' => $a->id, 'credit' => $short];
                $sumShort = Money::add($sumShort, $short);
            }
        }

        if (Money::isPositive($sumOver)) {
            $legs[] = ['account_id' => $diffAccount->id, 'credit' => $sumOver];
        }
        if (Money::isPositive($sumShort)) {
            $legs[] = ['account_id' => $diffAccount->id, 'debit' => $sumShort];
        }

        return DB::transaction(function () use ($userId, $date, $legs, $breakdown, $totalDiff, $note) {
            $entry = count($legs) >= 2
                ? $this->posting->post(
                    userId: $userId,
                    entryDate: $date,
                    legs: $legs,
                    description: "Arqueo de caja {$date}",
                    reference: "arqueo:{$date}",
                )
                : null;

            $count = CashCount::create([
                'user_id' => $userId,
                'count_date' => $date,
                'journal_entry_id' => $entry?->id,
                'total_difference' => $totalDiff,
                'breakdown' => $breakdown,
                'note' => $note,
            ]);

            return [
                'posted' => $entry !== null,
                'entry_id' => $entry?->id,
                'total_difference' => $totalDiff,
                'count' => $this->present($count),
            ];
        });
    }

    /**
     * Save settings (difference account + optional counted subset).
     *
     * @param  array<int,int>|null  $countedAccountIds
     * @return array<string,mixed>
     */
    public function saveSettings(int $userId, ?int $differenceAccountId, ?array $countedAccountIds): array
    {
        CashCountSetting::updateOrCreate(
            ['user_id' => $userId],
            ['difference_account_id' => $differenceAccountId, 'counted_account_ids' => $countedAccountIds],
        );

        return $this->settings($userId);
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $userId, int $limit = 5): array
    {
        return CashCount::query()
            ->where('user_id', $userId)
            ->orderByDesc('count_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (CashCount $c) => $this->present($c))
            ->all();
    }

    /** @return array<string,mixed> */
    private function present(CashCount $c): array
    {
        return [
            'id' => $c->id,
            'count_date' => $c->count_date?->toDateString(),
            'total_difference' => Money::of($c->total_difference),
            'journal_entry_id' => $c->journal_entry_id,
            'breakdown' => $c->breakdown,
            'note' => $c->note,
        ];
    }
}
