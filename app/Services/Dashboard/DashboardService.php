<?php

namespace App\Services\Dashboard;

use App\Models\AiPlan;
use App\Models\MealLog;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserGoal;
use App\Models\UserOnboarding;
use App\Models\WaterLog;
use App\Models\WorkoutLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates the data shown on the Dashboard, Alimentação and Exercícios screens.
 *
 * Keeps DB access out of controllers and centralises macro/water/streak math
 * so the same numbers feed every dashboard endpoint.
 */
class DashboardService
{
    private const DEFAULT_WATER_GOAL_LITERS = 2.0;

    /** Portuguese fallback labels for meal_type when no Meal record is linked. */
    private const MEAL_TYPE_LABELS = [
        'breakfast' => 'Café da manhã',
        'lunch' => 'Almoço',
        'dinner' => 'Jantar',
        'snack' => 'Lanche',
        'pre_workout' => 'Pré-treino',
        'post_workout' => 'Pós-treino',
    ];

    public function buildHome(User $user): array
    {
        $today = Carbon::today();
        $todayStr = $today->toDateString();
        $weekStart = $today->copy()->startOfWeek()->toDateString();

        $goal = $this->activeGoal($user);
        $gamif = $this->gamification($user);
        $onboarding = UserOnboarding::where('user_id', $user->id)->first();

        $meals = $this->mealsOn($user, $todayStr);
        $weeklyWorkouts = $this->weeklyWorkouts($user, $weekStart, $todayStr);
        $trainingDays = $weeklyWorkouts->pluck('date')->map(fn ($d) => $this->dateOnly($d))->unique()->values();
        $waterToday = $this->waterTotal($user, $todayStr);
        $last7Days = $this->nutritionTrend($user, $today, 7);

        return [
            'today' => $todayStr,
            'dailyCalories' => $this->caloriesPair($meals, $goal),
            'protein' => $this->macroPair($meals, $goal, 'protein_g', 'goal_protein_g'),
            'macros' => $this->macroBreakdown($meals, $goal),
            'currentWeight' => $onboarding?->weight_kg,
            'meals' => $this->enrichMeals($meals),
            'trainingDays' => $trainingDays,
            'weeklyWorkouts' => [
                'done' => $trainingDays->count(),
                'goal' => $goal?->goal_workouts_week,
            ],
            'suggestedWorkouts' => $this->enrichSuggestedWorkouts($this->activeWorkoutSuggestions($user)),
            'todayWorkouts' => $this->todayWorkouts($user, $today),
            'hydration' => $this->hydrationPair($waterToday, $goal),
            'nutritionChart' => $last7Days,
            'gamification' => $this->formatGamification($gamif),
        ];
    }

    public function buildAlimentation(User $user): array
    {
        $today = Carbon::today()->toDateString();
        $goal = $this->activeGoal($user);
        $meals = $this->mealsOn($user, $today);
        $water = $this->waterTotal($user, $today);

        return [
            'date' => $today,
            'dailyGoal' => $this->alimentationGoals($goal),
            'consumed' => $this->alimentationConsumed($meals),
            'macros' => $this->macroTotals($meals),
            'water' => $this->hydrationPair($water, $goal),
            'mealGroups' => $this->groupMealsByType($meals),
        ];
    }

    public function buildExercise(User $user): array
    {
        $today = Carbon::today();
        $todayStr = $today->toDateString();
        $weekStart = $today->copy()->startOfWeek()->toDateString();
        $goal = $this->activeGoal($user);

        $weeklyWorkouts = $this->weeklyWorkouts($user, $weekStart, $todayStr, withExercises: true);
        $weeklyDone = $weeklyWorkouts->pluck('date')->map(fn ($d) => $this->dateOnly($d))->unique()->count();
        $todayWorkout = $weeklyWorkouts->filter(fn ($w) => $this->dateOnly($w->date) === $todayStr)->values();

        return [
            'date' => $todayStr,
            'weeklyGoal' => $goal?->goal_workouts_week,
            'weeklyDone' => $weeklyDone,
            'stats' => [
                'calories_burned' => round((float) $weeklyWorkouts->sum('calories_burned')),
                'total_duration' => (int) $weeklyWorkouts->sum('duration_min'),
                'total_workouts' => $weeklyDone,
            ],
            'todayWorkout' => $todayWorkout,
            'weekDays' => $this->weekBreakdown($weeklyWorkouts, $today),
            'history' => $this->workoutHistory($user, $today, 30),
            'active_plan' => $this->activeWorkoutPlan($user),
        ];
    }

    // ── Shared helpers ────────────────────────────────────────────────

    private function activeGoal(User $user): ?UserGoal
    {
        return UserGoal::where('user_id', $user->id)->where('is_active', true)->first();
    }

    private function gamification(User $user): ?UserGamification
    {
        return UserGamification::where('user_id', $user->id)->first();
    }

    private function mealsOn(User $user, string $date): Collection
    {
        return MealLog::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->with('meal:id,name')
            ->orderBy('created_at')
            ->get();
    }

