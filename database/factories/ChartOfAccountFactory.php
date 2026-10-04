<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartOfAccount>
 */
class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    public function definition(): array
    {
        $type = fake()->randomElement(AccountType::cases());

        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'code' => (string) fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->words(2, true),
            'type' => $type,
            'normal_balance' => $type->normalBalance(),
            'is_postable' => true,
            'is_active' => true,
            'is_editable' => true,
            'is_deletable' => true,
        ];
    }

    public function ofType(AccountType $type): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'normal_balance' => $type->normalBalance(),
        ]);
    }

    public function group(): static
    {
        return $this->state(fn () => ['is_postable' => false]);
    }
}
