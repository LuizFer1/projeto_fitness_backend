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

        // Today's meals
        $mealsToday = MealLog::where('user_id', $user->id)
            ->where('date', $today->toDateString())
            ->orderBy('created_at')
            ->get();

        $caloriesConsumed = $mealsToday->sum('calories_consumed');
        $proteinConsumed  = $mealsToday->sum('protein_g');
        $carbsConsumed    = $mealsToday->sum('carbs_g');
        $fatConsumed      = $mealsToday->sum('fat_g');

        // Weekly workouts
        $weeklyWorkouts = WorkoutLog::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $today->toDateString()])
            ->get();

        // Training days this week (which days had workouts)
        $trainingDays = $weeklyWorkouts->pluck('date')->unique()->values();

        // Today's workouts
        $workoutsToday = $weeklyWorkouts->where('date', $today->toDateString());

        // Suggested workouts from active plan
        $activePlan = AiPlan::where('user_id', $user->id)
            ->where('type', 'workout')
            ->where('status', 'active')
            ->with('planWorkouts.exercises')
            ->first();

        $suggestedWorkouts = $activePlan?->planWorkouts ?? [];

        // Hydration (water logs)
        $waterToday = WaterLog::where('user_id', $user->id)
            ->where('date', $today->toDateString())
            ->sum('liters');

        // Nutrition chart (last 7 days)
        $last7Days = MealLog::where('user_id', $user->id)
            ->where('date', '>=', $today->copy()->subDays(6)->toDateString())
            ->selectRaw('date, SUM(calories_consumed) as calories, SUM(protein_g) as protein, SUM(carbs_g) as carbs, SUM(fat_g) as fat')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'today' => $today->toDateString(),
            'dailyCalories' => [
                'consumed' => round($caloriesConsumed),
                'goal'     => $goal->goal_calories_day ?? null,
            ],
            'protein' => [
                'consumed' => round($proteinConsumed, 1),
                'goal'     => $goal->goal_protein_g ?? null,
            ],
            'macros' => [
                'protein' => ['consumed' => round($proteinConsumed, 1), 'goal' => $goal->goal_protein_g ?? null],
                'carbs'   => ['consumed' => round($carbsConsumed, 1), 'goal' => $goal->goal_carbs_g ?? null],
                'fat'     => ['consumed' => round($fatConsumed, 1), 'goal' => $goal->goal_fat_g ?? null],
            ],
            'currentWeight' => $onboarding->weight_kg ?? null,
            'meals'         => $mealsToday,
            'trainingDays'  => $trainingDays,
            'weeklyWorkouts' => [
                'done' => $trainingDays->count(),
                'goal' => $goal->goal_workouts_week ?? null,
            ],
            'suggestedWorkouts' => $suggestedWorkouts,
            'hydration' => [
                'consumed' => round((float) $waterToday, 2),
                'goal'     => $goal->goal_water_liters ?? 2.0,
            ],
            'nutritionChart' => $last7Days,
            'gamification'   => $gam ? [
                'xp_total'       => $gam->xp_total,
                'current_level'  => $gam->current_level,
                'current_streak' => $gam->current_streak,
            ] : null,
        ]);
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

        $goal = UserGoal::where('user_id', $user->id)->where('is_active', true)->first();

        $meals = MealLog::where('user_id', $user->id)
            ->where('date', $today)
            ->orderBy('created_at')
            ->get();

        // Water consumed today
        $waterConsumed = WaterLog::where('user_id', $user->id)
            ->where('date', $today)
            ->sum('liters');

        // Group meals by type
        $mealGroups = $meals->groupBy('meal_type')->map(function ($group, $type) {
            return [
                'type'     => $type,
                'meals'    => $group->values(),
                'calories' => round($group->sum('calories_consumed')),
                'protein'  => round($group->sum('protein_g'), 1),
                'carbs'    => round($group->sum('carbs_g'), 1),
                'fat'      => round($group->sum('fat_g'), 1),
            ];
        })->values();

        return response()->json([
            'date' => $today,
            'dailyGoal' => [
                'calories'     => $goal->goal_calories_day ?? null,
                'protein_g'    => $goal->goal_protein_g ?? null,
                'carbs_g'      => $goal->goal_carbs_g ?? null,
                'fat_g'        => $goal->goal_fat_g ?? null,
                'water_liters' => $goal->goal_water_liters ?? 2.0,
            ],
            'consumed' => [
                'calories'  => round($meals->sum('calories_consumed')),
                'protein_g' => round($meals->sum('protein_g'), 1),
                'carbs_g'   => round($meals->sum('carbs_g'), 1),
                'fat_g'     => round($meals->sum('fat_g'), 1),
            ],
            'macros' => [
                'protein' => round($meals->sum('protein_g'), 1),
                'carbs'   => round($meals->sum('carbs_g'), 1),
                'fat'     => round($meals->sum('fat_g'), 1),
            ],
            'water' => [
                'consumed' => round((float) $waterConsumed, 2),
                'goal'     => $goal->goal_water_liters ?? 2.0,
            ],
            'mealGroups' => $mealGroups,
        ]);
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