    private function weeklyWorkouts(User $user, string $from, string $to, bool $withExercises = false): Collection
    {
        $q = WorkoutLog::where('user_id', $user->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to);
        if ($withExercises) {
            $q->with('workoutLogExercises');
        }

        return $q->orderBy('date')->get();
    }

    private function waterTotal(User $user, string $date): float
    {
        return (float) WaterLog::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->sum('liters');
    }

    private function nutritionTrend(User $user, Carbon $today, int $days): Collection
    {
        return MealLog::where('user_id', $user->id)
            ->whereDate('date', '>=', $today->copy()->subDays($days - 1)->toDateString())
            ->selectRaw('DATE(date) as day, SUM(calories_consumed) as calories, SUM(protein_g) as protein, SUM(carbs_g) as carbs, SUM(fat_g) as fat')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    private function activeWorkoutPlan(User $user): ?AiPlan
    {
        return AiPlan::where('user_id', $user->id)
            ->where('type', 'workout')
            ->where('status', 'active')
            ->with([
                'planWorkouts.exercises',
                'planWorkouts.exercises.exercise:id,name,thumbnail_url,muscle_group,difficulty',
            ])
            ->first();
    }

    private function activeWorkoutSuggestions(User $user): Collection
    {
        $plan = $this->activeWorkoutPlan($user);

        return $plan?->planWorkouts ?? collect();
    }

    /**
     * Adds top-level `name` and `image_url` keys to each MealLog row so the
     * frontend can render rich labels without re-deriving them from `meal_type`.
     *
     * Note: the `meals` table does not currently store an image URL, so
     * `image_url` is always null until that column is added.
     */
    private function enrichMeals(Collection $meals): Collection
    {
        return $meals->map(function ($log) {
            $payload = $log->toArray();
            $relatedName = $log->meal?->name;
            $payload['name'] = $relatedName ?: (self::MEAL_TYPE_LABELS[$log->meal_type] ?? 'Refeição');
            // `meals` table has no image column today; expose the field for
            // forward-compat so the FE can stop synthesising it.
            $payload['image_url'] = null;

            return $payload;
        })->values();
    }

    /**
     * Adds `duration_min`, `level`, `category` and `image_url` derived from the
     * exercises eager-loaded on each PlanWorkout. Original `workout_name` and
     * nested `exercises` are preserved so existing consumers keep working.
     */
    private function enrichSuggestedWorkouts(Collection $workouts): Collection
    {
        return $workouts->map(function ($workout) {
            $exercises = $workout->exercises ?? collect();
            $payload = $workout->toArray();

            // Duration estimate: sum over exercises of sets * (reps * 4s + rest_sec) seconds.
            $totalSec = 0;
            $hasAny = false;
            foreach ($exercises as $pwe) {
                $sets = (int) ($pwe->rec_sets ?? 0);
                $reps = (int) ($pwe->rec_reps ?? 0);
                $rest = (int) ($pwe->rest_sec ?? 0);
                if ($sets > 0 && ($reps > 0 || $rest > 0)) {
                    $hasAny = true;
                    $totalSec += $sets * (($reps * 4) + $rest);
                }
            }
            $payload['duration_min'] = $hasAny ? (int) round($totalSec / 60) : null;

            // Mode helpers — null when no exercises with the value.
            $difficulties = $exercises->map(fn ($pwe) => $pwe->exercise?->difficulty)->filter()->values();
            $muscles = $exercises->map(fn ($pwe) => $pwe->exercise?->muscle_group)->filter()->values();
            $payload['level'] = $this->mode($difficulties);
            $payload['category'] = $this->mode($muscles);

            // First non-null thumbnail across exercises (column is `thumbnail_url`).
            $image = null;
            foreach ($exercises as $pwe) {
                $candidate = $pwe->exercise?->thumbnail_url;
                if (! empty($candidate)) {
                    $image = $candidate;
                    break;
                }
            }
            $payload['image_url'] = $image;

            return $payload;
        })->values();
    }

    /**
     * Today's workout sessions for the user's local day. The `workout_logs`
     * table has no top-level completion column — completion is per exercise on
     * `workout_exercise_logs.is_completed`. We expose `done = true` when the
     * log has at least one completed exercise, or when there are no exercise
     * rows but the workout log itself exists (manual entry).
     */
    private function todayWorkouts(User $user, Carbon $today): Collection
    {
        $tz = $user->timezone ?: 'UTC';
        $localToday = Carbon::now($tz)->toDateString();

        $logs = WorkoutLog::where('user_id', $user->id)
            ->whereDate('date', $localToday)
            ->with(['planWorkout:id,workout_name', 'workoutLogExercises:id,workout_log_id,is_completed'])
            ->orderBy('created_at')
            ->get();

        return $logs->map(function ($log) {
            $name = $log->planWorkout?->workout_name ?: 'Treino';
            $duration = $log->duration_min !== null ? (int) $log->duration_min : null;

            $exercises = $log->workoutLogExercises;
            if ($exercises && $exercises->isNotEmpty()) {
                $done = $exercises->contains(fn ($e) => (bool) $e->is_completed);
            } else {
                // No granular exercise rows: treat the log itself as the source of truth.
                $done = true;
            }

            return [
                'id' => $log->id,
                'name' => $name,
                'duration_min' => $duration,
                'done' => $done,
            ];
        })->values();
    }

    /** Returns the most frequent value in the collection, or null when empty. */
    private function mode(Collection $values): ?string
    {
        if ($values->isEmpty()) {
            return null;
        }
        $counts = $values->countBy();
        $top = $counts->sortDesc()->keys()->first();

        return $top !== null ? (string) $top : null;
    }

    private function caloriesPair(Collection $meals, ?UserGoal $goal): array
    {
        return [
            'consumed' => round((float) $meals->sum('calories_consumed')),
            'goal' => $goal?->goal_calories_day,
        ];
    }

    private function macroPair(Collection $meals, ?UserGoal $goal, string $field, string $goalField): array
    {
        return [
            'consumed' => round((float) $meals->sum($field), 1),
            'goal' => $goal?->{$goalField},
        ];
    }

    private function macroBreakdown(Collection $meals, ?UserGoal $goal): array
    {
        return [
            'protein' => $this->macroPair($meals, $goal, 'protein_g', 'goal_protein_g'),
            'carbs' => $this->macroPair($meals, $goal, 'carbs_g', 'goal_carbs_g'),
            'fat' => $this->macroPair($meals, $goal, 'fat_g', 'goal_fat_g'),
        ];
    }

    private function macroTotals(Collection $meals): array
    {
        return [
            'protein' => round((float) $meals->sum('protein_g'), 1),
            'carbs' => round((float) $meals->sum('carbs_g'), 1),
            'fat' => round((float) $meals->sum('fat_g'), 1),
        ];
    }

    private function hydrationPair(float $consumed, ?UserGoal $goal): array
    {
        return [
            'consumed' => round($consumed, 2),
            'goal' => (float) ($goal?->goal_water_liters ?? self::DEFAULT_WATER_GOAL_LITERS),
        ];
    }

    private function alimentationGoals(?UserGoal $goal): array
    {
        return [
            'calories' => $goal?->goal_calories_day,
            'protein_g' => $goal?->goal_protein_g,
            'carbs_g' => $goal?->goal_carbs_g,
            'fat_g' => $goal?->goal_fat_g,
            'water_liters' => (float) ($goal?->goal_water_liters ?? self::DEFAULT_WATER_GOAL_LITERS),
        ];
    }

    private function alimentationConsumed(Collection $meals): array
    {
        return [
            'calories' => round((float) $meals->sum('calories_consumed')),
            'protein_g' => round((float) $meals->sum('protein_g'), 1),
            'carbs_g' => round((float) $meals->sum('carbs_g'), 1),
            'fat_g' => round((float) $meals->sum('fat_g'), 1),
        ];
    }

    private function groupMealsByType(Collection $meals): Collection
    {
        return $meals->groupBy('meal_type')->map(fn ($group, $type) => [
            'type' => $type,
            'meals' => $group->values(),
            'calories' => round((float) $group->sum('calories_consumed')),
            'protein' => round((float) $group->sum('protein_g'), 1),
            'carbs' => round((float) $group->sum('carbs_g'), 1),
            'fat' => round((float) $group->sum('fat_g'), 1),
        ])->values();
    }

    private function weekBreakdown(Collection $weeklyWorkouts, Carbon $today): array
    {
        $weekStart = $today->copy()->startOfWeek();
        $doneDates = $weeklyWorkouts->pluck('date')->map(fn ($d) => $this->dateOnly($d))->unique();
        $weekDays = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $dayStr = $day->toDateString();
            $weekDays[] = [
                'date' => $dayStr,
                'label' => $day->format('D'),
                'done' => $doneDates->contains($dayStr),
                'future' => $day->isAfter($today),
            ];
        }

        return $weekDays;
    }

    private function workoutHistory(User $user, Carbon $today, int $days): Collection
    {
        return WorkoutLog::where('user_id', $user->id)
            ->whereDate('date', '>=', $today->copy()->subDays($days)->toDateString())
            ->orderByDesc('date')
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'date' => $w->date,
                'duration_min' => $w->duration_min,
                'calories_burned' => $w->calories_burned,
                'muscles_trained' => $w->muscles_trained,
                'mood' => $w->mood,
            ]);
    }

    /** Normalises any date-ish value (Carbon, string with time, plain date) to "Y-m-d". */
    private function dateOnly($value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return substr((string) $value, 0, 10);
    }

    private function formatGamification(?UserGamification $gam): ?array
    {
        if (! $gam) {
            return null;
        }

        return [
            'xp_total' => $gam->xp_total,
            'current_level' => $gam->current_level,
            'current_streak' => $gam->current_streak,
        ];
    }
}
