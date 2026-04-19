<?php

namespace App\Application\UseCases\Onboarding;

class CalculateDailyCaloriesUseCase
{
    /**
     * Calculates the Basal Metabolic Rate (BMR) and Total Daily Energy Expenditure (TDEE).
     *
     * @param array $data
     * @return array Contains 'bmr' and 'tdee' keys, which might be null if data is insufficient.
     */
    public function execute(array $data): array
    {
        $bmr = null;
        $tdee = null;

        if (
            !empty($data['gender']) && 
            !empty($data['age']) && 
            !empty($data['weight_kg']) && 
            !empty($data['height_cm'])
        ) {
            $w = (float) $data['weight_kg'];
            $h = (int) $data['height_cm'];
            $a = (int) $data['age'];
            
            if ($data['gender'] === 'male') {
                $bmr = (10 * $w) + (6.25 * $h) - (5 * $a) + 5;
            } else {
                $bmr = (10 * $w) + (6.25 * $h) - (5 * $a) - 161;
            }
        }

        if ($bmr) {
            $activityMultiplier = 1.2;
            
            $workouts = (int) ($data['workouts_per_week'] ?? 0);
            if ($workouts >= 6) {
                $activityMultiplier = 1.725;
            } elseif ($workouts >= 3) {
                $activityMultiplier = 1.55;
            } elseif ($workouts >= 1) {
                $activityMultiplier = 1.375;
            }

            $workStyle = $data['work_style'] ?? '';
            if ($workStyle === 'active' || $workStyle === 'blue_collar') {
                $activityMultiplier += 0.15;
            }

            $tdee = (int) round($bmr * $activityMultiplier);
        }

        return [
            'bmr' => $bmr ? (int) round($bmr) : null,
            'tdee' => $tdee
        ];
    }
}
