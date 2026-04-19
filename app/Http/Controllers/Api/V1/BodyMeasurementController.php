<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BodyMeasurement;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BodyMeasurementController extends Controller
{
    public function __construct(private GamificationService $gamification)
    {
    }

    #[OA\Get(
        path: '/api/v1/measurements',
        summary: 'Listar medidas corporais',
        description: 'Retorna o histórico de medidas corporais do usuário, ordenado por data (mais recentes primeiro).',
        tags: ['Measurements'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Quantidade de registros (máx 365)', schema: new OA\Schema(type: 'integer', default: 30)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de medidas corporais'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->query('limit', 30), 365);

        $rows = BodyMeasurement::where('user_id', $user->id)
            ->orderByDesc('date')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $rows]);
    }

    #[OA\Post(
        path: '/api/v1/measurements',
        summary: 'Registrar medida corporal',
        description: 'Cria ou atualiza a medida corporal do dia. Calcula o IMC automaticamente se a altura estiver no onboarding. Concede XP de gamificação. Endpoint idempotente via header Idempotency-Key.',
        tags: ['Measurements'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, description: 'Chave de idempotência (opcional)', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['weight_kg'],
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-04-18'),
                    new OA\Property(property: 'weight_kg', type: 'number', example: 78.5),
                    new OA\Property(property: 'body_fat_pct', type: 'number', example: 18.2),
                    new OA\Property(property: 'muscle_mass_kg', type: 'number', example: 35.4),
                    new OA\Property(property: 'waist_circumference_cm', type: 'number', example: 82),
                    new OA\Property(property: 'hip_circumference_cm', type: 'number', example: 95),
                    new OA\Property(property: 'arm_circumference_cm', type: 'number', example: 36),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Medida registrada'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'                   => 'nullable|date',
            'weight_kg'              => 'required|numeric|min:30|max:300',
            'body_fat_pct'           => 'nullable|numeric|min:3|max:60',
            'muscle_mass_kg'         => 'nullable|numeric|min:10|max:200',
            'waist_circumference_cm' => 'nullable|numeric|min:30|max:250',
            'hip_circumference_cm'   => 'nullable|numeric|min:30|max:250',
            'arm_circumference_cm'   => 'nullable|numeric|min:10|max:100',
        ]);

        $user = $request->user();
        $date = $data['date'] ?? now()->toDateString();

        $bmi = null;
        $heightCm = optional($user->onboarding)->height_cm;
        if ($heightCm) {
            $heightM = $heightCm / 100;
            $bmi = round($data['weight_kg'] / ($heightM * $heightM), 2);
        }

        $measurement = BodyMeasurement::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            array_merge($data, ['bmi' => $bmi, 'date' => $date])
        );

        $xp = $this->gamification->grantWeightLoggedXp($user);

        return response()->json([
            'measurement' => $measurement,
            'xp_gained'   => $xp?->xp_gained ?? 0,
        ], 201);
    }

    #[OA\Delete(
        path: '/api/v1/measurements/{id}',
        summary: 'Remover medida corporal',
        description: 'Remove um registro de medida corporal do usuário.',
        tags: ['Measurements'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID da medida', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Medida removida'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $measurement = BodyMeasurement::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $measurement->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
