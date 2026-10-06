<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved planned (adjusted) net cash movement for one account in one month.
 * Per-user isolation via BelongsToUser. Actuals are never stored here.
 */
class CashFlowAdjustment extends Model
{
    /** @use HasFactory<\Database\Factories\CashFlowAdjustmentFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'period',
        'planned_amount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'decimal:4',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
