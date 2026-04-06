<?php

namespace Database\Factories;

use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(8),
            'icon' => '🏆',
            'category' => fake()->randomElement(['goals', 'milestone', 'streak', 'social', 'leaderboard']),
            'xp_reward' => fake()->randomElement([50, 100, 150, 200]),
        ];
    }
}
