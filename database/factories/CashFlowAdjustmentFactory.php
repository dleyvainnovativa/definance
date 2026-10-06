<?php

namespace Database\Factories;

use App\Models\CashFlowAdjustment;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashFlowAdjustment>
 */
class CashFlowAdjustmentFactory extends Factory
{
    protected $model = CashFlowAdjustment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => ChartOfAccount::factory(),
            'period' => now()->format('Y-m'),
            'planned_amount' => fake()->randomFloat(2, -10000, 10000),
            'note' => null,
        ];
    }
}
