<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationPreferenceController extends Controller
{
    #[OA\Get(
        path: '/api/v1/notification-preferences',
        summary: 'Consultar preferências de notificação',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Preferências de notificação')]
    )]
    public function show(Request $request): JsonResponse
    {
        $prefs = NotificationPreference::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        return response()->json(['preferences' => $prefs]);
    }

    #[OA\Put(
        path: '/api/v1/notification-preferences',
        summary: 'Atualizar preferências de notificação',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'streak_at_risk', type: 'boolean'),
                    new OA\Property(property: 'rank_drop', type: 'boolean'),
                    new OA\Property(property: 'achievement_close', type: 'boolean'),
                    new OA\Property(property: 'achievement_unlocked', type: 'boolean'),
                    new OA\Property(property: 'daily_summary', type: 'boolean'),
                    new OA\Property(property: 'quiet_hours_start', type: 'string', example: '23:00'),
                    new OA\Property(property: 'quiet_hours_end', type: 'string', example: '07:00'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Preferências atualizadas')]
    )]
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'streak_at_risk'       => 'nullable|boolean',
            'rank_drop'            => 'nullable|boolean',
            'achievement_close'    => 'nullable|boolean',
            'achievement_unlocked' => 'nullable|boolean',
            'daily_summary'        => 'nullable|boolean',
            'quiet_hours_start'    => 'nullable|date_format:H:i',
            'quiet_hours_end'      => 'nullable|date_format:H:i',
        ]);

        $prefs = NotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return response()->json(['preferences' => $prefs]);
    }
}
