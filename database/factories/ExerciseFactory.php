<?php

namespace Database\Factories;

use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    public function definition(): array
    {
        return [
            'name'         => fake()->unique()->words(2, true),
            'muscle_group' => fake()->randomElement(['chest', 'back', 'legs', 'shoulder']),
            'category'     => 'strength',
            'difficulty'   => 'beginner',
            'equipment'    => 'bodyweight',
            'is_active'    => true,
        ];
    }
}
