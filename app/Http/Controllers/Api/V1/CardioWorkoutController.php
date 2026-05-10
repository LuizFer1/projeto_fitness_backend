<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\UseCases\Workout\RegisterCardioSessionUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workouts\StoreCardioWorkoutRequest;
use App\Http\Resources\WorkoutLogResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class CardioWorkoutController extends Controller
{
    public function __construct(private RegisterCardioSessionUseCase $useCase) {}

    #[OA\Post(
        path: '/api/v1/workouts/cardio',
        summary: 'Registrar sessão de cárdio',
        description: 'Aceita dados manuais ou importados de HealthKit, Google Fit ou Garmin. Idempotente via Idempotency-Key.',
        tags: ['Workouts'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                    new OA\Property(property: 'duration_min', type: 'integer'),
                    new OA\Property(property: 'calories_burned', type: 'number'),
                    new OA\Property(property: 'distance_m', type: 'integer'),
                    new OA\Property(property: 'pace_seconds_per_km', type: 'integer'),
                    new OA\Property(property: 'avg_hr', type: 'integer'),
                    new OA\Property(property: 'max_hr', type: 'integer'),
                    new OA\Property(property: 'route_polyline', type: 'string', nullable: true),
                    new OA\Property(property: 'external_source', type: 'string', enum: ['manual', 'healthkit', 'googlefit', 'garmin']),
                    new OA\Property(property: 'external_id', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Sessão de cárdio registrada'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(StoreCardioWorkoutRequest $request): JsonResponse
    {
        $log = $this->useCase->execute($request->user(), $request->validated());

        return response()->json([
            'message' => 'Treino de cárdio registrado com sucesso.',
            'workout_log' => new WorkoutLogResource($log),
        ], 201);
    }
}
