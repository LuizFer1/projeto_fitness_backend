<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'           => fake()->unique()->slug(3),
            'title'          => fake()->sentence(4),
            'description'    => fake()->paragraph(1),
            'condition_type' => fake()->randomElement(['streak_workout_days', 'streak_water_days', 'workout_count_period']),
            'target_value'   => fake()->randomElement([3, 5, 7, 10]),
            'period_type'    => fake()->randomElement(['daily', 'weekly', 'monthly', 'lifetime']),
            'xp_reward'      => fake()->randomElement([50, 80, 100, 150]),
            'is_active'      => true,
        ];
    }
}
