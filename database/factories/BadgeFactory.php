<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BadgeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'        => fake()->unique()->slug(2),
            'name'        => fake()->words(2, true),
            'description' => fake()->sentence(),
            'category'    => fake()->randomElement(['consistency', 'training', 'water', 'hardcore', 'social']),
            'tier'        => fake()->randomElement(['bronze', 'silver', 'gold', 'legend']),
            'xp_reward'   => fake()->randomElement([100, 200, 300, 500, 800, 1000]),
            'is_active'   => true,
        ];
    }
}
