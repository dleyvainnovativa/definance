<?php

namespace App\Services\Import;

use App\Models\ChartOfAccount;
use App\Services\Ledger\PostingService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bulk import of journal entries.
 *
 * The client sends a flat list of rows (one leg per row) grouped by a `group`
 * key; this service resolves accounts by code within the user's chart, groups
 * the rows into balanced entries, validates each group with the same rules the
 * single-entry path enforces (Owned + postable account, debit XOR credit > 0,
 * Σdebits = Σcredits), and posts each via PostingService — the single
 * authoritative write path. Nothing here recalculates or stores balances.
 *
 * Two modes:
 *   - all_or_nothing: if any group is invalid, post nothing; if all valid, post
 *     them inside one transaction (rolled back on any posting failure).
 *   - skip_invalid:   post every valid group (each in its own transaction),
 *     skip the invalid ones, and report both.
 */
class ImportService
{
    public const MODE_ALL_OR_NOTHING = 'all_or_nothing';

    public const MODE_SKIP_INVALID = 'skip_invalid';

    public function __construct(private readonly PostingService $posting)
    {
    }

    /**
     * Resolve + group + validate without writing anything (dry run / preview).
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<string,mixed>
     */
    public function preview(int $userId, array $rows): array
    {
        $groups = $this->buildGroups($userId, $rows);

        $valid = array_filter($groups, fn ($g) => $g['valid']);

        return [
            'groups' => array_values($groups),
            'total_groups' => count($groups),
            'valid_count' => count($valid),
            'invalid_count' => count($groups) - count($valid),
            'can_import' => count($valid) > 0,
        ];
    }

    /**
     * Validate then post. Respects the chosen mode.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<string,mixed>
     */
    public function import(int $userId, array $rows, string $mode): array
    {
        $groups = $this->buildGroups($userId, $rows);
        $hasInvalid = (bool) array_filter($groups, fn ($g) => ! $g['valid']);

        $posted = 0;
        $skipped = 0;
        $entryIds = [];

        if ($mode === self::MODE_ALL_OR_NOTHING) {
            if ($hasInvalid) {
                // Post nothing; report every group so the UI can show the errors.
                return $this->result($mode, 0, count($groups), [], $groups, false);
            }

            try {
                DB::transaction(function () use ($userId, &$groups, &$posted, &$entryIds) {
                    foreach ($groups as $i => $group) {
                        $entry = $this->postGroup($userId, $group);
                        $groups[$i]['status'] = 'posted';
                        $groups[$i]['entry_id'] = $entry->id;
                        $entryIds[] = $entry->id;
                        $posted++;
                    }
                });
            } catch (\Throwable $e) {
                // A balanced, pre-validated set should never reach here; if it
                // does the whole batch was rolled back — report it honestly.
                return $this->result($mode, 0, count($groups), [], $groups, false, $e->getMessage());
            }

            return $this->result($mode, $posted, 0, $entryIds, $groups, true);
        }

        // skip_invalid: each valid group posted independently.
        foreach ($groups as $i => $group) {
            if (! $group['valid']) {
                $groups[$i]['status'] = 'skipped';
                $skipped++;

                continue;
            }

            try {
                $entry = $this->postGroup($userId, $group);
                $groups[$i]['status'] = 'posted';
                $groups[$i]['entry_id'] = $entry->id;
                $entryIds[] = $entry->id;
                $posted++;
            } catch (\Throwable $e) {
                $groups[$i]['status'] = 'failed';
                $groups[$i]['errors'][] = $e->getMessage();
                $skipped++;
            }
        }

        return $this->result($mode, $posted, $skipped, $entryIds, $groups, true);
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Resolve accounts, group rows by `group`, and validate each group.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>  one entry per group, in input order
     */
    private function buildGroups(int $userId, array $rows): array
    {
        $accountsByCode = ChartOfAccount::query()
            ->where('user_id', $userId)
            ->get()
            ->keyBy(fn ($a) => (string) $a->code);

        $grouped = [];
        $order = [];

        foreach ($rows as $index => $row) {
            $key = trim((string) ($row['group'] ?? ''));
            if ($key === '') {
                // Ungrouped rows each become their own single-leg group so the
                // "at least two legs" rule flags them clearly.
                $key = '__row_'.$index;
            }

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'group' => $key,
                    'entry_date' => null,
                    'description' => null,
                    'reference' => null,
                    'legs' => [],
                    'errors' => [],
                ];
                $order[] = $key;
            }

            // Entry-level fields: first non-empty wins across the group's rows.
            foreach (['entry_date', 'description', 'reference'] as $field) {
                $val = trim((string) ($row[$field] ?? ''));
                if ($val !== '' && ($grouped[$key][$field] === null || $grouped[$key][$field] === '')) {
                    $grouped[$key][$field] = $val;
                }
            }

            $grouped[$key]['legs'][] = $this->resolveLeg($row, $accountsByCode, $index + 1);
        }

        $result = [];
        foreach ($order as $key) {
            $result[] = $this->validateGroup($grouped[$key]);
        }

