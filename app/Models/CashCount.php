<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An audit record of one cash count: the per-account breakdown, the net total
 * difference, and the adjusting journal entry it posted (null when everything
 * matched). Per-user isolation via BelongsToUser.
 */
class CashCount extends Model
{
    /** @use HasFactory<\Database\Factories\CashCountFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'count_date',
        'journal_entry_id',
        'total_difference',
        'breakdown',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'count_date' => 'date',
            'breakdown' => 'array',
            'total_difference' => 'decimal:4',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
