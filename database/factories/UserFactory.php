<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'firebase_uid' => 'fb_'.Str::random(28),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
