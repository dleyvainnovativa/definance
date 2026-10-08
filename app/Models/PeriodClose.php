<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A closed fiscal year: the posted "Cierre de ejercicio" entry and the result
 * folded into equity. A row here LOCKS its year — PostingService refuses any
 * entry dated on or before {year}-12-31.
 */
class PeriodClose extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'year',
        'result_account_id',
        'journal_entry_id',
        'net_result',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'net_result' => 'decimal:4',
            'closed_at' => 'datetime',
        ];
    }

    public function resultAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'result_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
