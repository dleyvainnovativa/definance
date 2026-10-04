<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntryLine>
 *
 * Defaults to a debit line. Use ->debit($amount) / ->credit($amount) to set
 * the side explicitly when building balanced entries.
 */
class JournalEntryLineFactory extends Factory
{
    protected $model = JournalEntryLine::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => ChartOfAccount::factory(),
            'debit' => fake()->randomFloat(2, 1, 1000),
            'credit' => null,
            'line_description' => fake()->optional()->words(3, true),
        ];
    }

    public function debit(float $amount): static
    {
        return $this->state(fn () => ['debit' => $amount, 'credit' => null]);
    }

    public function credit(float $amount): static
    {
        return $this->state(fn () => ['credit' => $amount, 'debit' => null]);
    }
}
