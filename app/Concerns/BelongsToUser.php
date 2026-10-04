<?php

namespace App\Concerns;

use App\Models\Scopes\UserScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Ownership behaviour for per-user models.
 *
 * - Applies the global UserScope so queries are automatically filtered to the
 *   authenticated user (tenant isolation).
 * - Stamps user_id from the authenticated user on create when not set.
 * - Provides the `user` relation and a `forUser()` scope (bypasses the global
 *   scope) for console / cross-user contexts.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new UserScope);

        static::creating(function ($model) {
            if (empty($model->user_id) && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Explicit ownership filter without the global scope (console/admin). */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->withoutGlobalScope(UserScope::class)
            ->where($this->getTable().'.user_id', $userId);
    }
}
