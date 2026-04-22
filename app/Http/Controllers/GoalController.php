<?php

namespace App\Http\Controllers;

use App\Models\UserGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class GoalController extends Controller
{
    /**
     * Get the user's active goal.
     */
    #[OA\Get(
        path: '/api/goals',
        summary: 'Obter metas do usuário',
        description: 'Retorna as metas ativas do usuário (calorias, macros, treinos, hidratação, peso).',
        tags: ['Goals'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Metas ativas do usuário'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
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
    #[OA\Put(
        path: '/api/goals/exercise',
        summary: 'Atualizar metas de exercício',
        description: 'Atualiza ou cria as metas de exercício (passos diários e treinos por semana).',
        tags: ['Goals'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'goal_steps_day', type: 'integer', example: 10000),
                    new OA\Property(property: 'goal_workouts_week', type: 'integer', example: 4),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Metas de exercício atualizadas'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
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
    #[OA\Put(
        path: '/api/goals/alimentation',
        summary: 'Atualizar metas de alimentação',
        description: 'Atualiza ou cria metas alimentares. Se informar diet_objective sem macros, calcula automaticamente com base nos dados de onboarding (BMR Mifflin-St Jeor + fator de atividade).',
        tags: ['Goals'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'diet_objective', type: 'string', enum: ['weight_loss', 'maintenance', 'muscle_gain'], example: 'muscle_gain'),
                    new OA\Property(property: 'goal_calories_day', type: 'integer', example: 2200),
                    new OA\Property(property: 'goal_protein_g', type: 'number', example: 150),
                    new OA\Property(property: 'goal_carbs_g', type: 'number', example: 260),
                    new OA\Property(property: 'goal_fat_g', type: 'number', example: 60),
                    new OA\Property(property: 'goal_water_liters', type: 'number', example: 2.5),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Metas alimentares atualizadas'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
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

        if ($this->needsAutoCalculation($data)) {
            $onboarding = $request->user()->onboarding;
            if ($onboarding) {
                $data = $this->mergeCalculatedMacros($data, $onboarding);
            }
        }

        unset($data['diet_objective']);

        $goal = UserGoal::updateOrCreate(
            ['user_id' => $request->user()->id, 'is_active' => true],
            $data
        );

        return response()->json([
            'message' => 'Alimentation goals updated successfully.',
            'goal' => $goal,
        ]);
    }

    private function needsAutoCalculation(array $data): bool
    {
        if (empty($data['diet_objective'])) {
            return false;
        }

        foreach (['goal_calories_day', 'goal_protein_g', 'goal_carbs_g', 'goal_fat_g'] as $field) {
            if (empty($data[$field])) {
                return true;
            }
        }

        return false;
    }

    private function mergeCalculatedMacros(array $data, $onboarding): array
    {
        $bmr  = $this->calculateBmr($onboarding);
        $tdee = $bmr * $this->activityMultiplier($onboarding->work_style ?? 'light');
        $split = $this->macroSplit($data['diet_objective'], $tdee);

        $data['goal_calories_day'] ??= round($split['calories']);
        $data['goal_protein_g']    ??= round($split['protein'], 1);
        $data['goal_carbs_g']      ??= round($split['carbs'], 1);
        $data['goal_fat_g']        ??= round($split['fat'], 1);

        return $data;
    }

    private function calculateBmr($onboarding): float
    {
        $weight = $onboarding->weight_kg;
        $height = $onboarding->height_cm;
        $age    = $onboarding->age;
        $gender = strtolower($onboarding->gender ?? 'masculino');

        $base = (10 * $weight) + (6.25 * $height) - (5 * $age);
        $isMale = in_array($gender, ['male', 'm', 'masculino', 'homem'], true);

        return $isMale ? $base + 5 : $base - 161;
    }

    private function activityMultiplier(string $workStyle): float
    {
        $style = strtolower($workStyle);

        if (in_array($style, ['moderate', 'moderado', 'moderada'], true)) {
            return 1.375;
        }
        if (in_array($style, ['active', 'ativo', 'intenso', 'intensa'], true)) {
            return 1.725;
        }

        return 1.2;
    }

    private function macroSplit(string $objective, float $tdee): array
    {
        $splits = [
            'weight_loss' => ['delta' => -random_int(300, 500), 'pct' => [0.35, 0.40, 0.25]],
            'muscle_gain' => ['delta' =>  random_int(200, 400), 'pct' => [0.30, 0.50, 0.20]],
            'maintenance' => ['delta' => 0,                      'pct' => [0.20, 0.50, 0.30]],
        ];

        $config = $splits[$objective] ?? $splits['maintenance'];
        $calories = $tdee + $config['delta'];
        [$protPct, $carbPct, $fatPct] = $config['pct'];

        return [
            'calories' => $calories,
            'protein'  => ($calories * $protPct) / 4,
            'carbs'    => ($calories * $carbPct) / 4,
            'fat'      => ($calories * $fatPct) / 9,
        ];
    }
}
