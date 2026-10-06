<?php

namespace App\Services\CashFlow;

use App\Models\CashFlowAdjustment;
use App\Services\Ledger\LedgerService;
use App\Services\Support\PlanActualService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FEA — Flujo de Efectivo Ajustado (managed/adjusted cash flow).
 *
 * An editable monthly cash-flow projection built on the computed actual cash
 * flow (LedgerService::cashFlow). Per cash account the user may save an absolute
 * planned NET movement (decision R3); an untouched account mirrors its actual.
 *
 * Carry-forward (so "next month's opening = this month's adjusted closing",
 * while staying anchored to real ledger balances):
 *
 *   opening_adjusted(p)  = actual_opening(p) + Σ_{q<p, saved}(planned(q) − actual_net(q))
 *   closing_adjusted(p)  = opening_adjusted(p) + planned_net(p)
 *
 * With no saved adjustments the sum is 0, so the projection equals reality.
 * This is computed (never stored); it can be snapshotted later as a read cache.
 */
class ManagedCashFlowService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PlanActualService $planActual,
    ) {
    }

    /**
     * The adjusted cash flow for one month (YYYY-MM): per cash account, the
     * adjusted opening, actual net, planned net, variance and adjusted closing.
     *
     * @return array<string,mixed>
     */
    public function get(int $userId, string $period): array
    {
        [$from, $to] = $this->range($period);

        $cash = $this->ledger->cashFlow($userId, $from, $to);
        $saved = $this->savedPlanned($userId, $period);
        $priorEffect = $this->priorAdjustmentEffect($userId, $period);

        // actual NET per account (inflow − outflow) for the plan/actual merge.
        $actualNet = [];
        $openingActual = [];
        foreach ($cash['rows'] as $r) {
            $actualNet[$r['account_id']] = Money::sub($r['inflow'], $r['outflow']);
            $openingActual[$r['account_id']] = $r['opening'];
        }

        $merged = $this->planActual->merge($actualNet, $saved);

        $rows = [];
        $totOpening = $totActual = $totPlanned = $totVariance = $totClosing = '0';

        foreach ($cash['rows'] as $r) {
            $id = $r['account_id'];
            $m = $merged[$id];

            $opening = Money::add($openingActual[$id], $priorEffect[$id] ?? '0');
            $closing = Money::add($opening, $m['planned']);

            $rows[] = [
                'account_id' => $id,
                'code' => $r['code'],
                'name' => $r['name'],
                'opening' => $opening,          // adjusted opening (carried)
                'actual' => $m['actual'],       // actual net movement this month
                'planned' => $m['planned'],     // planned net movement (editable)
                'variance' => $m['variance'],   // planned − actual
                'closing' => $closing,          // adjusted closing (opening + planned)
                'note' => $saved[$id.'__note'] ?? null,
            ];

            $totOpening = Money::add($totOpening, $opening);
            $totActual = Money::add($totActual, $m['actual']);
            $totPlanned = Money::add($totPlanned, $m['planned']);
            $totVariance = Money::add($totVariance, $m['variance']);
            $totClosing = Money::add($totClosing, $closing);
        }

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'opening' => $totOpening,
                'actual' => $totActual,
                'planned' => $totPlanned,
                'variance' => $totVariance,
                'closing' => $totClosing,
            ],
            'has_cash_accounts' => $rows !== [],
        ];
    }

    /**
     * Upsert the planned amounts for a month, then return the recomputed view.
     *
     * @param  array<int,array{account_id:int,planned_amount:int|float|string,note?:?string}>  $rows
     * @return array<string,mixed>
     */
    public function save(int $userId, string $period, array $rows): array
    {
        DB::transaction(function () use ($userId, $period, $rows) {
            foreach ($rows as $row) {
                CashFlowAdjustment::updateOrCreate(
                    ['user_id' => $userId, 'period' => $period, 'account_id' => (int) $row['account_id']],
                    ['planned_amount' => Money::of($row['planned_amount']), 'note' => $row['note'] ?? null],
                );
            }
        });

        return $this->get($userId, $period);
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Saved planned amounts for the period, keyed by account_id; notes keyed by
     * "{account_id}__note".
     *
     * @return array<int|string,string|null>
     */
    private function savedPlanned(int $userId, string $period): array
    {
        $out = [];
        $rows = CashFlowAdjustment::query()
            ->where('user_id', $userId)
            ->where('period', $period)
            ->get();

        foreach ($rows as $adj) {
            $out[$adj->account_id] = Money::of($adj->planned_amount);
            $out[$adj->account_id.'__note'] = $adj->note;
        }

        return $out;
    }

    /**
     * Σ over all prior saved periods of (planned − actual_net) per account.
     * Only periods the user explicitly adjusted contribute (others net to 0).
     *
     * @return array<int,string>
     */
    private function priorAdjustmentEffect(int $userId, string $period): array
    {
        $priorPeriods = CashFlowAdjustment::query()
            ->where('user_id', $userId)
            ->where('period', '<', $period)
            ->distinct()
            ->orderBy('period')
            ->pluck('period');

        $effect = [];

        foreach ($priorPeriods as $p) {
            [$from, $to] = $this->range($p);
            $cash = $this->ledger->cashFlow($userId, $from, $to);

            $actualNet = [];
            foreach ($cash['rows'] as $r) {
                $actualNet[$r['account_id']] = Money::sub($r['inflow'], $r['outflow']);
            }

            $saved = CashFlowAdjustment::query()
                ->where('user_id', $userId)
                ->where('period', $p)
                ->get();

            foreach ($saved as $adj) {
                $diff = Money::sub(Money::of($adj->planned_amount), $actualNet[$adj->account_id] ?? '0');
                $effect[$adj->account_id] = Money::add($effect[$adj->account_id] ?? '0', $diff);
            }
        }

        return $effect;
    }

    /** @return array{0:string,1:string} first and last day of a YYYY-MM month */
    private function range(string $period): array
    {
        $start = Carbon::parse($period.'-01')->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }
}
