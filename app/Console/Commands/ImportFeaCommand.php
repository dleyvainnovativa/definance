<?php

namespace App\Console\Commands;

use App\Models\CashFlowAdjustment;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time import of the legacy FEA (Flujo de Efectivo Ajustado) JSON projections
 * into the rebuilt `cash_flow_adjustments` table.
 *
 * Legacy stored, per user per month, a file storage/app/private/fea/{uid}/fea_{M}_{Y}.json:
 * a list of per-account projections, each routed to a cash account. The rebuild's
 * FEA stores one PLANNED NET movement per cash account per month, so we aggregate:
 *
 *   planned_net(cash_account, month) = Σ inflow projections routed to it
 *                                      − Σ outflow projections routed to it
 *
 * Inflow/outflow follows the legacy "Saldo Final" definition (verified against the
 * old ManagedCashFlowController): incomes / cash_available /
 * miscellaneous_debtors_income / other_incomes are +; every *_expenses and
 * payments_* section is −. Rows with no cash_account or zero projection are skipped.
 *
 * Account ids are mapped legacy → code (via the legacy chart) → new account id
 * (same code, preserved by the migration). Users map by firebase_uid, as in the
 * migration. Run with --dry-run first.
 *
 *   php artisan definance:import-fea --dry-run
 *   php artisan definance:import-fea --user=1
 */
class ImportFeaCommand extends Command
{
    protected $signature = 'definance:import-fea
        {--connection=legacy : source DB connection for the account and user maps}
        {--path= : folder holding the per-user fea subfolders; default storage/app/private/fea}
        {--user= : limit to one legacy user id}
        {--dry-run : process then roll back, reporting only}';

    protected $description = 'Import the legacy FEA JSON projections into cash_flow_adjustments.';

    /** Sections that increase a cash account (Saldo Final "plus"). */
    private const PLUS = ['incomes', 'cash_available', 'miscellaneous_debtors_income', 'other_incomes'];

    /** Sections that decrease a cash account (Saldo Final "minus"). */
    private const MINUS = [
        'monthly_expenses', 'credit_expenses', 'bank_expenses', 'car_expenses',
        'house_expenses', 'payments_miscellaneous_creditors', 'investment_expenses', 'other_expenses',
    ];

    public function handle(): int
    {
        $source = (string) $this->option('connection');
        if ($source === 'legacy' && ! config('database.connections.legacy')) {
            config(['database.connections.legacy' => config('legacy')]);
        }
        $legacy = DB::connection($source);

        $base = $this->option('path') ?: storage_path('app/private/fea');
        $files = glob(rtrim($base, '/').'/*/fea_*.json') ?: [];
        if ($files === []) {
            $this->error("No FEA files found under {$base}");

            return self::FAILURE;
        }

        // legacy account id => code  (code is preserved into the new chart)
        $idToCode = $legacy->table('chart_of_accounts')->pluck('code', 'id')->all();

        $summary = [];
        $unmapped = [];
        $codeMapCache = [];   // newUserId => [code => newAccountId]
        $userCache = [];      // legacyUserId => newUserId|null

        DB::beginTransaction();
        try {
            foreach ($files as $file) {
                if (! preg_match('#/(\d+)/fea_(\d+)_(\d+)\.json$#', $file, $m)) {
                    continue;
                }
                [$legacyUserId, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];

                if ($this->option('user') && $legacyUserId !== (int) $this->option('user')) {
                    continue;
                }

                // legacy user -> new user (by firebase_uid)
                if (! array_key_exists($legacyUserId, $userCache)) {
                    $uid = $legacy->table('users')->where('id', $legacyUserId)->value('firebase_uid');
                    $userCache[$legacyUserId] = $uid ? (User::where('firebase_uid', $uid)->value('id')) : null;
                }
                $newUserId = $userCache[$legacyUserId];
                if (! $newUserId) {
                    $summary[] = ["{$legacyUserId} → ?", "{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT), '—', 'user not migrated'];

                    continue;
                }

                $codeToNewId = $codeMapCache[$newUserId]
                    ??= ChartOfAccount::query()->where('user_id', $newUserId)->pluck('id', 'code')->all();

                $rows = json_decode((string) file_get_contents($file), true) ?: [];
                $net = []; // newAccountId => planned net (string)

                foreach ($rows as $r) {
                    $proj = $r['projection'] ?? 0;
                    $cashLegacyId = $r['cash_account'] ?? null;
                    $key = $r['key'] ?? '';
                    if (! $proj || $cashLegacyId === null) {
                        continue;
                    }
                    $sign = in_array($key, self::PLUS, true) ? 1 : (in_array($key, self::MINUS, true) ? -1 : 0);
                    if ($sign === 0) {
                        continue;
                    }

                    $code = $idToCode[$cashLegacyId] ?? null;
                    $newId = $code !== null ? ($codeToNewId[$code] ?? null) : null;
                    if ($newId === null) {
                        $unmapped[$cashLegacyId] = true;

                        continue;
                    }

                    $amt = Money::of($proj);
                    $net[$newId] = Money::add($net[$newId] ?? '0', $sign > 0 ? $amt : Money::sub('0', $amt));
                }

                $period = sprintf('%04d-%02d', $year, $month);
                $posted = 0;
                foreach ($net as $newId => $amount) {
                    if (Money::isZero($amount)) {
                        continue;
                    }
                    CashFlowAdjustment::updateOrCreate(
                        ['user_id' => $newUserId, 'period' => $period, 'account_id' => $newId],
                        ['planned_amount' => $amount, 'note' => 'Importado de FEA (legacy)'],
                    );
                    $posted++;
                }

                if ($posted > 0) {
                    $detail = collect($net)
                        ->reject(fn ($a) => Money::isZero($a))
                        ->map(fn ($a, $id) => ($codeToNewId ? array_search($id, $codeToNewId, true) : $id).'='.number_format((float) $a, 2))
                        ->implode(' | ');
                    $summary[] = ["{$legacyUserId} → {$newUserId}", $period, $posted, $detail];
                }
            }

            $this->table(['User', 'Period', 'Cash accts', 'Planned net per account'], $summary);
            if ($unmapped !== []) {
                $this->warn('Unmapped legacy cash account ids (skipped): '.implode(', ', array_keys($unmapped)));
            }

            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->warn('Dry run: all changes rolled back. Re-run without --dry-run to apply.');
            } else {
                DB::commit();
                $this->info('FEA import committed.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed and was rolled back: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
