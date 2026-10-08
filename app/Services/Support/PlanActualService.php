<?php

namespace App\Services\Support;

use App\Support\Money;

/**
 * Shared "plan vs actual" merge used by planning modules (FEA, Budget,
 * Budget Monthly). Keeps one rule in one place: actuals come from the ledger,
 * a planned figure may override per key, and variance = planned − actual.
 *
 * It never reads or writes anything — pure merge over arrays keyed by id.
 */
class PlanActualService
{
    /**
     * @param  array<int|string,int|float|string|null>  $actualsById   computed actuals, keyed by id
     * @param  array<int|string,int|float|string|null>  $plannedById   saved plan, keyed by id (missing = use actual)
     * @return array<int|string,array{actual:string,planned:string,variance:string}>
     */
    public function merge(array $actualsById, array $plannedById): array
    {
        $keys = array_unique(array_merge(array_keys($actualsById), array_keys($plannedById)));

        $out = [];
        foreach ($keys as $key) {
            $actual = Money::of($actualsById[$key] ?? 0);
            // Absolute planned values (decision R3): an untouched line mirrors the
            // actual. Only numeric planned values are money — null, or a non-numeric
            // value (e.g. the FEA note carried in the same map), falls back to actual.
            $planned = array_key_exists($key, $plannedById) && is_numeric($plannedById[$key])
                ? Money::of($plannedById[$key])
                : $actual;

            $out[$key] = [
                'actual' => $actual,
                'planned' => $planned,
                'variance' => Money::sub($planned, $actual),
            ];
        }

        return $out;
    }
}
