<?php

namespace App\Services\Closing;

use App\Enums\EntryStatus;
use App\Models\ChartOfAccount;
use App\Models\ClosingSetting;
use App\Models\JournalEntry;
use App\Models\PeriodClose;
use App\Services\Ledger\LedgerService;
use App\Services\Ledger\PostingService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cierre de ejercicio — year-end close.
 *
 * Closing a year posts ONE balanced entry dated Dec-31 that zeroes every
 * income/expense account into the result equity account (default 300.2), and
 * records a PeriodClose row that LOCKS the year (PostingService then refuses
 * entries dated on/before that Dec-31). Reopening voids the entry and unlocks.
 *
 * Years must be closed in order and only the latest closed year can be reopened.
 * The current calendar year stays open.
 */
class ClosingService
{
    /** Account codes tried, in order, when no result account is configured. */
    private const DEFAULT_RESULT_CODES = ['300.2', '305', '399'];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PostingService $posting,
    ) {
    }

    /** @return array<string,mixed> */
    public function settings(int $userId): array
    {
        $account = $this->resultAccount($userId);

        return [
            'result_account' => $account
                ? ['id' => $account->id, 'code' => $account->code, 'name' => $account->name]
                : null,
            'configured' => $account !== null,
        ];
    }

    /** @return array<string,mixed> */
    public function saveSettings(int $userId, int $resultAccountId): array
    {
        ClosingSetting::updateOrCreate(
            ['user_id' => $userId],
            ['result_account_id' => $resultAccountId],
        );

        return $this->settings($userId);
    }

    /**
     * Year-by-year close status, oldest first.
     *
     * @return array<string,mixed>
     */
    public function status(int $userId): array
    {
        $range = JournalEntry::query()
            ->where('user_id', $userId)
            ->selectRaw('MIN(entry_date) a, MAX(entry_date) b')
            ->first();

        $rows = [];
        if ($range && $range->a) {
            $currentYear = (int) now()->year;
            $minYear = (int) Carbon::parse($range->a)->year;

            $closed = PeriodClose::query()->where('user_id', $userId)->get()->keyBy('year');
            $maxClosed = $closed->keys()->max();

            for ($y = $minYear; $y <= $currentYear; $y++) {
                $stmt = $this->ledger->incomeStatement($userId, "{$y}-01-01", "{$y}-12-31");
                $isClosed = $closed->has($y);
                $priorDone = $y === $minYear || $closed->has($y - 1) || ($y - 1) < $minYear;

                $rows[] = [
                    'year' => $y,
                    'net_result' => $stmt['totals']['net_income'],
                    'revenue' => $stmt['totals']['revenue'],
                    'expenses' => $stmt['totals']['expenses'],
                    'closed' => $isClosed,
                    'closeable' => ! $isClosed && $y < $currentYear && $priorDone,
                    'reopenable' => $isClosed && $y === $maxClosed,
                    'entry_id' => $isClosed ? $closed[$y]->journal_entry_id : null,
                ];
            }
        }

        return [
            'settings' => $this->settings($userId),
            'current_year' => (int) now()->year,
            'years' => $rows,
        ];
    }

    /**
     * Close a fiscal year. Returns the refreshed status.
     *
     * @return array<string,mixed>
     */
    public function close(int $userId, int $year): array
    {
        $resultAccount = $this->resultAccount($userId);
        if (! $resultAccount) {
            throw new \DomainException('Configura la cuenta de resultado (p. ej. 300.2) antes de cerrar un ejercicio.');
        }
        if ($year >= (int) now()->year) {
            throw new \DomainException('No puedes cerrar el ejercicio en curso.');
        }
        if (PeriodClose::query()->where('user_id', $userId)->where('year', $year)->exists()) {
            throw new \DomainException("El ejercicio {$year} ya está cerrado.");
        }

        // Sequential: the immediately preceding year (if it has data) must be closed.
        $minYear = (int) Carbon::parse(
            JournalEntry::query()->where('user_id', $userId)->min('entry_date') ?? "{$year}-01-01"
        )->year;
        $priorClosed = ($year - 1) < $minYear
            || PeriodClose::query()->where('user_id', $userId)->where('year', $year - 1)->exists();
        if (! $priorClosed) {
            throw new \DomainException('Cierra primero los ejercicios anteriores.');
        }

        $stmt = $this->ledger->incomeStatement($userId, "{$year}-01-01", "{$year}-12-31");

        // Zero each nominal account; balance the difference into the result account.
        $legs = [];
        $debitTotal = '0';
        $creditTotal = '0';

        foreach (array_merge($stmt['revenue'], $stmt['expenses']) as $line) {
            $amount = Money::of($line['amount']); // signed, positive on the account's normal side
            if (Money::isZero($amount)) {
                continue;
            }
            // Post the OPPOSITE of the account's normal-side balance to zero it.
            // Revenue (credit-normal, amount>0) → debit it; Expense (debit-normal, amount>0) → credit it.
            $isCredit = $this->isCreditNormal($userId, (int) $line['account_id']);
            $zeroOnDebit = $isCredit ? Money::isPositive($amount) : Money::isNegative($amount);
            $abs = Money::isNegative($amount) ? Money::sub('0', $amount) : $amount;

            if ($zeroOnDebit) {
                $legs[] = ['account_id' => $line['account_id'], 'debit' => $abs];
                $debitTotal = Money::add($debitTotal, $abs);
            } else {
                $legs[] = ['account_id' => $line['account_id'], 'credit' => $abs];
                $creditTotal = Money::add($creditTotal, $abs);
            }
        }

        if ($legs === []) {
            throw new \DomainException("No hay movimientos de ingresos o gastos que cerrar en {$year}.");
        }

        $diff = Money::sub($debitTotal, $creditTotal);
        if (Money::isPositive($diff)) {
            $legs[] = ['account_id' => $resultAccount->id, 'credit' => $diff];
        } elseif (Money::isNegative($diff)) {
            $legs[] = ['account_id' => $resultAccount->id, 'debit' => Money::sub('0', $diff)];
        }

        return DB::transaction(function () use ($userId, $year, $legs, $resultAccount, $stmt) {
            $entry = $this->posting->post(
                userId: $userId,
                entryDate: "{$year}-12-31",
                legs: $legs,
                description: "Cierre de ejercicio {$year}",
                reference: "cierre:{$year}",
            );

            PeriodClose::create([
                'user_id' => $userId,
                'year' => $year,
                'result_account_id' => $resultAccount->id,
                'journal_entry_id' => $entry->id,
                'net_result' => $stmt['totals']['net_income'],
                'closed_at' => now(),
            ]);

            return $this->status($userId);
        });
    }

    /**
     * Reopen a closed year (latest first): remove the lock, then void the entry.
     *
     * @return array<string,mixed>
     */
    public function reopen(int $userId, int $year): array
    {
        $close = PeriodClose::query()->where('user_id', $userId)->where('year', $year)->first();
        if (! $close) {
            throw new \DomainException("El ejercicio {$year} no está cerrado.");
        }

        $maxClosed = (int) PeriodClose::query()->where('user_id', $userId)->max('year');
        if ($year !== $maxClosed) {
            throw new \DomainException('Reabre primero los ejercicios posteriores (del más reciente al más antiguo).');
        }

        return DB::transaction(function () use ($userId, $close) {
            $entry = $close->journal_entry_id ? JournalEntry::find($close->journal_entry_id) : null;

            // Delete the lock first so the reversing entry (dated Dec-31) is allowed.
            $close->delete();

            if ($entry && $entry->status === EntryStatus::Posted) {
                $this->posting->void($entry, reason: "Reapertura de ejercicio {$entry->entry_date?->format('Y')}");
            }

            return $this->status($userId);
        });
    }

    // ----------------------------------------------------------------- helpers

    private function resultAccount(int $userId): ?ChartOfAccount
    {
        $setting = ClosingSetting::query()->where('user_id', $userId)->first();
        if ($setting?->result_account_id) {
            $acc = ChartOfAccount::query()->where('user_id', $userId)->find($setting->result_account_id);
            if ($acc) {
                return $acc;
            }
        }

        foreach (self::DEFAULT_RESULT_CODES as $code) {
            $acc = ChartOfAccount::query()->where('user_id', $userId)->where('code', $code)->first();
            if ($acc) {
                return $acc;
            }
        }

        return null;
    }

    private function isCreditNormal(int $userId, int $accountId): bool
    {
        $type = ChartOfAccount::query()->where('user_id', $userId)->where('id', $accountId)->value('type');

        return in_array($type?->value, ['liability', 'equity', 'income'], true);
    }
}
