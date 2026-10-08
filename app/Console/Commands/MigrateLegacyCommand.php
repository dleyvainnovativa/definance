<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\EntryStatus;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-time ETL from the old DeFinance schema into the rebuilt ledger (rev 3).
 *
 * Validated against the legacy dump (user 1): reproduces the old app's Activos,
 * Pasivos, Ingresos/Egresos and Capital, with CARTERA = $1,301.00.
 *
 * THREE things the old data needs, each handled here:
 *
 *  1) ENTRY ORIENTATION. The legacy `journal_entry_lines` store debit/credit in
 *     an entry_type-dependent orientation; the old `journal_voucher` view is what
 *     normalized it (expense / asset_acquisition swap sides). We replicate that
 *     mapping in PHP from the RAW lines — we do NOT read the view (it was found
 *     pinned to a stale schema). Importing raw without the swap was the bug that
 *     ballooned assets and made Egresos negative.
 *
 *  2) OPENING SEEDS. Legacy openings are AUTO monthly carry-forwards. We keep,
 *     per account, only the EARLIEST opening and only when it predates the
 *     account's first transaction (genesis seed), balanced via `Apertura` (399).
 *     Apertura also absorbs the legacy opening imbalance (the old app's ~$9k
 *     descuadre), so the new ledger balances where the old one silently did not.
 *
 *  3) ANNUAL CLOSE (cierre de ejercicio). The old app resets nominal accounts
 *     each year and folds the result into equity (`300.2`). We post a closing
 *     entry at each completed year-end that zeroes income/expense into the result
 *     account, so a year's income statement is that year's activity and equity
 *     carries accumulated results. The latest (current) year stays open.
 *
 * Safe to preview with --dry-run (everything rolls back).
 */
class MigrateLegacyCommand extends Command
{
    protected $signature = 'definance:migrate-legacy
        {--connection=legacy : source DB connection}
        {--user= : limit to one legacy user id}
        {--dry-run : process then roll back, reporting only}
        {--fresh : clear the target ledger first}
        {--force : skip confirmation for --fresh}
        {--result-code=300.2 : equity account code that receives the yearly result}';

    protected $description = 'Migrate the old DeFinance database into the rebuilt ledger.';

    private ConnectionInterface $legacy;

    /** newAccountId => AccountType, for nominal detection during posting. */
    private array $typeByNewId = [];

