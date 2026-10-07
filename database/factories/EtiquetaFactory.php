<?php

namespace Database\Factories;

use App\Models\Etiqueta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Etiqueta> */
class EtiquetaFactory extends Factory
{
    protected $model = Etiqueta::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst($this->faker->unique()->word()),
            'color' => $this->faker->randomElement([null, '#406dab', '#a84882', '#2f8f6b']),
        ];
    }
}
