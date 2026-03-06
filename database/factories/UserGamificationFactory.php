<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserGamificationFactory extends Factory
{
    public function definition(): array
    {
        $streakCurrent = fake()->numberBetween(0, 60);
        $streakBest    = fake()->numberBetween($streakCurrent, 120);
        $activeCurrent = fake()->numberBetween(0, 30);
        $activeTotal   = fake()->numberBetween($activeCurrent, 365);
        $level         = fake()->numberBetween(1, 50);

        return [
            'user_uuid'           => User::factory(),
            'xp_total'            => $level * fake()->numberBetween(80, 150),
            'level'               => $level,
            'streak_current_days' => $streakCurrent,
            'streak_best_days'    => $streakBest,
            'last_streak_day'     => $streakCurrent > 0 ? now()->subDays(1)->format('Y-m-d') : null,
            'active_days_total'   => $activeTotal,
            'active_days_current' => $activeCurrent,
            'last_active_day'     => $activeCurrent > 0 ? now()->format('Y-m-d') : null,
        ];
    }

    public function beginner(): static
    {
        return $this->state(fn () => [
            'xp_total' => fake()->numberBetween(0, 500),
            'level'    => 1,
            'streak_current_days' => 0,
        ]);
    }
}
