<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class LoginDevice extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }
}
