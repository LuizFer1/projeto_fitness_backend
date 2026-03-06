<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExerciseWeekFactory extends Factory
{
    public function definition(): array
    {
        $goalSessions = fake()->numberBetween(3, 6);

        return [
            'user_uuid'       => User::factory(),
            'week_start'      => fake()->dateTimeBetween('-8 weeks', 'now')->modify('monday this week')->format('Y-m-d'),
            'goal_sessions'   => $goalSessions,
            'done_sessions'   => fake()->numberBetween(0, $goalSessions),
            'calories_burned' => fake()->numberBetween(500, 3000),
            'total_minutes'   => fake()->numberBetween(60, 600),
            'streak_days'     => fake()->numberBetween(0, 7),
        ];
    }
}
