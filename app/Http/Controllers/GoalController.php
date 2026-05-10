<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserGoalResource;
use App\Models\UserGoal;
use App\Services\Diet\MacroGoalCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class GoalController extends Controller
{
    public function __construct(private MacroGoalCalculator $macroCalculator) {}

    #[OA\Get(
        path: '/api/goals',
        summary: 'Obter metas do usuário',
        description: 'Retorna as metas ativas do usuário (calorias, macros, treinos, hidratação, peso). O shape é estável: quando ainda não existe registro, todos os campos vêm null com is_active=false.',
        tags: ['Goals'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Metas do usuário (shape estável vazio ou preenchido)'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $goal = $user->goal;

        if (! $goal) {
            return response()->json(UserGoalResource::emptyShape($user->id));
        }

        return response()->json((new UserGoalResource($goal))->resolve());
    }

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
            'goal_steps_day' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'goal_workouts_week' => ['nullable', 'integer', 'min:0', 'max:14'],
        ]);

        $goal = UserGoal::updateOrCreate(
            ['user_id' => $request->user()->id, 'is_active' => true],
            $data,
        );

        return response()->json([
            'message' => 'Exercise goals updated successfully.',
            'goal' => (new UserGoalResource($goal))->resolve(),
        ]);
    }

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
            'diet_objective' => ['nullable', 'string', 'in:weight_loss,maintenance,muscle_gain'],
            'goal_calories_day' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'goal_protein_g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'goal_carbs_g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'goal_fat_g' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'goal_water_liters' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        if ($this->needsAutoCalculation($data) && ($onboarding = $request->user()->onboarding)) {
            $split = $this->macroCalculator->calculateForObjective($onboarding, $data['diet_objective']);

            if ($split) {
                $data['goal_calories_day'] ??= $split['calories'];
                $data['goal_protein_g'] ??= $split['protein'];
                $data['goal_carbs_g'] ??= $split['carbs'];
                $data['goal_fat_g'] ??= $split['fat'];
            }
        }

        $goal = UserGoal::updateOrCreate(
            ['user_id' => $request->user()->id, 'is_active' => true],
            $data,
        );

        return response()->json([
            'message' => 'Alimentation goals updated successfully.',
            'goal' => (new UserGoalResource($goal))->resolve(),
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
}
