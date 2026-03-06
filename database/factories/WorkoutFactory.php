<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutFactory extends Factory
{
    public function definition(): array
    {
        $workouts = [
            'Treino de Peito e Tríceps',
            'Treino de Costas e Bíceps',
            'Treino de Pernas',
            'Treino de Ombros',
            'HIIT Cardio',
            'Treino Full Body',
            'Corrida Leve',
            'Treino de Core',
            'Mobilidade e Alongamento',
        ];

        return [
            'user_uuid'    => User::factory(),
            'name'         => fake()->randomElement($workouts),
            'duration_min' => fake()->randomElement([30, 45, 60, 75, 90]),
            'calories'     => fake()->numberBetween(150, 600),
            'level'        => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
            'category'     => fake()->randomElement(['FORCA', 'CARDIO', 'MOBILIDADE', 'OUTRO']),
            'workout_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ];
    }
}
