<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'firebase_uid',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Identity is Firebase — there is no local `password` column. The session
     * guard reads this when building the "remember me" recaller; returning an
     * empty string lets Auth::login(remember: true) work without tripping
     * strict-mode's MissingAttributeException on a column that doesn't exist.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    // Domain relations (chart of accounts, journal entries, …) are added in
    // Phase 1 when those models exist.
}
