<?php

namespace App\Policies;

use App\Models\ChartOfAccount;
use App\Models\User;

class ChartOfAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ChartOfAccount $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ChartOfAccount $account): bool
    {
        return $account->user_id === $user->id && $account->is_editable;
    }

    /**
     * Labels (etiquetas) are additive metadata, not structural edits, so they
     * can be managed on ANY owned account — including standard accounts locked
     * for structural editing (is_editable = false).
     */
    public function manageLabels(User $user, ChartOfAccount $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function delete(User $user, ChartOfAccount $account): bool
    {
        return $account->user_id === $user->id && $account->is_deletable;
    }
}
