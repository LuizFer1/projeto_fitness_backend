<?php

namespace App\Application\UseCases\Onboarding;

class CalculateDailyCaloriesUseCase
{
    public function execute(array $data): array
    {
        $formula = $data['tdee_formula'] ?? 'mifflin';
        $bmr = $this->calculateBmr($data, $formula);

        if ($bmr === null) {
            return ['bmr' => null, 'tdee' => null];
        }

        $factor = isset($data['activity_factor']) && $data['activity_factor'] !== null
            ? (float) $data['activity_factor']
            : $this->activityMultiplier($data);

        $tdee = (int) round($bmr * $factor);

        return [
            'bmr' => (int) round($bmr),
            'tdee' => $tdee,
            'formula' => $formula,
            'activity_factor' => round($factor, 2),
        ];
    }

    private function calculateBmr(array $data, string $formula): ?float
    {
        foreach (['gender', 'age', 'weight_kg', 'height_cm'] as $required) {
            if (empty($data[$required])) {
                return null;
            }
        }

        $weight = (float) $data['weight_kg'];
        $height = (float) $data['height_cm'];
        $age = (int) $data['age'];

        return match ($formula) {
            'harris' => $this->harrisBenedict($data['gender'], $weight, $height, $age),
            default => $this->mifflinStJeor($data['gender'], $weight, $height, $age),
        };
    }

    private function mifflinStJeor(string $gender, float $weight, float $height, int $age): float
    {
        $base = (10 * $weight) + (6.25 * $height) - (5 * $age);

        return $gender === 'male' ? $base + 5 : $base - 161;
    }

    private function harrisBenedict(string $gender, float $weight, float $height, int $age): float
    {
        return $gender === 'male'
            ? 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age)
            : 447.593 + (9.247 * $weight) + (3.098 * $height) - (4.330 * $age);
    }

    private function activityMultiplier(array $data): float
    {
        $multiplier = $this->workoutMultiplier((int) ($data['exercise_frequency'] ?? $data['workouts_per_week'] ?? 0));

        if (in_array($data['work_style'] ?? '', ['active', 'blue_collar', 'very_active'], true)) {
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
            default => 1.2,
        };
    }
}
