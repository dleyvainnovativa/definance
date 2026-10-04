<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    /** @use HasFactory<\Database\Factories\JournalEntryLineFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'line_description',
        'tax_code',
        'tax_rate',
        'tax_base',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_base' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        // Application-level mirror of the DB CHECK, so the invariant also holds
        // on SQLite (CI/tests) where the ALTER ... CHECK isn't applied.
        static::saving(function (JournalEntryLine $line) {
            $debit = $line->debit !== null ? (float) $line->debit : null;
            $credit = $line->credit !== null ? (float) $line->credit : null;

            $hasDebit = $debit !== null && $debit > 0;
            $hasCredit = $credit !== null && $credit > 0;

            if ($hasDebit === $hasCredit) {
                throw new \DomainException(
                    'A journal line must have exactly one positive side: debit XOR credit.'
                );
            }
            if (($debit ?? 0) < 0 || ($credit ?? 0) < 0) {
                throw new \DomainException('Journal line amounts cannot be negative.');
            }
        });
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
