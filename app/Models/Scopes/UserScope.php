<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Transparent tenant isolation: every query on an owned model is automatically
 * constrained to the authenticated user. Nothing relies on a developer
 * remembering to add `where('user_id', ...)`.
 *
 * When there is no authenticated user (console: seeders, migrations, the
 * ledger-check command, tests that don't log in) the scope is a no-op, so
 * those contexts set user_id explicitly and are unaffected.
 */
class UserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check()) {
            $builder->where($model->getTable().'.user_id', Auth::id());
        }
    }
}
