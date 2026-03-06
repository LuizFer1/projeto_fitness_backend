<?php

namespace Database\Factories;

use App\Models\Workout;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutExerciseFactory extends Factory
{
    public function definition(): array
    {
        $exercises = [
            'Supino Reto', 'Supino Inclinado', 'Desenvolvimento',
            'Agachamento', 'Leg Press', 'Stiff',
            'Remada Curvada', 'Puxada Frontal', 'Rosca Direta',
            'Tríceps Corda', 'Elevação Lateral', 'Prancha',
            'Flexão de Braço', 'Cadeira Extensora', 'Mesa Flexora',
        ];

        return [
            'workout_uuid' => Workout::factory(),
            'name'         => fake()->randomElement($exercises),
            'sets'         => fake()->numberBetween(3, 5),
            'reps'         => fake()->randomElement(['8', '10', '12', '15', '8-12', '10-15']),
            'weight'       => fake()->randomElement(['20kg', '30kg', '40kg', '50kg', '60kg', null]),
            'rest_seconds' => fake()->randomElement([30, 45, 60, 90, 120]),
            'done'         => fake()->boolean(40),
            'sort_order'   => fake()->numberBetween(0, 10),
        ];
    }
}
