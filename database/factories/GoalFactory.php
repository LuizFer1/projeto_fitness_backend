<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoalFactory extends Factory
{
    public function definition(): array
    {
        $category = fake()->randomElement(['alimentation', 'exercise']);

        $typeMap = [
            'alimentation' => ['water', 'calories', 'sleep'],
            'exercise'     => ['workout', 'steps', 'weight'],
        ];

        $type = fake()->randomElement($typeMap[$category]);

        $unitMap = [
            'water'    => ['target' => 3000, 'unit' => 'ml'],
            'calories' => ['target' => 2200, 'unit' => 'kcal'],
            'sleep'    => ['target' => 480,  'unit' => 'min'],
            'workout'  => ['target' => 5,    'unit' => 'sessions'],
            'steps'    => ['target' => 10000,'unit' => 'steps'],
            'weight'   => ['target' => 75,   'unit' => 'kg'],
        ];

        return [
            'user_uuid' => User::factory(),
            'category' => $category,
            'type'     => $type,
            'label'    => ucfirst($type) . ' Goal',
            'target'   => $unitMap[$type]['target'],
            'unit'     => $unitMap[$type]['unit'],
            'period'   => fake()->randomElement(['daily', 'weekly', 'monthly']),
            'active'   => true,
        ];
    }
}
