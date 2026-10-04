<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Enums\EntryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    /** @use HasFactory<\Database\Factories\JournalEntryFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'entry_date',
        'reference',
        'description',
        'status',
        'posted_at',
        'reversed_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => EntryStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /** Sum of all debit legs (posted or not — caller decides scope). */
    public function totalDebit(): float
    {
        return (float) $this->lines->sum(fn ($l) => (float) $l->debit);
    }

    public function totalCredit(): float
    {
        return (float) $this->lines->sum(fn ($l) => (float) $l->credit);
    }

    /**
     * True when debits and credits match (to 4 dp).
     * Real posting uses exact string math in PostingService (Phase 2); this
     * helper is a convenience check for display and tests.
     */
    public function isBalanced(): bool
    {
        return round($this->totalDebit() - $this->totalCredit(), 4) === 0.0;
    }
}
