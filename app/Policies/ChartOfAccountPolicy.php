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

    public function delete(User $user, ChartOfAccount $account): bool
    {
        return $account->user_id === $user->id && $account->is_deletable;
    }
}
