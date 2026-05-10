<?php

namespace App\Application\UseCases\Nutrition;

use App\Models\AiPlan;
use App\Models\MealLog;
use App\Models\User;
use Carbon\Carbon;

class RedistributeMacrosUseCase
{
    /**
     * After a meal is logged, adjust remaining plan_meals of the active nutritional plan
     * proportionally to reflect actual consumption vs. goal.
     */
    public function execute(User $user, MealLog $mealLog): void
    {
        $plan = AiPlan::where('user_id', $user->id)
            ->where('type', 'nutritional')
            ->where('status', 'active')
            ->first();

        if (! $plan) {
            return;
        }

        $date = $mealLog->date instanceof Carbon
            ? $mealLog->date->toDateString()
            : (string) $mealLog->date;

        $futureMeals = $plan->planMeals()
            ->where('scheduled_date', '>', $date)
            ->orderBy('scheduled_date')
            ->get();

        if ($futureMeals->isEmpty()) {
            return;
        }

        // Calories consumed today across all meal logs
        $consumedToday = MealLog::where('user_id', $user->id)
            ->where('date', $date)
            ->sum('calories_consumed');

        $goalCalories = $user->onboarding?->tdee ?? 2000;

        if ($goalCalories <= 0) {
            return;
        }

        $ratio = max(0.5, min(1.5, $consumedToday / $goalCalories));

        foreach ($futureMeals as $planMeal) {
            $content = $planMeal->content_json ?? [];

            if (empty($content)) {
                continue;
            }

            // Scale each macro proportionally (inverse of consumption ratio to rebalance)
            $scaleFactor = (2 - $ratio); // if over-eaten (ratio>1) future meals shrink; if under (ratio<1) they grow

            foreach (['calories', 'protein_g', 'carbs_g', 'fat_g'] as $field) {
                if (isset($content[$field]) && is_numeric($content[$field])) {
                    $content[$field] = round($content[$field] * $scaleFactor, 1);
                }
            }

            $planMeal->content_json = $content;
            $planMeal->save();
        }
    }
}
