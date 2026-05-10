<?php

namespace App\Services\Diet;

use App\Models\DietAdjustment;
use App\Models\MealLog;
use App\Models\NutritionDaily;
use App\Models\User;
use Carbon\Carbon;

class DietEngineService
{
    private const SAFETY_RATIO = 0.20;

    private const DILUTION_DAYS = 3;

    /**
     * Recalculates the user's nutritional delta after a meal is logged.
     * Applies the 20% Safety Rule (SRS RF-03): if the excess exceeds 20% of TDEE,
     * the compensation is diluted over the next 3 days instead of same-day.
     *
     * Called synchronously from MealLogController — must stay under 500ms (RF-01).
     */
    public function recalculateAfterMeal(User $user, MealLog $mealLog): NutritionDaily
    {
        $date = $mealLog->date instanceof Carbon
            ? $mealLog->date->toDateString()
            : (string) $mealLog->date;

        $daily = $this->getOrCreateDaily($user, $date);
        $daily = $this->aggregateTodayMeals($user, $daily, $date);

        $deltaKcal = $daily->calories_consumed - $daily->calories_goal;
        $adjustmentRatio = $daily->calories_goal > 0
            ? abs($deltaKcal) / $daily->calories_goal
            : 0;

        $daily->update([
            'delta_kcal' => $deltaKcal,
            'adjustment_ratio' => round($adjustmentRatio, 4),
        ]);

        if ($deltaKcal <= 0) {
            // Under or on target — no compensation needed
            $daily->update(['dilution_active' => false]);

            return $daily->refresh();
        }

        if ($adjustmentRatio > self::SAFETY_RATIO) {
            $this->createDilutionAdjustments($user, $mealLog, $daily, $deltaKcal, $date);
            $daily->update(['dilution_active' => true]);
        } else {
            $this->createSameDayAdjustment($user, $mealLog, $daily, $deltaKcal, $date);
            $daily->update(['dilution_active' => false]);
        }

        return $daily->refresh();
    }

    /**
     * Returns today's nutrition summary for a user, initialising the record if missing.
     */
    public function getTodaySummary(User $user): NutritionDaily
    {
        $today = Carbon::now($user->timezone ?? 'UTC')->toDateString();

        return $this->getOrCreateDaily($user, $today);
    }

    // ────────────────────────────────────────────────────────────────

    private function getOrCreateDaily(User $user, string $date): NutritionDaily
    {
        $existing = NutritionDaily::where('user_id', $user->id)
            ->whereDate('day', $date)
            ->first();

        if ($existing) {
            return $existing;
        }

        $goal = $user->goal;

        return NutritionDaily::create([
            'user_id' => $user->id,
            'day' => $date,
            'calories_goal' => $goal->goal_calories_day ?? 0,
            'protein_goal_g' => $goal->goal_protein_g ?? 0,
            'carbs_goal_g' => $goal->goal_carbs_g ?? 0,
            'fat_goal_g' => $goal->goal_fat_g ?? 0,
        ]);
    }

    private function aggregateTodayMeals(User $user, NutritionDaily $daily, string $date): NutritionDaily
    {
        $totals = MealLog::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->selectRaw('
                COALESCE(SUM(calories_consumed), 0) AS kcal,
                COALESCE(SUM(protein_g), 0)         AS protein,
                COALESCE(SUM(carbs_g), 0)           AS carbs,
                COALESCE(SUM(fat_g), 0)             AS fat
            ')
            ->first();

        $daily->update([
            'calories_consumed' => (int) $totals->kcal,
            'protein_consumed_g' => (int) $totals->protein,
            'carbs_consumed_g' => (int) $totals->carbs,
            'fat_consumed_g' => (int) $totals->fat,
        ]);

        return $daily->refresh();
    }

    private function createSameDayAdjustment(
        User $user, MealLog $mealLog, NutritionDaily $daily, int $deltaKcal, string $date
    ): void {
        [$dp, $dc, $df] = $this->splitDeltaToMacros($daily, $deltaKcal);

        DietAdjustment::updateOrCreate(
            ['user_id' => $user->id, 'source_meal_log_id' => $mealLog->id, 'target_date' => $date],
            [
                'delta_kcal' => $deltaKcal,
                'delta_protein_g' => $dp,
                'delta_carbs_g' => $dc,
                'delta_fat_g' => $df,
                'mode' => 'same_day',
                'applied_at' => null,
            ]
        );
    }

    private function createDilutionAdjustments(
        User $user, MealLog $mealLog, NutritionDaily $daily, int $deltaKcal, string $date
    ): void {
        $perDay = (int) ceil($deltaKcal / self::DILUTION_DAYS);
        $base = Carbon::parse($date);

        for ($i = 1; $i <= self::DILUTION_DAYS; $i++) {
            $targetDate = $base->copy()->addDays($i)->toDateString();
            $kcalThisDay = ($i < self::DILUTION_DAYS) ? $perDay : ($deltaKcal - $perDay * (self::DILUTION_DAYS - 1));
            [$dp, $dc, $df] = $this->splitDeltaToMacros($daily, $kcalThisDay);

            DietAdjustment::updateOrCreate(
                ['user_id' => $user->id, 'source_meal_log_id' => $mealLog->id, 'target_date' => $targetDate],
                [
                    'delta_kcal' => $kcalThisDay,
                    'delta_protein_g' => $dp,
                    'delta_carbs_g' => $dc,
                    'delta_fat_g' => $df,
                    'mode' => 'dilution',
                    'applied_at' => null,
                ]
            );
        }
    }

    /**
     * Splits a kcal delta into macro grams using the user's current macro ratio.
     * Returns [protein_g, carbs_g, fat_g].
     */
    private function splitDeltaToMacros(NutritionDaily $daily, int $kcal): array
    {
        $totalGoal = max(
            $daily->calories_goal,
            ($daily->protein_goal_g * 4) + ($daily->carbs_goal_g * 4) + ($daily->fat_goal_g * 9)
        );

        if ($totalGoal <= 0) {
            // Fallback to 40/30/30 split (protein/carbs/fat)
            return [
                (int) round($kcal * 0.40 / 4),
                (int) round($kcal * 0.30 / 4),
                (int) round($kcal * 0.30 / 9),
            ];
        }

        $proteinKcal = $daily->protein_goal_g * 4;
        $carbsKcal = $daily->carbs_goal_g * 4;
        $fatKcal = $daily->fat_goal_g * 9;

        return [
            (int) round($kcal * ($proteinKcal / $totalGoal) / 4),
            (int) round($kcal * ($carbsKcal / $totalGoal) / 4),
            (int) round($kcal * ($fatKcal / $totalGoal) / 9),
        ];
    }
}
