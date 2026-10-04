<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pre-flight check for a production deploy. Run after setting .env and caching
 * config. Reports anything that would be unsafe or broken in production.
 *
 *   php artisan definance:check-production
 */
class CheckProductionCommand extends Command
{
    protected $signature = 'definance:check-production';

    protected $description = 'Verify this environment is ready for production.';

    public function handle(): int
    {
        $problems = 0;
        $ok = fn(string $m) => $this->line('  <info>✓</info> ' . $m);
        $warn = fn(string $m) => $this->line('  <comment>!</comment> ' . $m);
        $fail = function (string $m) use (&$problems) {
            $this->line('  <error>✗</error> ' . $m);
            $problems++;
        };

        $this->info('Environment');
        config('app.debug') ? $fail('APP_DEBUG is true — set it to false in production.') : $ok('APP_DEBUG is off.');
        app()->environment('production') ? $ok('APP_ENV is production.') : $warn('APP_ENV is ' . app()->environment() . '.');
        str_starts_with((string) config('app.url'), 'https://') ? $ok('APP_URL uses https.') : $warn('APP_URL is not https.');

        $this->info('Caching');
        app()->configurationIsCached() ? $ok('Config is cached.') : $warn('Run php artisan config:cache.');
        app()->routesAreCached() ? $ok('Routes are cached.') : $warn('Run php artisan route:cache.');

        $this->info('Drivers (Hostinger: database-backed)');
        foreach (['session' => 'session.driver', 'cache' => 'cache.default', 'queue' => 'queue.default'] as $label => $key) {
            config($key) === 'database' ? $ok("$label driver is database.") : $warn("$label driver is " . config($key) . '.');
        }

        $this->info('Firebase');
        $cred = config('firebase.projects.app.credentials');
        $cred && is_string($cred) && file_exists(base_path($cred))
            ? $ok('Service account file found.')
            : $fail('Firebase service account not found at FIREBASE_CREDENTIALS.');

        $this->info('Database & ledger');
        try {
            DB::connection()->getPdo();
            $ok('Database connection works.');
            $bad = DB::table('journal_entry_lines')
                ->whereIn('journal_entry_id', function ($q) {
                    $q->select('id')->from('journal_entries')->whereIn('status', ['posted', 'void']);
                })
                ->groupBy('journal_entry_id')
                ->havingRaw('ROUND(SUM(COALESCE(debit,0)) - SUM(COALESCE(credit,0)), 4) <> 0')
                ->select('journal_entry_id')
                ->get()->count();
            $bad === 0 ? $ok('Every posted entry balances.') : $fail("{$bad} unbalanced entrie(s) — run definance:ledger-check.");
        } catch (\Throwable $e) {
            $fail('Database check failed: ' . $e->getMessage());
        }

        $this->newLine();
        if ($problems === 0) {
            $this->info('Ready for production.');

            return self::SUCCESS;
        }
        $this->error("{$problems} blocking issue(s) found.");

        return self::FAILURE;
    }
}
