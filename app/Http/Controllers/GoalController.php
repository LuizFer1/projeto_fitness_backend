<?php

namespace App\Http\Controllers;

use App\Models\UserGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    /**
     * Get the user's active goal.
     */
    public function index(Request $request): JsonResponse
    {
        $goal = $request->user()->goal;

        if (!$goal) {
            return response()->json([
                'main_goal' => null,
                'goal_calories_day' => null,
                'goal_protein_g' => null,
                'goal_carbs_g' => null,
                'goal_fat_g' => null,
                'goal_workouts_week' => null,
                'goal_water_liters' => null,
                'goal_steps_day' => null,
                'goal_weight_kg' => null,
            ]);
        }

        return response()->json($goal);
    }

    /**
     * Update exercise-related goals.
     */
    public function updateExercise(Request $request): JsonResponse
    {
        $data = $request->validate([
            'goal_steps_day' => 'nullable|integer|min:0',
            'goal_workouts_week' => 'nullable|integer|min:0|max:14',
        ]);

        $goal = UserGoal::updateOrCreate(
            ['user_id' => $request->user()->id, 'is_active' => true],
            $data
        );

        return response()->json([
            'message' => 'Exercise goals updated successfully.',
            'goal' => $goal
        ]);
    }

    /**
     * Update alimentation-related goals.
     */
    public function updateAlimentation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'diet_objective' => 'nullable|string|in:weight_loss,maintenance,muscle_gain',
            'goal_calories_day' => 'nullable|integer|min:0',
            'goal_protein_g' => 'nullable|numeric|min:0',
            'goal_carbs_g' => 'nullable|numeric|min:0',
            'goal_fat_g' => 'nullable|numeric|min:0',
            'goal_water_liters' => 'nullable|numeric|min:0',
        ]);

        $goalCalories = $data['goal_calories_day'] ?? null;
        $goalProtein = $data['goal_protein_g'] ?? null;
        $goalCarbs = $data['goal_carbs_g'] ?? null;
        $goalFat = $data['goal_fat_g'] ?? null;

        if (isset($data['diet_objective']) && (!$goalCalories || !$goalProtein || !$goalCarbs || !$goalFat)) {
            $user = $request->user();
            $onboarding = $user->onboarding;

            if ($onboarding) {
                $weight = $onboarding->weight_kg;
                $height = $onboarding->height_cm;
                $age = $onboarding->age;
                $gender = strtolower($onboarding->gender ?? 'masculino');

                // 1. Cálculo da Manutenção BMR (Equação Mifflin-St Jeor)
                if (in_array($gender, ['male', 'm', 'masculino', 'homem'])) {
                    $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
                } else {
                    $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
                }

                // 2. Multiplicador de Atividade Física (Fator Atividade)
                $workStyle = strtolower($onboarding->work_style ?? 'light');
                $activityMultiplier = 1.2; // Sedentário / "light"
                if (in_array($workStyle, ['moderate', 'moderado', 'moderada'])) {
                    $activityMultiplier = 1.375;
                } elseif (in_array($workStyle, ['active', 'ativo', 'intenso', 'intensa'])) {
                    $activityMultiplier = 1.725;
                }

                $tdee = $bmr * $activityMultiplier;

                // 3. Tratativas de acordo com as regras
                switch ($data['diet_objective']) {
                    case 'weight_loss':
                        $calorias_alvo = $tdee - random_int(300, 500);
                        $macro_prot = ($calorias_alvo * 0.35) / 4; // 35% de proteína
                        $macro_carb = ($calorias_alvo * 0.40) / 4; // 40% carboidratos
                        $macro_fat  = ($calorias_alvo * 0.25) / 9; // 25% gordura
                        break;
                    case 'muscle_gain':
                        $calorias_alvo = $tdee + random_int(200, 400);
                        $macro_prot = ($calorias_alvo * 0.30) / 4; // 30%
                        $macro_carb = ($calorias_alvo * 0.50) / 4; // 50%
                        $macro_fat  = ($calorias_alvo * 0.20) / 9; // 20%
                        break;
                    case 'maintenance':
                    default:
                        $calorias_alvo = $tdee;
                        $macro_prot = ($calorias_alvo * 0.20) / 4; // 20%
                        $macro_carb = ($calorias_alvo * 0.50) / 4; // 50%
                        $macro_fat  = ($calorias_alvo * 0.30) / 9; // 30%
                        break;
                }

                $data['goal_calories_day'] = $goalCalories ?? round($calorias_alvo);
                $data['goal_protein_g'] = $goalProtein ?? round($macro_prot, 1);
                $data['goal_carbs_g'] = $goalCarbs ?? round($macro_carb, 1);
                $data['goal_fat_g'] = $goalFat ?? round($macro_fat, 1);
            }
        }

        unset($data['diet_objective']);

        $goal = UserGoal::updateOrCreate(
            ['user_id' => $request->user()->id, 'is_active' => true],
            $data
        );

        return response()->json([
            'message' => 'Alimentation goals updated successfully.',
            'goal' => $goal
        ]);
    }
}
