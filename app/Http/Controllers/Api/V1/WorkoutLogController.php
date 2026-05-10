<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workouts\FinishWorkoutRequest;
use App\Http\Resources\WorkoutLogResource;
use App\Services\Workout\WorkoutFinishService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class WorkoutLogController extends Controller
{
    public function __construct(private WorkoutFinishService $finishService) {}

    #[OA\Post(
        path: '/api/v1/workouts/finish',
        summary: 'Salva e analisa um treino finalizado',
        description: 'Salva o log do treino, os exercícios realizados, e envia os dados para a IA calcular calorias gastas, músculos treinados e gerar um feedback motivacional.',
        tags: ['Workouts'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-03-15'),
                    new OA\Property(property: 'time_start', type: 'string', format: 'time', example: '14:00:00'),
                    new OA\Property(property: 'time_end', type: 'string', format: 'time', example: '15:30:00'),
                    new OA\Property(property: 'plan_workout_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'observations', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'exercises',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'exercise_id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'sets', type: 'integer', example: 3),
                                new OA\Property(property: 'reps', type: 'integer', example: 12),
                                new OA\Property(property: 'weight_kg', type: 'number', example: 20.5),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Treino salvo e analisado com sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação ou erro na integração com a IA'),
        ]
    )]
    public function finish(FinishWorkoutRequest $request): JsonResponse
    {
        try {
            $log = $this->finishService->finish($request->user(), $request->validated());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Falha ao processar treino com IA: '.$e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Treino finalizado com sucesso!',
            'log' => (new WorkoutLogResource($log->load('workoutLogExercises')))->resolve(),
        ], 201);
    }
}
