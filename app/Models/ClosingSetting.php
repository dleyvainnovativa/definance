<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user cierre settings (one row per user): the equity account that receives
 * each year's result (e.g. 300.2 "Déficit o Remanente de Ejercicios Anteriores").
 */
class ClosingSetting extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'result_account_id',
    ];

    public function resultAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'result_account_id');
    }
}
