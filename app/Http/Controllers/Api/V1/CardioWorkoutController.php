<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\UseCases\Workout\RegisterCardioSessionUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'                => 'nullable|date',
            'duration_min'        => 'nullable|integer|min:1',
            'calories_burned'     => 'nullable|numeric|min:0',
            'distance_m'          => 'nullable|integer|min:0',
            'pace_seconds_per_km' => 'nullable|integer|min:0',
            'avg_hr'              => 'nullable|integer|min:30|max:300',
            'max_hr'              => 'nullable|integer|min:30|max:300',
            'elevation_gain_m'    => 'nullable|integer',
            'route_polyline'      => 'nullable|string',
            'mood'                => 'nullable|in:great,good,neutral,tired,bad',
            'observations'        => 'nullable|string|max:1000',
            'external_source'     => 'nullable|in:manual,healthkit,googlefit,garmin',
            'external_id'         => 'nullable|string|max:120',
        ]);

        $log = $this->useCase->execute($request->user(), $validated);

        return response()->json([
            'message'     => 'Treino de cárdio registrado com sucesso.',
            'workout_log' => $log,
        ], 201);
    }
}
