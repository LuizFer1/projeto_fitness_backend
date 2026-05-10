<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    #[OA\Get(
        path: '/api/dashboard',
        summary: 'Dashboard consolidado do usuário',
        description: 'Retorna dados consolidados do dia: metas, refeições enriquecidas com nome/imagem, treinos sugeridos com duração/nível/categoria/imagem, treinos do dia, hidratação, gráfico nutricional dos últimos 7 dias e gamificação.',
        tags: ['Dashboard'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dados consolidados do dashboard',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'today', type: 'string', format: 'date', example: '2026-05-09'),
                        new OA\Property(property: 'dailyCalories', type: 'object', properties: [
                            new OA\Property(property: 'consumed', type: 'number'),
                            new OA\Property(property: 'goal', type: 'number', nullable: true),
                        ]),
                        new OA\Property(property: 'protein', type: 'object'),
                        new OA\Property(property: 'macros', type: 'object'),
                        new OA\Property(property: 'currentWeight', type: 'number', nullable: true),
                        new OA\Property(
                            property: 'meals',
                            type: 'array',
                            description: 'MealLog rows enriched with rich `name` (from related Meal or PT-BR fallback by `meal_type`) and `image_url`.',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string'),
                                    new OA\Property(property: 'meal_type', type: 'string'),
                                    new OA\Property(property: 'calories_consumed', type: 'number'),
                                    new OA\Property(property: 'name', type: 'string', example: 'Almoço'),
                                    new OA\Property(property: 'image_url', type: 'string', nullable: true),
                                ]
                            )
                        ),
                        new OA\Property(property: 'trainingDays', type: 'array', items: new OA\Items(type: 'string', format: 'date')),
                        new OA\Property(property: 'weeklyWorkouts', type: 'object', properties: [
                            new OA\Property(property: 'done', type: 'integer'),
                            new OA\Property(property: 'goal', type: 'integer', nullable: true),
                        ]),
                        new OA\Property(
                            property: 'suggestedWorkouts',
                            type: 'array',
                            description: 'PlanWorkout rows enriched with derived `duration_min`, `level`, `category` and `image_url`.',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string'),
                                    new OA\Property(property: 'workout_name', type: 'string', nullable: true),
                                    new OA\Property(property: 'exercises', type: 'array', items: new OA\Items(type: 'object')),
                                    new OA\Property(property: 'duration_min', type: 'integer', nullable: true),
                                    new OA\Property(property: 'level', type: 'string', nullable: true, example: 'intermediate'),
                                    new OA\Property(property: 'category', type: 'string', nullable: true, example: 'chest'),
                                    new OA\Property(property: 'image_url', type: 'string', nullable: true),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'todayWorkouts',
                            type: 'array',
                            description: 'WorkoutLog rows for the user\'s local day, simplified for the home card.',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string'),
                                    new OA\Property(property: 'name', type: 'string', example: 'Treino A - Peito e Tríceps'),
                                    new OA\Property(property: 'duration_min', type: 'integer', nullable: true),
                                    new OA\Property(property: 'done', type: 'boolean'),
                                ]
                            )
                        ),
                        new OA\Property(property: 'hydration', type: 'object'),
                        new OA\Property(property: 'nutritionChart', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'gamification', type: 'object', nullable: true),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->buildHome($request->user()));
    }

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
        return response()->json($this->dashboard->buildAlimentation($request->user()));
    }

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
        return response()->json($this->dashboard->buildExercise($request->user()));
    }
}
