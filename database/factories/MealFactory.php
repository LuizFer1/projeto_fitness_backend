<?php

namespace Database\Factories;

use App\Models\MealGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class MealFactory extends Factory
{
    public function definition(): array
    {
        $meals = [
            ['name' => 'Frango Grelhado',       'kcal' => 250, 'protein' => 35, 'carbs' => 0,  'fat' => 8],
            ['name' => 'Arroz Integral',         'kcal' => 180, 'protein' => 4,  'carbs' => 38, 'fat' => 1.5],
            ['name' => 'Salada Verde',           'kcal' => 45,  'protein' => 2,  'carbs' => 8,  'fat' => 0.5],
            ['name' => 'Ovo Cozido',             'kcal' => 78,  'protein' => 6,  'carbs' => 0.6,'fat' => 5],
            ['name' => 'Batata Doce',            'kcal' => 120, 'protein' => 2,  'carbs' => 28, 'fat' => 0],
            ['name' => 'Whey Protein',           'kcal' => 120, 'protein' => 24, 'carbs' => 3,  'fat' => 1.5],
            ['name' => 'Banana',                 'kcal' => 105, 'protein' => 1.3,'carbs' => 27, 'fat' => 0.3],
            ['name' => 'Aveia',                  'kcal' => 150, 'protein' => 5,  'carbs' => 27, 'fat' => 2.5],
            ['name' => 'Feijão',                 'kcal' => 130, 'protein' => 8,  'carbs' => 22, 'fat' => 0.5],
            ['name' => 'Salmão Grelhado',        'kcal' => 280, 'protein' => 30, 'carbs' => 0,  'fat' => 16],
        ];

        $meal = fake()->randomElement($meals);

        return [
            'meal_group_uuid' => MealGroup::factory(),
            'name'          => $meal['name'],
            'detail'        => fake()->optional()->sentence(4),
            'kcal'          => $meal['kcal'],
            'protein_g'     => $meal['protein'],
            'carbs_g'       => $meal['carbs'],
            'fat_g'         => $meal['fat'],
            'img_url'       => null,
            'time_hhmm'     => fake()->time('H:i'),
        ];
    }
}
