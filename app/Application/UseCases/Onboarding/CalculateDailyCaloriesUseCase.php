<?php

namespace App\Application\UseCases\Onboarding;

class CalculateDailyCaloriesUseCase
{
    public function execute(array $data): array
    {
        $bmr = $this->calculateBmr($data);
        if ($bmr === null) {
            return ['bmr' => null, 'tdee' => null];
        }

        $tdee = (int) round($bmr * $this->activityMultiplier($data));

        return ['bmr' => (int) round($bmr), 'tdee' => $tdee];
    }

    private function calculateBmr(array $data): ?float
    {
        foreach (['gender', 'age', 'weight_kg', 'height_cm'] as $required) {
            if (empty($data[$required])) {
                return null;
            }
        }

        $weight = (float) $data['weight_kg'];
        $height = (int) $data['height_cm'];
        $age    = (int) $data['age'];

        $base = (10 * $weight) + (6.25 * $height) - (5 * $age);

        return $data['gender'] === 'male' ? $base + 5 : $base - 161;
    }

    private function activityMultiplier(array $data): float
    {
        $multiplier = $this->workoutMultiplier((int) ($data['workouts_per_week'] ?? 0));

        if (in_array($data['work_style'] ?? '', ['active', 'blue_collar'], true)) {
            $multiplier += 0.15;
        }

        return $multiplier;
    }

    private function workoutMultiplier(int $workouts): float
    {
        return match (true) {
            $workouts >= 6 => 1.725,
            $workouts >= 3 => 1.55,
            $workouts >= 1 => 1.375,
            default        => 1.2,
        };
    }
}
