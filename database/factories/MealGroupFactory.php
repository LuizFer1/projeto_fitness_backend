<?php

namespace Database\Factories;

use App\Models\NutritionDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

class MealGroupFactory extends Factory
{
    private static int $sortCounter = 0;

    public function definition(): array
    {
        $groups = [
            ['label' => 'Café da Manhã', 'emoji' => '☀️'],
            ['label' => 'Almoço',        'emoji' => '🍽️'],
            ['label' => 'Lanche',        'emoji' => '🍎'],
            ['label' => 'Jantar',        'emoji' => '🌙'],
        ];

        $group = fake()->randomElement($groups);

        return [
            'nutrition_day' => NutritionDaily::factory(),
            'label'         => $group['label'],
            'emoji'         => $group['emoji'],
            'sort_order'    => self::$sortCounter++,
        ];
    }
}
