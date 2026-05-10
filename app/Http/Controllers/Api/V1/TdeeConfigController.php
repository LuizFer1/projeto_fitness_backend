<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\UseCases\Onboarding\CalculateDailyCaloriesUseCase;
use App\Http\Controllers\Controller;
use App\Models\UserOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TdeeConfigController extends Controller
{
    public function __construct(private CalculateDailyCaloriesUseCase $calculator) {}

    #[OA\Put(
        path: '/api/v1/onboarding/tdee-config',
        summary: 'Atualizar fórmula e fator de atividade do TDEE',
        description: 'Permite escolher entre Mifflin-St Jeor e Harris-Benedict, e/ou definir fator de atividade manual (1.2–1.9). Recalcula BMR e TDEE em seguida.',
        tags: ['Onboarding'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'formula', type: 'string', enum: ['mifflin', 'harris'], example: 'harris'),
                    new OA\Property(property: 'activity_factor', type: 'number', nullable: true, minimum: 1.2, maximum: 1.9, example: 1.55),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'TDEE recalculado'),
            new OA\Response(response: 404, description: 'Onboarding não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'formula' => ['sometimes', 'in:mifflin,harris'],
            'activity_factor' => ['sometimes', 'nullable', 'numeric', 'min:1.2', 'max:1.9'],
        ]);

        $user = $request->user();

        $onboarding = UserOnboarding::where('user_id', $user->id)->first();

        if (! $onboarding) {
            return response()->json(['message' => 'Onboarding não encontrado. Complete o onboarding primeiro.'], 404);
        }

        $updates = [];
        if (array_key_exists('formula', $data)) {
            $updates['tdee_formula'] = $data['formula'];
        }
        if (array_key_exists('activity_factor', $data)) {
            $updates['activity_factor'] = $data['activity_factor'];
        }

        if ($updates) {
            $onboarding->update($updates);
        }

        // Recalculate with updated values
        $input = array_merge($onboarding->toArray(), [
            'tdee_formula' => $onboarding->tdee_formula ?? 'mifflin',
            'activity_factor' => $onboarding->activity_factor,
        ]);

        $result = $this->calculator->execute($input);

        // Persist updated BMR
        if ($result['bmr'] !== null) {
            $onboarding->update(['bmr' => $result['bmr']]);
        }

        return response()->json([
            'formula' => $result['formula'],
            'activity_factor' => $result['activity_factor'],
            'bmr' => $result['bmr'],
            'tdee' => $result['tdee'],
        ]);
    }
}
