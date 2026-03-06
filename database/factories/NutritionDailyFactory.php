<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NutritionDailyFactory extends Factory
{
    public function definition(): array
    {
        $caloriesGoal = fake()->randomElement([1800, 2000, 2200, 2500]);

        return [
            'user_uuid'         => User::factory(),
            'day'               => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'calories_goal'     => $caloriesGoal,
            'calories_total'    => fake()->numberBetween(0, $caloriesGoal),
            'water_goal'        => fake()->randomElement([2000, 2500, 3000, 3500]),
            'water_current'     => fake()->numberBetween(0, 3500),
            'sleep_goal_min'    => 480,
            'sleep_current_min' => fake()->numberBetween(300, 600),
        ];
    }
}
