<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class DashboardResource extends JsonResource
{
    private const DEFAULT_CARBS_GOAL = 250;
    private const DEFAULT_PROTEIN_GOAL = 150;
    private const DEFAULT_FAT_GOAL = 70;
    private const DEFAULT_CALORIES_GOAL = 2500;
    private const DEFAULT_WATER_GOAL_ML = 2000;
    private const DEFAULT_WEEKLY_SESSIONS = 5;
    private const GLASS_SIZE_ML = 250;
    private const KCAL_PER_GRAM_CARB = 4;
    private const KCAL_PER_GRAM_PROTEIN = 4;
    private const KCAL_PER_GRAM_FAT = 9;

    public function toArray(Request $request): array
    {
        $nutrition = $this['nutrition'] ?? null;
        $exercise  = $this['exercise'] ?? null;
        $weightLog = $this['weight'] ?? null;

        $macros = $this->resolveMacros($nutrition);
        $kcal = $this->calculateKcalBreakdown($macros);
        $hydration = $this->resolveHydration($nutrition);
        $weekly = $this->buildWeeklyActivity($this['recentNutrition'] ?? collect(), $this['recentWorkouts'] ?? collect());

        return [
            'dailyCalories' => [
                'goal'     => $nutrition && $nutrition->calories_goal ? $nutrition->calories_goal : self::DEFAULT_CALORIES_GOAL,
                'consumed' => $kcal['total'],
            ],
            'protein' => [
                'goal'    => $macros['proteinGoal'],
                'current' => $macros['protein'],
            ],
            'weeklyWorkouts' => [
                'goal' => $exercise ? $exercise->goal_sessions : self::DEFAULT_WEEKLY_SESSIONS,
                'done' => $exercise ? $exercise->done_sessions : 0,
            ],
            'hydration' => [
                'current' => $hydration['current_ml'] / 1000,
                'glasses' => $hydration['glasses'],
            ],
            'activityChart' => $weekly['chart'],
            'trainingDays'  => $weekly['trainingDays'],
            'activeDays'    => $weekly['activeDays'],
            'weekDays'      => $weekly['weekDays'],
            'currentWeight' => [
                'value'        => $weightLog ? (float) $weightLog->weight_kg : 0,
                'weeklyChange' => 0,
            ],
            'meals'             => $this->formatMeals($this['meals'] ?? collect()),
            'suggestedWorkouts' => $this->formatSuggestedWorkouts($this['suggestedWorkouts'] ?? collect()),
            'userGoals'         => $this['goals'] ?? collect(),
            'macros'            => $this->formatMacros($macros, $kcal),
        ];
    }

    private function resolveMacros($nutrition): array
    {
        $defaults = [
            'carbs'       => 0,
            'protein'     => 0,
            'fat'         => 0,
            'carbsGoal'   => self::DEFAULT_CARBS_GOAL,
            'proteinGoal' => self::DEFAULT_PROTEIN_GOAL,
            'fatGoal'     => self::DEFAULT_FAT_GOAL,
        ];

        if (!$nutrition || !$nutrition->relationLoaded('macros')) {
            return $defaults;
        }

        $carbs   = $nutrition->macros->firstWhere('label', 'Carbo');
        $protein = $nutrition->macros->firstWhere('label', 'Proteína');
        $fat     = $nutrition->macros->firstWhere('label', 'Gordura');

        return [
            'carbs'       => $carbs ? $carbs->current_value : 0,
            'protein'     => $protein ? $protein->current_value : 0,
            'fat'         => $fat ? $fat->current_value : 0,
            'carbsGoal'   => $this->goalOrDefault($carbs, self::DEFAULT_CARBS_GOAL),
            'proteinGoal' => $this->goalOrDefault($protein, self::DEFAULT_PROTEIN_GOAL),
            'fatGoal'     => $this->goalOrDefault($fat, self::DEFAULT_FAT_GOAL),
        ];
    }

    private function goalOrDefault($macro, int $default): int|float
    {
        return $macro && $macro->goal_value > 0 ? $macro->goal_value : $default;
    }

    private function calculateKcalBreakdown(array $macros): array
    {
        $fromCarbs   = $macros['carbs']   * self::KCAL_PER_GRAM_CARB;
        $fromProtein = $macros['protein'] * self::KCAL_PER_GRAM_PROTEIN;
        $fromFat     = $macros['fat']     * self::KCAL_PER_GRAM_FAT;
        $total       = $fromCarbs + $fromProtein + $fromFat;

        return [
            'fromCarbs'   => $fromCarbs,
            'fromProtein' => $fromProtein,
            'fromFat'     => $fromFat,
            'total'       => $total,
            'carbsPct'    => $total > 0 ? ($fromCarbs / $total) * 100 : 0,
            'proteinPct'  => $total > 0 ? ($fromProtein / $total) * 100 : 0,
            'fatPct'      => $total > 0 ? ($fromFat / $total) * 100 : 0,
        ];
    }

    private function resolveHydration($nutrition): array
    {
        $goalMl    = $nutrition && $nutrition->water_goal > 0 ? $nutrition->water_goal : self::DEFAULT_WATER_GOAL_ML;
        $currentMl = $nutrition ? $nutrition->water_current : 0;

        $total = (int) ceil($goalMl / self::GLASS_SIZE_ML);
        $drunk = (int) floor($currentMl / self::GLASS_SIZE_ML);

        $glasses = [];
        for ($i = 0; $i < max($total, $drunk); $i++) {
            $glasses[] = $i < $drunk;
        }

        return ['current_ml' => $currentMl, 'glasses' => $glasses];
    }

    private function buildWeeklyActivity(Collection $recentNutrition, Collection $recentWorkouts): array
    {
        $chart = [];
        $trainingDays = [];
        $activeDays = [];
        $weekDays = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->toDateString();

            $dayNutrition = $recentNutrition->firstWhere('day', $dateStr);
            $dayWorkout   = $recentWorkouts->firstWhere('workout_date', $dateStr);

            $fg = $this->scoreDay($dayNutrition, $dayWorkout);

            if ($dayWorkout) {
                $trainingDays[] = substr($date->locale('pt_BR')->dayName, 0, 1);
            }

            $chart[]    = ['bg' => 100, 'fg' => $fg];
            $weekDays[] = ucfirst(substr($date->locale('pt_BR')->dayName, 0, 3));

            if ($fg >= 25) {
                $activeDays[] = 6 - $i;
            }
        }

        return compact('chart', 'trainingDays', 'activeDays', 'weekDays');
    }

    private function scoreDay($dayNutrition, $dayWorkout): int
    {
        $fg = 0;

        if ($dayNutrition) {
            $fg += $this->scoreCalories($dayNutrition);
            $fg += $this->scoreHydration($dayNutrition);
        }

        if ($dayWorkout) {
            $fg += 25;
        }

        return min($fg, 100);
    }

    private function scoreCalories($dayNutrition): int
    {
        if ($dayNutrition->calories_goal > 0 && $dayNutrition->calories_total >= $dayNutrition->calories_goal * 0.8) {
            return 50;
        }

        return $dayNutrition->calories_total > 0 ? 25 : 0;
    }

    private function scoreHydration($dayNutrition): int
    {
        return $dayNutrition->water_goal > 0 && $dayNutrition->water_current >= $dayNutrition->water_goal ? 25 : 0;
    }

    private function formatMeals(Collection $meals): array
    {
        return $meals->map(fn ($meal) => [
            'id'       => (string) $meal->id,
            'name'     => $meal->name,
            'detail'   => $meal->detail,
            'calories' => $meal->kcal,
            'image'    => $meal->img_url,
        ])->values()->all();
    }

    private function formatSuggestedWorkouts(Collection $workouts): array
    {
        return $workouts->map(fn ($wk) => [
            'id'       => (string) $wk->id,
            'name'     => $wk->name,
            'duration' => $wk->duration_min,
            'level'    => $wk->level,
            'category' => $wk->category,
            'image'    => $wk->img_url,
        ])->values()->all();
    }

    private function formatMacros(array $macros, array $kcal): array
    {
        return [
            [
                'name'       => 'Carbo',
                'current'    => $macros['carbs'],
                'goal'       => $macros['carbsGoal'],
                'percentage' => round($kcal['carbsPct'], 1),
                'color'      => '#3b82f6',
                'unit'       => 'g',
            ],
            [
                'name'       => 'Proteína',
                'current'    => $macros['protein'],
                'goal'       => $macros['proteinGoal'],
                'percentage' => round($kcal['proteinPct'], 1),
                'color'      => '#ef4444',
                'unit'       => 'g',
            ],
            [
                'name'       => 'Gordura',
                'current'    => $macros['fat'],
                'goal'       => $macros['fatGoal'],
                'percentage' => round($kcal['fatPct'], 1),
                'color'      => '#eab308',
                'unit'       => 'g',
            ],
        ];
    }
}
