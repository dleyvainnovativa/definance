<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => ChartOfAccount::factory(),
            'year' => (int) now()->format('Y'),
            'month' => 0,
            'amount' => fake()->randomFloat(2, 0, 100000),
        ];
    }
}
