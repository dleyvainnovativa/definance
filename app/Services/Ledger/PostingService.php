<?php

namespace App\Services\Ledger;

use App\Enums\EntryStatus;
use App\Exceptions\PostingException;
use App\Exceptions\UnbalancedEntryException;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single, authoritative way to write to the ledger.
 *
 * Guarantees, atomically:
 *   - at least two legs,
 *   - every leg posts to exactly one side (debit XOR credit, > 0),
 *   - every account is owned by the user, postable and active,
 *   - Σ debits == Σ credits (exact, 4 dp).
 *
 * Corrections never mutate history: void() posts a reversing entry and marks
 * the original void. This is what makes the old "descuadre on edit" class of
 * bug impossible — there is no forward-recalculation and no synthetic rows.
 */
class PostingService
{
    /**
     * Post a balanced journal entry.
     *
     * @param  array<int,Leg|array<string,mixed>>  $legs
     */
    public function post(
        int $userId,
        string $entryDate,
        array $legs,
        ?string $description = null,
        ?string $reference = null,
        EntryStatus $status = EntryStatus::Posted,
    ): JournalEntry {
        $legs = $this->normalizeLegs($legs);
        $this->assertBalanced($legs);
        $this->assertAccounts($userId, $legs);

        return DB::transaction(function () use ($userId, $entryDate, $legs, $description, $reference, $status) {
            $entry = JournalEntry::create([
                'user_id' => $userId,
                'entry_date' => $entryDate,
                'description' => $description,
                'reference' => $reference,
                'status' => $status,
                'posted_at' => $status === EntryStatus::Posted ? now() : null,
            ]);

            foreach ($legs as $leg) {
                $entry->lines()->create($leg->toLineAttributes($userId));
            }

            return $entry->load('lines');
        });
    }

    /**
     * Void a posted entry by posting its mirror image and linking the two.
     * Returns the reversing entry.
     */
    public function void(JournalEntry $entry, ?string $date = null, ?string $reason = null): JournalEntry
    {
        if ($entry->status !== EntryStatus::Posted) {
            throw PostingException::notPosted();
        }

        return DB::transaction(function () use ($entry, $date, $reason) {
            $reversingLegs = $entry->lines->map(fn ($line) => new Leg(
                accountId: $line->account_id,
                debit: Money::isPositive(Money::of($line->credit)) ? $line->credit : null,
                credit: Money::isPositive(Money::of($line->debit)) ? $line->debit : null,
                description: $line->line_description,
            ))->all();

            $reversing = $this->post(
                userId: $entry->user_id,
                entryDate: $date ?? Carbon::parse($entry->entry_date)->toDateString(),
                legs: $reversingLegs,
                description: $reason ?? "Reversal of entry #{$entry->id}",
                reference: "reversal:{$entry->id}",
            );

            $entry->update([
                'status' => EntryStatus::Void,
                'reversed_entry_id' => $reversing->id,
            ]);

            return $reversing;
        });
    }

    /**
     * Replace a DRAFT entry's legs and header in place. Only drafts can be
     * edited — they are excluded from the ledger, so there is no history to
     * rewrite. Posted entries are never edited (correct by void + re-post).
     *
     * @param  array<int,Leg|array<string,mixed>>  $legs
     */
    public function updateDraft(
        JournalEntry $entry,
        string $entryDate,
        array $legs,
        ?string $description = null,
        ?string $reference = null,
    ): JournalEntry {
        if ($entry->status !== EntryStatus::Draft) {
            throw PostingException::notDraft();
        }

        $legs = $this->normalizeLegs($legs);
        $this->assertBalanced($legs);
        $this->assertAccounts($entry->user_id, $legs);

        return DB::transaction(function () use ($entry, $entryDate, $legs, $description, $reference) {
            $entry->lines()->delete();
            $entry->update([
                'entry_date' => $entryDate,
                'description' => $description,
                'reference' => $reference,
            ]);

            foreach ($legs as $leg) {
                $entry->lines()->create($leg->toLineAttributes($entry->user_id));
            }

            return $entry->load('lines');
        });
    }

    /**
     * Edit a POSTED entry's header metadata only — date, description, reference.
     * Legs and amounts are never touched: the ledger stays append-only, so money
     * is still corrected by void + re-post. Because balances are computed on
     * read, moving the date is safe and causes no descuadre.
     *
     * (Hook: when período-cierre lands, reject moving entry_date across a closed
     * period boundary here. No-op today — no closed periods exist yet.)
     */
    public function updatePostedMeta(
        JournalEntry $entry,
        string $entryDate,
        ?string $description = null,
        ?string $reference = null,
    ): JournalEntry {
        if ($entry->status !== EntryStatus::Posted) {
            throw PostingException::notPostedForMeta();
        }

        $entry->update([
            'entry_date' => $entryDate,
            'description' => $description,
            'reference' => $reference,
        ]);

        return $entry->fresh()->load('lines');
    }

    /**
     * Post a DRAFT entry: re-check that its stored lines balance, then flip it
     * to posted. The entry keeps its id and lines.
     */
    public function postDraft(JournalEntry $entry): JournalEntry
    {
        if ($entry->status !== EntryStatus::Draft) {
            throw PostingException::notDraft();
        }

        $debit = Money::sum($entry->lines->pluck('debit'));
        $credit = Money::sum($entry->lines->pluck('credit'));
        if (Money::cmp($debit, $credit) !== 0) {
            throw UnbalancedEntryException::make($debit, $credit);
        }

        $entry->update(['status' => EntryStatus::Posted, 'posted_at' => now()]);

        return $entry;
    }

    /**
     * @param  array<int,Leg|array<string,mixed>>  $legs
     * @return array<int,Leg>
     */
    private function normalizeLegs(array $legs): array
    {
        if (count($legs) < 2) {
            throw PostingException::tooFewLegs();
        }

        return array_map(
            fn ($leg) => $leg instanceof Leg ? $leg : Leg::fromArray($leg),
            array_values($legs),
        );
    }

    /** @param array<int,Leg> $legs */
    private function assertBalanced(array $legs): void
    {
        $debit = Money::sum(array_map(fn (Leg $l) => $l->debit, $legs));
        $credit = Money::sum(array_map(fn (Leg $l) => $l->credit, $legs));

        if (Money::cmp($debit, $credit) !== 0) {
            throw UnbalancedEntryException::make($debit, $credit);
        }
    }

    /** @param array<int,Leg> $legs */
    private function assertAccounts(int $userId, array $legs): void
    {
        $ids = array_values(array_unique(array_map(fn (Leg $l) => $l->accountId, $legs)));

        $accounts = ChartOfAccount::query()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $account = $accounts->get($id);
            if (! $account) {
                throw PostingException::accountNotOwned($id);
            }
            if (! $account->is_postable || ! $account->is_active) {
                throw PostingException::accountNotPostable($account->code);
            }
        }
    }
}
