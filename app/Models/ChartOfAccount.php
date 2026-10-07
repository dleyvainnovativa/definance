<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    /** @use HasFactory<\Database\Factories\ChartOfAccountFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'parent_id',
        'code',
        'name',
        'type',
        'normal_balance',
        'is_postable',
        'is_active',
        'is_cash',
        'is_editable',
        'is_deletable',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'normal_balance' => NormalBalance::class,
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
            'is_cash' => 'boolean',
            'is_editable' => 'boolean',
            'is_deletable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keep normal_balance consistent with type automatically.
        static::saving(function (ChartOfAccount $account) {
            if ($account->type instanceof AccountType) {
                $account->normal_balance = $account->type->normalBalance();
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'account_etiqueta', 'account_id', 'etiqueta_id');
    }
}
