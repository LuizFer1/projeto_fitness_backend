<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserPrivacySetting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PrivacySettingsController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    #[OA\Get(
        path: '/api/v1/privacy-settings',
        summary: 'Consultar configurações de privacidade por métrica',
        tags: ['Privacy'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Configurações de privacidade'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = UserPrivacySetting::forUser($user);

        return response()->json(['data' => $this->format($settings)]);
    }

    #[OA\Put(
        path: '/api/v1/privacy-settings',
        summary: 'Atualizar configurações de privacidade por métrica',
        tags: ['Privacy'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'share_weight', type: 'boolean'),
                    new OA\Property(property: 'share_macros', type: 'boolean'),
                    new OA\Property(property: 'share_one_rm', type: 'boolean'),
                    new OA\Property(property: 'share_streak', type: 'boolean'),
                    new OA\Property(property: 'share_achievements', type: 'boolean'),
                    new OA\Property(property: 'share_workouts', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Configurações atualizadas'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'share_weight' => ['sometimes', 'boolean'],
            'share_macros' => ['sometimes', 'boolean'],
            'share_one_rm' => ['sometimes', 'boolean'],
            'share_streak' => ['sometimes', 'boolean'],
            'share_achievements' => ['sometimes', 'boolean'],
            'share_workouts' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $settings = UserPrivacySetting::forUser($user);
        $settings->update($data);

        $this->audit->log('privacy_settings_updated', $user->id, $user->id, 'User', $data);

        return response()->json(['data' => $this->format($settings->fresh())]);
    }

    private function format(UserPrivacySetting $s): array
    {
        return [
            'share_weight' => $s->share_weight,
            'share_macros' => $s->share_macros,
            'share_one_rm' => $s->share_one_rm,
            'share_streak' => $s->share_streak,
            'share_achievements' => $s->share_achievements,
            'share_workouts' => $s->share_workouts,
        ];
    }
}
