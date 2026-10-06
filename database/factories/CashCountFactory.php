<?php

namespace Database\Factories;

use App\Models\CashCount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashCount>
 */
class CashCountFactory extends Factory
{
    protected $model = CashCount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'count_date' => now()->toDateString(),
            'journal_entry_id' => null,
            'total_difference' => 0,
            'breakdown' => [],
            'note' => null,
        ];
    }
}
