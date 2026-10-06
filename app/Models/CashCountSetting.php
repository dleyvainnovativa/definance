<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user arqueo settings (one row per user): the combined "Diferencia en
 * Arqueo" account and, optionally, the subset of accounts to count.
 */
class CashCountSetting extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'difference_account_id',
        'counted_account_ids',
    ];

    protected function casts(): array
    {
        return [
            'counted_account_ids' => 'array',
        ];
    }

    public function differenceAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'difference_account_id');
    }
}
