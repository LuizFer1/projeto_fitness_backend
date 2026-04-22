<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiPlan;
use App\Models\MealLog;
use App\Models\UserGoal;
use App\Models\UserGamification;
use App\Models\UserOnboarding;
use App\Models\WorkoutLog;
use App\Models\WaterLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard
     * Frontend expects: nutritionChart, trainingDays, suggestedWorkouts, meals,
     * macros, currentWeight, dailyCalories, protein, weeklyWorkouts, hydration
     */
    #[OA\Get(
        path: '/api/dashboard',
        summary: 'Dashboard consolidado do usuário',
        description: 'Retorna dados consolidados do dia: metas, refeições, treinos da semana, hidratação, gráfico nutricional dos últimos 7 dias e gamificação.',
        tags: ['Dashboard'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dados consolidados do dashboard'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek();

        $goal = UserGoal::where('user_id', $user->id)->where('is_active', true)->first();
        $gam  = UserGamification::where('user_id', $user->id)->first();
        $onboarding = UserOnboarding::where('user_id', $user->id)->first();

        $mealsToday = $this->mealsOn($user->id, $today->toDateString());
        $weeklyWorkouts = WorkoutLog::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $today->toDateString()])
            ->get();
        $trainingDays = $weeklyWorkouts->pluck('date')->unique()->values();

        $waterToday = (float) WaterLog::where('user_id', $user->id)
            ->where('date', $today->toDateString())
            ->sum('liters');

        $last7Days = MealLog::where('user_id', $user->id)
            ->where('date', '>=', $today->copy()->subDays(6)->toDateString())
            ->selectRaw('date, SUM(calories_consumed) as calories, SUM(protein_g) as protein, SUM(carbs_g) as carbs, SUM(fat_g) as fat')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'today'           => $today->toDateString(),
            'dailyCalories'   => ['consumed' => round($mealsToday->sum('calories_consumed')), 'goal' => $goal->goal_calories_day ?? null],
            'protein'         => ['consumed' => round($mealsToday->sum('protein_g'), 1), 'goal' => $goal->goal_protein_g ?? null],
            'macros'          => $this->macrosWithGoals($mealsToday, $goal),
            'currentWeight'   => $onboarding->weight_kg ?? null,
            'meals'           => $mealsToday,
            'trainingDays'    => $trainingDays,
            'weeklyWorkouts'  => ['done' => $trainingDays->count(), 'goal' => $goal->goal_workouts_week ?? null],
            'suggestedWorkouts' => $this->activeWorkoutSuggestions($user->id),
            'hydration'       => ['consumed' => round($waterToday, 2), 'goal' => $goal->goal_water_liters ?? 2.0],
            'nutritionChart'  => $last7Days,
            'gamification'    => $this->formatGamification($gam),
        ]);
    }

    private function mealsOn($userId, string $date)
    {
        return MealLog::where('user_id', $userId)
            ->where('date', $date)
            ->orderBy('created_at')
            ->get();
    }

    private function macrosWithGoals($meals, $goal): array
    {
        return [
            'protein' => ['consumed' => round($meals->sum('protein_g'), 1), 'goal' => $goal->goal_protein_g ?? null],
            'carbs'   => ['consumed' => round($meals->sum('carbs_g'), 1),   'goal' => $goal->goal_carbs_g ?? null],
            'fat'     => ['consumed' => round($meals->sum('fat_g'), 1),     'goal' => $goal->goal_fat_g ?? null],
        ];
    }

    private function activeWorkoutSuggestions($userId)
    {
        $activePlan = AiPlan::where('user_id', $userId)
            ->where('type', 'workout')
            ->where('status', 'active')
            ->with('planWorkouts.exercises')
            ->first();

        return $activePlan?->planWorkouts ?? [];
    }

    private function formatGamification($gam): ?array
    {
        if (!$gam) {
            return null;
        }

        return [
            'xp_total'       => $gam->xp_total,
            'current_level'  => $gam->current_level,
            'current_streak' => $gam->current_streak,
        ];
    }

    /**
     * GET /api/dashboard/alimentation
     * Frontend expects: dailyGoal, consumed, water, macros, mealGroups
     */
    #[OA\Get(
        path: '/api/dashboard/alimentation',
        summary: 'Dashboard de alimentação',
        description: 'Retorna dados detalhados de alimentação do dia: metas, consumo, macros, hidratação e refeições agrupadas por tipo.',
        tags: ['Dashboard'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dados de alimentação do dia'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function alimentation(Request $request): JsonResponse
    {
        $user  = $request->user();
        $today = Carbon::today()->toDateString();

        $goal  = UserGoal::where('user_id', $user->id)->where('is_active', true)->first();
        $meals = $this->mealsOn($user->id, $today);

        $waterConsumed = (float) WaterLog::where('user_id', $user->id)
            ->where('date', $today)
            ->sum('liters');

        return response()->json([
            'date'       => $today,
            'dailyGoal'  => $this->alimentationGoal($goal),
            'consumed'   => $this->alimentationConsumed($meals),
            'macros'     => $this->macroTotals($meals),
            'water'      => ['consumed' => round($waterConsumed, 2), 'goal' => $goal->goal_water_liters ?? 2.0],
            'mealGroups' => $this->groupMealsByType($meals),
        ]);
    }

    private function alimentationGoal($goal): array
    {
        return [
            'calories'     => $goal->goal_calories_day ?? null,
            'protein_g'    => $goal->goal_protein_g ?? null,
            'carbs_g'      => $goal->goal_carbs_g ?? null,
            'fat_g'        => $goal->goal_fat_g ?? null,
            'water_liters' => $goal->goal_water_liters ?? 2.0,
        ];
    }

    private function alimentationConsumed($meals): array
    {
        return [
            'calories'  => round($meals->sum('calories_consumed')),
            'protein_g' => round($meals->sum('protein_g'), 1),
            'carbs_g'   => round($meals->sum('carbs_g'), 1),
            'fat_g'     => round($meals->sum('fat_g'), 1),
        ];
    }

    private function macroTotals($meals): array
    {
        return [
            'protein' => round($meals->sum('protein_g'), 1),
            'carbs'   => round($meals->sum('carbs_g'), 1),
            'fat'     => round($meals->sum('fat_g'), 1),
        ];
    }

    private function groupMealsByType($meals)
    {
        return $meals->groupBy('meal_type')->map(fn ($group, $type) => [
            'type'     => $type,
            'meals'    => $group->values(),
            'calories' => round($group->sum('calories_consumed')),
            'protein'  => round($group->sum('protein_g'), 1),
            'carbs'    => round($group->sum('carbs_g'), 1),
            'fat'      => round($group->sum('fat_g'), 1),
        ])->values();
    }

    /**
     * GET /api/dashboard/exercise
     * Frontend expects: weeklyGoal, weeklyDone, stats, todayWorkout, weekDays, history
     */
    #[OA\Get(
        path: '/api/dashboard/exercise',
        summary: 'Dashboard de exercícios',
        description: 'Retorna dados detalhados de treinos: meta semanal, treinos da semana, estatísticas, treino do dia e histórico dos últimos 30 dias.',
        tags: ['Dashboard'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dados de exercícios'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function exercise(Request $request): JsonResponse
    {
        $user  = $request->user();
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek();

        $goal = UserGoal::where('user_id', $user->id)->where('is_active', true)->first();

        // This week's workouts
        $weeklyWorkouts = WorkoutLog::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $today->toDateString()])
            ->with('workoutLogExercises')
            ->orderBy('date')
            ->get();

        // Today's workout
        $todayWorkout = $weeklyWorkouts->where('date', $today->toDateString())->values();

        // Week days breakdown (Mon-Sun with done/not done)
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $dayStr = $day->toDateString();
            $weekDays[] = [
                'date'   => $dayStr,
                'label'  => $day->format('D'),
                'done'   => $weeklyWorkouts->contains('date', $dayStr),
                'future' => $day->isAfter($today),
            ];
        }

        // History (last 30 days)
        $history = WorkoutLog::where('user_id', $user->id)
            ->where('date', '>=', $today->copy()->subDays(30)->toDateString())
            ->orderByDesc('date')
            ->get()
            ->map(fn ($w) => [
                'id'              => $w->id,
                'date'            => $w->date,
                'duration_min'    => $w->duration_min,
                'calories_burned' => $w->calories_burned,
                'muscles_trained' => $w->muscles_trained,
                'mood'            => $w->mood,
            ]);

        // Active plan
        $activePlan = AiPlan::where('user_id', $user->id)
            ->where('type', 'workout')
            ->where('status', 'active')
            ->with('planWorkouts.exercises')
            ->first();

        $weeklyDone = $weeklyWorkouts->pluck('date')->unique()->count();

        return response()->json([
            'date'        => $today->toDateString(),
            'weeklyGoal'  => $goal->goal_workouts_week ?? null,
            'weeklyDone'  => $weeklyDone,
            'stats' => [
                'calories_burned' => round($weeklyWorkouts->sum('calories_burned')),
                'total_duration'  => $weeklyWorkouts->sum('duration_min'),
                'total_workouts'  => $weeklyDone,
            ],
            'todayWorkout'  => $todayWorkout,
            'weekDays'      => $weekDays,
            'history'       => $history,
            'active_plan'   => $activePlan,
        ]);
    }
}