    public function handle(): int
    {
        $source = (string) $this->option('connection');
        if ($source === 'legacy' && ! config('database.connections.legacy')) {
            config(['database.connections.legacy' => config('legacy')]);
        }
        $this->legacy = DB::connection($source);

        $dryRun = (bool) $this->option('dry-run');
        $summary = [];

        DB::beginTransaction();
        try {
            if ($this->option('fresh')) {
                if (! $this->option('force') && ! $this->confirm('This clears all accounts and journal entries in the target. Continue?')) {
                    DB::rollBack();

                    return self::FAILURE;
                }
                DB::table('journal_entry_lines')->delete();
                DB::table('journal_entries')->delete();
                DB::table('chart_of_accounts')->delete();
            }

            $users = $this->legacy->table('users')
                ->when($this->option('user'), fn($q) => $q->where('id', $this->option('user')))
                ->get();

            $migratedUserIds = [];

            foreach ($users as $lu) {
                $newUser = User::firstOrCreate(
                    ['firebase_uid' => $lu->firebase_uid],
                    ['name' => $lu->name ?? null, 'email' => $lu->email ?? null],
                );
                $migratedUserIds[] = $newUser->id;

                [$accMap, $aperturaId] = $this->migrateAccounts((int) $lu->id, $newUser->id);
                $firstTxn = $this->firstTransactionDates((int) $lu->id);

                $seeds = $this->migrateSeeds((int) $lu->id, $newUser->id, $accMap, $aperturaId, $firstTxn);
                // $nominalByYear[year][newAccountId] = raw (Σdebit − Σcredit) string
                $tx = $this->migrateTransactions((int) $lu->id, $newUser->id, $accMap, $nominalByYear);
                $closes = $this->postAnnualCloses($newUser->id, $nominalByYear ?? []);

                $totals = $this->reconcile($newUser->id);
                $summary[] = [
                    $lu->id . ' → ' . $newUser->id,
                    count($accMap),
                    $tx['posted'],
                    $seeds['seeded'],
                    $seeds['dropped'],
                    $tx['skipped'],
                    $closes,
                    number_format($totals['debit'], 2),
                    number_format($totals['credit'], 2),
                    $totals['balanced'] ? 'yes' : 'NO',
                ];
            }

            $this->table(
                ['User', 'Accts', 'Tx', 'Seeds', 'DropOpen', 'Skipped', 'Closes', 'Σ Debit', 'Σ Credit', 'Balanced'],
                $summary,
            );

            $imbalanced = $this->imbalancedEntries($migratedUserIds);
            if ($imbalanced > 0) {
                $this->error("{$imbalanced} migrated entrie(s) do not balance — investigate before committing.");
            } else {
                $this->info('Every migrated entry balances.');
            }

            if ($dryRun) {
                DB::rollBack();
                $this->warn('Dry run: all changes rolled back. Re-run without --dry-run to apply.');
            } else {
                DB::commit();
                $this->info('Migration committed.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Migration failed and was rolled back: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array{0:array<int,int>,1:int} [oldAccountId => newAccountId], aperturaAccountId
     */
    private function migrateAccounts(int $oldUserId, int $newUserId): array
    {
        $legacyAccounts = $this->legacy->table('chart_of_accounts')
            ->where('user_id', $oldUserId)
            ->orderByRaw('LENGTH(code), code')
            ->get();

        $parentIds = $legacyAccounts->pluck('parent_id')->filter()->map(fn($v) => (int) $v)->unique()->flip();

        $map = [];
        foreach ($legacyAccounts as $a) {
            $isLeaf = ! $parentIds->has((int) $a->id);
            $isCash = $isLeaf && (str_starts_with($a->code, '100.1') || str_starts_with($a->code, '100.2'));
            $type = AccountType::from($a->type);

            $new = ChartOfAccount::create([
                'user_id' => $newUserId,
                'parent_id' => $a->parent_id ? ($map[(int) $a->parent_id] ?? null) : null,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $type,
                'is_postable' => $isLeaf,
                'is_active' => true,
                'is_cash' => $isCash,
                'is_editable' => (bool) $a->is_editable,
                'is_deletable' => (bool) $a->is_deletable,
            ]);
            $map[(int) $a->id] = $new->id;
            $this->typeByNewId[$new->id] = $type;
        }

        $equityRoot = ChartOfAccount::where('user_id', $newUserId)
            ->where('type', AccountType::Equity)->whereNull('parent_id')->value('id');

        $apertura = ChartOfAccount::firstOrCreate(
            ['user_id' => $newUserId, 'code' => '399'],
            [
                'name' => 'Apertura / Saldos Iniciales',
                'type' => AccountType::Equity,
                'parent_id' => $equityRoot,
                'is_postable' => true,
                'is_active' => true,
                'is_editable' => false,
                'is_deletable' => false,
            ],
        );
        $this->typeByNewId[$apertura->id] = AccountType::Equity;

        return [$map, $apertura->id];
    }

    /**
     * Earliest real-transaction date per legacy account id (gates the seeds).
     *
     * @return array<int,string>
     */
    private function firstTransactionDates(int $oldUserId): array
    {
        $rows = $this->legacy->table('journal_entries as je')
            ->join('journal_entry_lines as l', 'l.journal_entry_id', '=', 'je.id')
            ->where('je.user_id', $oldUserId)
            ->whereNotIn('je.entry_type', ['opening_balance', 'opening_balance_credit'])
            ->get(['l.chart_of_account_id as acct', 'je.entry_date']);

        $first = [];
        foreach ($rows as $r) {
            if ($r->acct === null) {
                continue;
            }
            $aid = (int) $r->acct;
            $date = Carbon::parse($r->entry_date)->toDateString();
            if (! isset($first[$aid]) || $date < $first[$aid]) {
                $first[$aid] = $date;
            }
        }

        return $first;
    }

    /**
     * Genesis seeds: per account the earliest opening, kept only when it predates
     * the first transaction; balanced via Apertura (which also absorbs the legacy
     * opening imbalance so the new ledger balances).
     *
     * @param  array<int,int>     $accMap
     * @param  array<int,string>  $firstTxn
     * @return array{seeded:int,dropped:int,skipped:int}
     */
    private function migrateSeeds(int $oldUserId, int $newUserId, array $accMap, int $aperturaId, array $firstTxn): array
    {
        $stats = ['seeded' => 0, 'dropped' => 0, 'skipped' => 0];

        $openings = $this->legacy->table('journal_entries as je')
            ->join('journal_entry_lines as l', 'l.journal_entry_id', '=', 'je.id')
            ->where('je.user_id', $oldUserId)
            ->whereIn('je.entry_type', ['opening_balance', 'opening_balance_credit'])
            ->orderBy('je.entry_date')
            ->orderBy('je.id')
            ->get(['je.entry_date', 'l.chart_of_account_id as acct', 'l.debit', 'l.credit']);

        $earliest = [];
        foreach ($openings as $o) {
            if ($o->acct === null) {
                continue;
            }
            $aid = (int) $o->acct;
            $date = Carbon::parse($o->entry_date)->toDateString();
            if (! isset($earliest[$aid]) || $date < $earliest[$aid]['date']) {
                $earliest[$aid] = ['date' => $date, 'debit' => $o->debit, 'credit' => $o->credit];
            } else {
                $stats['dropped']++;
            }
        }

        foreach ($earliest as $aid => $op) {
            $firstTx = $firstTxn[$aid] ?? null;
            if ($firstTx !== null && $op['date'] > $firstTx) {
                $stats['dropped']++;

                continue;
            }

            $accNew = $accMap[$aid] ?? null;
            $debitSide = $op['debit'] !== null && (float) $op['debit'] > 0;
            $amount = $debitSide ? $op['debit'] : $op['credit'];

            if ($accNew === null || $amount === null || (float) $amount <= 0) {
                $stats['skipped']++;

                continue;
            }

            $entry = $this->createEntry($newUserId, $op['date'], 'Saldo inicial', 'saldo-inicial');
            $entry->lines()->create($this->lineEntry($newUserId, $accNew, $debitSide ? $amount : null, $debitSide ? null : $amount));
            $entry->lines()->create($this->lineEntry($newUserId, $aperturaId, $debitSide ? null : $amount, $debitSide ? $amount : null));
            $stats['seeded']++;
        }

        return $stats;
    }

    /**
     * Real movements from RAW legacy lines, with the entry_type orientation
     * applied in PHP. Accumulates nominal (income/expense) raw net per year for
     * the annual close.
     *
     * @param  array<int,int>  $accMap
     * @param  array<int,array<int,string>>|null  $nominalByYear  out: [year][newAcctId] => Σdebit−Σcredit
     * @return array{posted:int,skipped:int}
     */
    private function migrateTransactions(int $oldUserId, int $newUserId, array $accMap, ?array &$nominalByYear): array
    {
        $nominalByYear = [];
        $stats = ['posted' => 0, 'skipped' => 0];

        $entries = $this->legacy->table('journal_entries')
            ->where('user_id', $oldUserId)
            ->whereNotIn('entry_type', ['opening_balance', 'opening_balance_credit'])
            ->orderBy('entry_date')->orderBy('id')
            ->get();

        $lines = $this->legacy->table('journal_entry_lines')
            ->whereIn('journal_entry_id', $entries->pluck('id'))
            ->get()
            ->groupBy('journal_entry_id');

        foreach ($entries as $e) {
            $ls = $lines->get($e->id, collect());

            // Debit-side and credit-side legs as stored (legacy orientation).
            $dl = $ls->first(fn($l) => $l->debit !== null && (float) $l->debit > 0);
            $cl = $ls->first(fn($l) => $l->credit !== null && (float) $l->credit > 0);
            if (! $dl || ! $cl) {
                $stats['skipped']++;   // single-sided / malformed
                continue;
            }

            $da = $accMap[(int) $dl->chart_of_account_id] ?? null;  // debit-line account
            $ca = $accMap[(int) $cl->chart_of_account_id] ?? null;  // credit-line account
            $amount = Money::of($dl->debit);

            // Normalize orientation (replicates journal_voucher):
            //   income / transfer / default → Dr da, Cr ca
            //   expense / asset_acquisition → Dr ca, Cr da
            if (in_array($e->entry_type, ['expense', 'asset_acquisition'], true)) {
                [$drId, $crId] = [$ca, $da];
            } else {
                [$drId, $crId] = [$da, $ca];
            }

            if ($drId === null || $crId === null || $drId === $crId || Money::cmp($amount, '0') <= 0) {
                $stats['skipped']++;   // unresolved or self-referencing (e.g. legacy #39037)
                continue;
            }

            $entry = $this->createEntry($newUserId, Carbon::parse($e->entry_date)->toDateString(), $e->description, $e->reference);
            $entry->lines()->create($this->lineEntry($newUserId, $drId, $amount, null));
            $entry->lines()->create($this->lineEntry($newUserId, $crId, null, $amount));
            $stats['posted']++;

            // Accumulate nominal raw net (Σdebit − Σcredit) per year.
            $year = (int) Carbon::parse($e->entry_date)->year;
            foreach ([[$drId, $amount, '0'], [$crId, '0', $amount]] as [$aid, $d, $c]) {
                if (($this->typeByNewId[$aid] ?? null) instanceof AccountType
                    && in_array($this->typeByNewId[$aid], [AccountType::Income, AccountType::Expense], true)
                ) {
                    $prev = $nominalByYear[$year][$aid] ?? '0';
                    $nominalByYear[$year][$aid] = Money::sub(Money::add($prev, $d), $c);
                }
            }
        }

        return $stats;
    }

    /**
     * Cierre de ejercicio: for every completed year (all but the latest present),
     * post a closing entry dated Dec 31 that zeroes nominal accounts into the
     * result equity account. Returns the number of closing entries posted.
     *
     * @param  array<int,array<int,string>>  $nominalByYear
     */
    private function postAnnualCloses(int $newUserId, array $nominalByYear): int
    {
        if ($nominalByYear === []) {
            return 0;
        }

        $years = array_keys($nominalByYear);
        sort($years);
        $openYear = max($years);                 // latest year stays open

        $resultCode = (string) $this->option('result-code');
        $resultId = ChartOfAccount::where('user_id', $newUserId)
            ->whereIn('code', [$resultCode, '305', '399'])
            ->orderByRaw("FIELD(code, ?, '305', '399')", [$resultCode])
            ->value('id');
        if (! $resultId) {
            $this->warn("No result account ({$resultCode}/305/399) — skipping annual close.");

            return 0;
        }

        $posted = 0;
        foreach ($years as $year) {
            if ($year >= $openYear) {
                continue;
            }

            $legs = [];
            $debitTotal = '0';
            $creditTotal = '0';

            foreach ($nominalByYear[$year] as $aid => $rawNet) {
                if (Money::isZero($rawNet)) {
                    continue;
                }
                // Reverse the account's net to zero it.
                if (Money::isPositive($rawNet)) {            // net debit (expense) → credit it
                    $legs[] = $this->lineEntry($newUserId, $aid, null, $rawNet);
                    $creditTotal = Money::add($creditTotal, $rawNet);
                } else {                                     // net credit (income) → debit it
                    $amt = Money::sub('0', $rawNet);
                    $legs[] = $this->lineEntry($newUserId, $aid, $amt, null);
                    $debitTotal = Money::add($debitTotal, $amt);
                }
            }

            if ($legs === []) {
                continue;
            }

            // Balance to the result account (profit → credit equity; loss → debit).
            $diff = Money::sub($debitTotal, $creditTotal);
            if (Money::isPositive($diff)) {
                $legs[] = $this->lineEntry($newUserId, $resultId, null, $diff);
            } elseif (Money::isNegative($diff)) {
                $legs[] = $this->lineEntry($newUserId, $resultId, Money::sub('0', $diff), null);
            }

            $entry = $this->createEntry($newUserId, "{$year}-12-31", "Cierre de ejercicio {$year}", "cierre:{$year}");
            foreach ($legs as $leg) {
                $entry->lines()->create($leg);
            }
            $posted++;
        }

        return $posted;
    }

    private function createEntry(int $userId, string $date, ?string $description, ?string $reference): JournalEntry
    {
        return JournalEntry::create([
            'user_id' => $userId,
            'entry_date' => $date,
            'reference' => $reference,
            'description' => $description,
            'status' => EntryStatus::Posted,
            'posted_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function lineEntry(int $userId, int $accountId, $debit, $credit): array
    {
        return [
            'user_id' => $userId,
            'account_id' => $accountId,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    /** @return array{debit:float,credit:float,balanced:bool} */
    private function reconcile(int $userId): array
    {
        $r = DB::table('journal_entry_lines')
            ->where('user_id', $userId)
            ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')
            ->first();

        return [
            'debit' => (float) $r->d,
            'credit' => (float) $r->c,
            'balanced' => round((float) $r->d - (float) $r->c, 2) === 0.0,
        ];
    }

    /** @param array<int,int> $userIds */
    private function imbalancedEntries(array $userIds): int
    {
        return DB::table('journal_entry_lines')
            ->whereIn('user_id', $userIds)
            ->groupBy('journal_entry_id')
            ->havingRaw('ROUND(SUM(COALESCE(debit,0)) - SUM(COALESCE(credit,0)), 4) <> 0')
            ->select('journal_entry_id')
            ->get()
            ->count();
    }
}
