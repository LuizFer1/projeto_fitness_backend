<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DeviceController extends Controller
{
    #[OA\Post(
        path: '/api/v1/devices',
        summary: 'Registrar dispositivo para push notifications',
        tags: ['Devices'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['platform', 'push_token'],
                properties: [
                    new OA\Property(property: 'platform', type: 'string', enum: ['ios', 'android', 'web']),
                    new OA\Property(property: 'push_token', type: 'string'),
                    new OA\Property(property: 'app_version', type: 'string', nullable: true),
                    new OA\Property(property: 'device_model', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Dispositivo registrado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|in:ios,android,web',
            'push_token' => 'required|string|max:500',
            'app_version' => 'nullable|string|max:20',
            'device_model' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $device = UserDevice::updateOrCreate(
            ['user_id' => $user->id, 'push_token' => $validated['push_token']],
            array_merge($validated, ['last_seen_at' => now(), 'revoked_at' => null])
        );

        return response()->json([
            'message' => 'Dispositivo registrado.',
            'device_id' => $device->id,
        ], 201);
    }

    #[OA\Delete(
        path: '/api/v1/devices/{uuid}',
        summary: 'Revogar dispositivo (push token)',
        tags: ['Devices'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Dispositivo revogado'),
            new OA\Response(response: 404, description: 'Dispositivo não encontrado'),
        ]
    )]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $device = UserDevice::where('user_id', $user->id)->findOrFail($uuid);

        $device->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Dispositivo revogado.']);
    }
}
