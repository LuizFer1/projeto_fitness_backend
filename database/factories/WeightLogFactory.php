<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WeightLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'day'       => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'weight_kg' => fake()->randomFloat(2, 50, 130),
        ];
    }
}
