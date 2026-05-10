<?php

namespace App\Services\Diet;

use App\Application\UseCases\Onboarding\CalculateDailyCaloriesUseCase;
use App\Models\UserOnboarding;

/**
 * Builds macro/calorie goals for a user based on their objective and onboarding.
 *
 * Reuses CalculateDailyCaloriesUseCase (Mifflin-St Jeor + activity factor) so
 * we never duplicate BMR/TDEE math across the codebase.
 */
class MacroGoalCalculator
{
    /**
     * Splits per objective: protein% / carbs% / fat% of total daily kcal,
     * plus a delta applied to TDEE (negative = cut, positive = bulk).
     */
    private const SPLITS = [
        'weight_loss' => ['delta_min' => -500, 'delta_max' => -300, 'pct' => [0.35, 0.40, 0.25]],
        'muscle_gain' => ['delta_min' => 200, 'delta_max' => 400, 'pct' => [0.30, 0.50, 0.20]],
        'maintenance' => ['delta_min' => 0, 'delta_max' => 0, 'pct' => [0.20, 0.50, 0.30]],
    ];

    public function __construct(private CalculateDailyCaloriesUseCase $calories) {}

    /**
     * @return array{calories: int, protein: float, carbs: float, fat: float}|null
     */
    public function calculateForObjective(UserOnboarding $onboarding, string $objective): ?array
    {
        $tdee = $this->calories->execute($onboarding->toArray())['tdee'] ?? null;

        if ($tdee === null) {
            return null;
        }

        $config = self::SPLITS[$objective] ?? self::SPLITS['maintenance'];
        $delta = $this->pickDelta($config);
        $calories = max(0, $tdee + $delta);

        [$pPct, $cPct, $fPct] = $config['pct'];

        return [
            'calories' => (int) round($calories),
            'protein' => round(($calories * $pPct) / 4, 1),
            'carbs' => round(($calories * $cPct) / 4, 1),
            'fat' => round(($calories * $fPct) / 9, 1),
        ];
    }

    private function pickDelta(array $config): int
    {
        if ($config['delta_min'] === $config['delta_max']) {
            return $config['delta_min'];
        }

        return random_int($config['delta_min'], $config['delta_max']);
    }
}