        return $result;
    }

    /**
     * Turn one raw row into a resolved leg (account looked up by code).
     *
     * @param  array<string,mixed>  $row
     * @param  \Illuminate\Support\Collection<string,ChartOfAccount>  $accountsByCode
     * @return array<string,mixed>
     */
    private function resolveLeg(array $row, $accountsByCode, int $rowNumber): array
    {
        $code = trim((string) ($row['account_code'] ?? ''));
        $account = $code === '' ? null : $accountsByCode->get($code);

        $debit = Money::of($row['debit'] ?? null);
        $credit = Money::of($row['credit'] ?? null);

        return [
            'row' => $rowNumber,
            'account_code' => $code,
            'account_id' => $account?->id,
            'account_name' => $account?->name,
            'account_postable' => $account ? ($account->is_postable && $account->is_active) : null,
            'debit' => Money::isPositive($debit) ? $debit : null,
            'credit' => Money::isPositive($credit) ? $credit : null,
            'line_description' => trim((string) ($row['line_description'] ?? '')) ?: null,
            '_debit_raw' => $debit,
            '_credit_raw' => $credit,
        ];
    }

    /**
     * Validate one group; attach errors and a `valid` flag + computed totals.
     *
     * @param  array<string,mixed>  $group
     * @return array<string,mixed>
     */
    private function validateGroup(array $group): array
    {
        $errors = [];

        // Date.
        if (($group['entry_date'] ?? null) === null) {
            $errors[] = 'Falta la fecha de la póliza.';
        } elseif (! $this->isValidDate($group['entry_date'])) {
            $errors[] = "Fecha inválida: \"{$group['entry_date']}\" (usa AAAA-MM-DD).";
        }

        // Legs.
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($group['legs'] as $leg) {
            $totalDebit = Money::add($totalDebit, $leg['_debit_raw']);
            $totalCredit = Money::add($totalCredit, $leg['_credit_raw']);

            if ($leg['account_code'] === '') {
                $errors[] = "Fila {$leg['row']}: falta el código de cuenta.";
            } elseif ($leg['account_id'] === null) {
                $errors[] = "Fila {$leg['row']}: la cuenta \"{$leg['account_code']}\" no existe en tu catálogo.";
            } elseif ($leg['account_postable'] === false) {
                $errors[] = "Fila {$leg['row']}: la cuenta \"{$leg['account_code']}\" no recibe movimientos.";
            }

            // Exactly one side, strictly positive.
            if (Money::isPositive($leg['_debit_raw']) === Money::isPositive($leg['_credit_raw'])) {
                $errors[] = "Fila {$leg['row']}: debe tener exactamente un cargo o un abono (> 0).";
            }
            if (Money::isNegative($leg['_debit_raw']) || Money::isNegative($leg['_credit_raw'])) {
                $errors[] = "Fila {$leg['row']}: los importes no pueden ser negativos.";
            }
        }

        if (count($group['legs']) < 2) {
            $errors[] = 'Una póliza debe tener al menos dos líneas (cargo y abono).';
        }

        if (Money::cmp($totalDebit, $totalCredit) !== 0) {
            $errors[] = "No cuadra: cargos {$totalDebit} ≠ abonos {$totalCredit}.";
        }

        // Strip internal scratch fields from the legs before returning.
        $legs = array_map(function ($leg) {
            unset($leg['_debit_raw'], $leg['_credit_raw'], $leg['account_postable']);

            return $leg;
        }, $group['legs']);

        return [
            'group' => $group['group'],
            'entry_date' => $group['entry_date'],
            'description' => $group['description'],
            'reference' => $group['reference'],
            'legs' => $legs,
            'totals' => ['debit' => $totalDebit, 'credit' => $totalCredit],
            'errors' => $errors,
            'valid' => $errors === [],
            'status' => 'pending',
            'entry_id' => null,
        ];
    }

    /** Post one validated group through the authoritative PostingService. */
    private function postGroup(int $userId, array $group): \App\Models\JournalEntry
    {
        $legs = array_map(fn ($leg) => [
            'account_id' => $leg['account_id'],
            'debit' => $leg['debit'],
            'credit' => $leg['credit'],
            'description' => $leg['line_description'],
        ], $group['legs']);

        return $this->posting->post(
            userId: $userId,
            entryDate: $group['entry_date'],
            legs: $legs,
            description: $group['description'],
            reference: $group['reference'],
        );
    }

    private function isValidDate(string $value): bool
    {
        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<int,int>  $entryIds
     * @param  array<int,array<string,mixed>>  $groups
     * @return array<string,mixed>
     */
    private function result(string $mode, int $posted, int $skipped, array $entryIds, array $groups, bool $attempted, ?string $error = null): array
    {
        return array_filter([
            'mode' => $mode,
            'attempted' => $attempted,
            'posted' => $posted,
            'skipped' => $skipped,
            'entry_ids' => $entryIds,
            'groups' => array_values($groups),
            'error' => $error,
        ], fn ($v) => $v !== null);
    }
}
