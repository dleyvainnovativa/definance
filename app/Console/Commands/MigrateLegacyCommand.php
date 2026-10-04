<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\EntryStatus;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * One-time ETL from the old DeFinance schema into the rebuilt ledger.
 *
 * What it does, per legacy user:
 *   - migrates the chart of accounts (leaf detection → is_postable; Caja/Bancos
 *     leaves → is_cash; normal_balance derived; parents remapped),
 *   - DROPS synthetic "Saldo Inicial" carry-forward entries (the descuadre source),
 *   - migrates real 2-leg transactions as posted entries,
 *   - re-balances genuine single-leg opening balances through an `Apertura`
 *     (Opening Balance Equity) account,
 *   - skips zero-amount noise,
 *   - prints a reconciliation summary (debits = credits per user).
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
        {--force : skip confirmation for --fresh}';

    protected $description = 'Migrate the old DeFinance database into the rebuilt ledger.';

    /** Descriptions written by the old auto carry-forward mechanism. */
    private const AUTO_DESCS = ['Saldo Inicial Auto', 'Saldo Inicial Update', 'Saldo Inicial Nuevo'];

    private ConnectionInterface $legacy;

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
                $stats = $this->migrateEntries((int) $lu->id, $newUser->id, $accMap, $aperturaId);

                $totals = $this->reconcile($newUser->id);
                $summary[] = [
                    $lu->id . ' → ' . $newUser->id,
                    count($accMap),
                    $stats['tx'],
                    $stats['openings'],
                    $stats['dropped'],
                    $stats['skipped'],
                    number_format($totals['debit'], 2),
                    number_format($totals['credit'], 2),
                    $totals['balanced'] ? 'yes' : 'NO',
                ];
            }

            $this->table(
                ['User', 'Accounts', 'Tx', 'Openings', 'Dropped', 'Skipped', 'Σ Debit', 'Σ Credit', 'Balanced'],
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

        // An account is a group (non-postable) when another account is its child.
        $parentIds = $legacyAccounts->pluck('parent_id')->filter()->map(fn($v) => (int) $v)->unique()->flip();

        $map = [];
        foreach ($legacyAccounts as $a) {
            $isLeaf = ! $parentIds->has((int) $a->id);
            $isCash = $isLeaf && (str_starts_with($a->code, '100.1') || str_starts_with($a->code, '100.2'));

            $new = ChartOfAccount::create([
                'user_id' => $newUserId,
                'parent_id' => $a->parent_id ? ($map[(int) $a->parent_id] ?? null) : null,
                'code' => $a->code,
                'name' => $a->name,
                'type' => AccountType::from($a->type),
                'is_postable' => $isLeaf,
                'is_active' => true,
                'is_cash' => $isCash,
                'is_editable' => (bool) $a->is_editable,
                'is_deletable' => (bool) $a->is_deletable,
            ]);
            $map[(int) $a->id] = $new->id;
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

        return [$map, $apertura->id];
    }

    /**
     * @param  array<int,int>  $accMap
     * @return array{tx:int,openings:int,dropped:int,skipped:int}
     */
    private function migrateEntries(int $oldUserId, int $newUserId, array $accMap, int $aperturaId): array
    {
        $stats = ['tx' => 0, 'openings' => 0, 'dropped' => 0, 'skipped' => 0];

        $entries = $this->legacy->table('journal_entries')->where('user_id', $oldUserId)->get();
        $lines = $this->legacy->table('journal_entry_lines')
            ->whereIn('journal_entry_id', $entries->pluck('id'))
            ->get()
            ->groupBy('journal_entry_id');

        foreach ($entries as $e) {
            if ($this->isAuto($e)) {
                $stats['dropped']++;

                continue;
            }

            $entryLines = $lines->get($e->id, collect());

            if ($this->isOpening($e)) {
                $this->migrateOpening($e, $entryLines, $newUserId, $accMap, $aperturaId, $stats);

                continue;
            }

            $this->migrateTransaction($e, $entryLines, $newUserId, $accMap, $stats);
        }

        return $stats;
    }

    private function migrateOpening($e, $entryLines, int $newUserId, array $accMap, int $aperturaId, array &$stats): void
    {
        $ln = $entryLines->first();
        if (! $ln) {
            $stats['skipped']++;

            return;
        }
        $debitSide = $ln->debit !== null && (float) $ln->debit > 0;
        $amount = $debitSide ? $ln->debit : $ln->credit;
        $accNew = $accMap[(int) $ln->chart_of_account_id] ?? null;

        if ($accNew === null || $amount === null || (float) $amount <= 0) {
            $stats['skipped']++;

            return;
        }

        $entry = $this->createEntry($e, $newUserId, 'Saldo inicial');
        // The account's opening side …
        $entry->lines()->create($this->lineAttributes($newUserId, $accNew, $debitSide ? $amount : null, $debitSide ? null : $amount));
        // … balanced against Apertura on the opposite side.
        $entry->lines()->create($this->lineAttributes($newUserId, $aperturaId, $debitSide ? null : $amount, $debitSide ? $amount : null));
        $stats['openings']++;
    }

    private function migrateTransaction($e, $entryLines, int $newUserId, array $accMap, array &$stats): void
    {
        $valid = [];
        $sumDebit = 0.0;
        $sumCredit = 0.0;

        foreach ($entryLines as $ln) {
            $d = $ln->debit !== null ? (float) $ln->debit : 0.0;
            $c = $ln->credit !== null ? (float) $ln->credit : 0.0;
            if (($d > 0) === ($c > 0)) {
                continue; // zero or two-sided noise line
            }
            $accNew = $accMap[(int) $ln->chart_of_account_id] ?? null;
            if ($accNew === null) {
                continue;
            }
            $valid[] = [$accNew, $d > 0 ? $ln->debit : null, $c > 0 ? $ln->credit : null];
            $sumDebit += $d;
            $sumCredit += $c;
        }

        if (count($valid) < 2 || round($sumDebit - $sumCredit, 2) !== 0.0 || $sumDebit <= 0) {
            $stats['skipped']++;

            return;
        }

        $entry = $this->createEntry($e, $newUserId, $e->description ?? null);
        foreach ($valid as [$accNew, $debit, $credit]) {
            $entry->lines()->create($this->lineAttributes($newUserId, $accNew, $debit, $credit));
        }
        $stats['tx']++;
    }

    private function createEntry($e, int $newUserId, ?string $description): JournalEntry
    {
        return JournalEntry::create([
            'user_id' => $newUserId,
            'entry_date' => $e->entry_date,
            'reference' => $e->reference,
            'description' => $description,
            'status' => EntryStatus::Posted,
            'posted_at' => $e->created_at ?? now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function lineAttributes(int $userId, int $accountId, $debit, $credit): array
    {
        return [
            'user_id' => $userId,
            'account_id' => $accountId,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    private function isOpening($e): bool
    {
        return in_array($e->entry_type, ['opening_balance', 'opening_balance_credit'], true);
    }

    private function isAuto($e): bool
    {
        return $this->isOpening($e)
            && ($e->reference === 'automatic' || in_array($e->description, self::AUTO_DESCS, true));
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
