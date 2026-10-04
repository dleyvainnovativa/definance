<?php

namespace App\Support;

/**
 * Exact decimal arithmetic for money, at the ledger scale (4 dp).
 *
 * All amounts are handled as strings via bcmath so there is never a binary
 * float rounding error in a balance. Requires ext-bcmath.
 */
final class Money
{
    public const SCALE = 4;

    public static function of(int|float|string|null $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    /** -1 if a<b, 0 if equal, 1 if a>b (at ledger scale). */
    public static function cmp(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isZero(string $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isPositive(string $a): bool
    {
        return self::cmp($a, '0') === 1;
    }

    public static function isNegative(string $a): bool
    {
        return self::cmp($a, '0') === -1;
    }

    /** @param  iterable<int|float|string|null>  $values */
    public static function sum(iterable $values): string
    {
        $total = '0';
        foreach ($values as $v) {
            $total = self::add($total, self::of($v));
        }

        return self::of($total);
    }
}
