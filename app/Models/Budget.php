<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A budgeted amount for one account in a year (month = 0) or a month (1–12).
 * Per-user isolation via BelongsToUser. Actuals are never stored here.
 */
class Budget extends Model
{
    /** @use HasFactory<\Database\Factories\BudgetFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'year',
        'month',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'amount' => 'decimal:4',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
