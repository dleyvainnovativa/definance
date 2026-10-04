<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Integrity backstop: finds posted entries whose debits ≠ credits.
 *
 * PostingService prevents these from ever being created, so a non-empty result
 * means data arrived another way — a raw DB edit or a migration defect. Run on
 * a schedule and after the Phase 9 data migration.
 *
 *   php artisan definance:ledger-check
 *   php artisan definance:ledger-check --user=5
 */
class LedgerCheckCommand extends Command
{
    protected $signature = 'definance:ledger-check {--user= : Limit to one user id}';

    protected $description = 'Report posted journal entries that do not balance (debits ≠ credits).';

    public function handle(): int
    {
        $query = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->whereIn('e.status', ['posted', 'void'])
            ->when($this->option('user'), fn ($q) => $q->where('e.user_id', (int) $this->option('user')))
            ->groupBy('l.journal_entry_id')
            ->havingRaw('ROUND(SUM(COALESCE(l.debit,0)) - SUM(COALESCE(l.credit,0)), 4) <> 0')
            ->selectRaw('l.journal_entry_id, ROUND(SUM(COALESCE(l.debit,0)),4) as d, ROUND(SUM(COALESCE(l.credit,0)),4) as c');

        $offenders = $query->get();

        if ($offenders->isEmpty()) {
            $this->info('Ledger OK — every posted entry balances.');

            return self::SUCCESS;
        }

        $this->error("Found {$offenders->count()} unbalanced posted entrie(s):");
        $this->table(
            ['Entry ID', 'Debits', 'Credits', 'Difference'],
            $offenders->map(fn ($r) => [
                $r->journal_entry_id,
                number_format((float) $r->d, 4),
                number_format((float) $r->c, 4),
                number_format((float) $r->d - (float) $r->c, 4),
            ])->all()
        );

        return self::FAILURE;
    }
}
