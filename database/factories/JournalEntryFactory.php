<?php

namespace Database\Factories;

use App\Enums\EntryStatus;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entry_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'reference' => fake()->optional()->bothify('REF-####'),
            'description' => fake()->sentence(),
            'status' => EntryStatus::Posted,
            'posted_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EntryStatus::Draft, 'posted_at' => null]);
    }
}
