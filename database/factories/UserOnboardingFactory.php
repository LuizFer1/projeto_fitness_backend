<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserOnboardingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_uuid'         => User::factory(),
            'completed'         => fake()->boolean(70),
            'height_cm'         => fake()->numberBetween(150, 200),
            'weight_kg'         => fake()->randomFloat(2, 50, 130),
            'body_fat_percent'  => fake()->optional()->randomFloat(2, 8, 40),
            'workouts_per_week' => fake()->numberBetween(0, 7),
            'work_style'        => fake()->randomElement(['sedentary', 'moderate', 'active']),
        ];
    }

    public function incomplete(): static
    {
        return $this->state(fn () => ['completed' => false]);
    }
}
