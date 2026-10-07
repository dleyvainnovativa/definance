<?php

namespace App\Policies;

use App\Enums\EntryStatus;
use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $entry->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** The ledger is append-only: a posted entry is corrected by voiding. */
    public function void(User $user, JournalEntry $entry): bool
    {
        return $entry->user_id === $user->id && $entry->status === EntryStatus::Posted;
    }

    /** Only drafts may be edited/deleted in place. */
    public function update(User $user, JournalEntry $entry): bool
    {
        return $entry->user_id === $user->id && $entry->status === EntryStatus::Draft;
    }

    /**
     * A posted entry's header metadata (date, description, reference) may be
     * corrected in place. Amounts/legs stay immutable — those go through void.
     */
    public function updateMeta(User $user, JournalEntry $entry): bool
    {
        return $entry->user_id === $user->id && $entry->status === EntryStatus::Posted;
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        return $entry->user_id === $user->id && $entry->status === EntryStatus::Draft;
    }
}
