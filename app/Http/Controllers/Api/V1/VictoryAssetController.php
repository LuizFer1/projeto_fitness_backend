<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateVictoryAssetJob;
use App\Models\VictoryAsset;
use App\Models\WorkoutLog;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class VictoryAssetController extends Controller
{
    public function __construct(private GamificationService $gamification) {}

    #[OA\Post(
        path: '/api/v1/workouts/{uuid}/victory-asset',
        summary: 'Enfileirar geração de victory asset',
        description: 'Gera assíncrona e persistentemente uma imagem de vitória para o treino.',
        tags: ['Victory Assets'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Asset enfileirado'),
            new OA\Response(response: 202, description: 'Asset já em processamento ou pronto'),
            new OA\Response(response: 404, description: 'Treino não encontrado'),
        ]
    )]
    public function enqueue(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $log  = WorkoutLog::where('id', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $existing = VictoryAsset::where('workout_log_id', $log->id)->latest()->first();

        if ($existing && in_array($existing->status, ['pending', 'processing', 'ready'])) {
            return response()->json([
                'status'  => $existing->status,
                'url'     => $existing->public_url,
                'message' => 'Asset já em processamento ou pronto.',
            ], 202);
        }

        $isCardio = in_array($log->modality, ['cardio']) || ($log->distance_m > 0);

        $asset = VictoryAsset::create([
            'workout_log_id' => $log->id,
            'user_id'        => $user->id,
            'type'           => $isCardio ? 'cardio' : 'strength',
            'status'         => 'pending',
        ]);

        GenerateVictoryAssetJob::dispatch($asset->id, $log->id);

        // Grant XP for sharing a victory asset (SRS: asset_shared event)
        $this->gamification->grantAssetSharedXp($user, $log->id);

        return response()->json([
            'id'     => $asset->id,
            'status' => 'pending',
        ], 201);
    }

    #[OA\Get(
        path: '/api/v1/workouts/{uuid}/victory-asset',
        summary: 'Consultar status do victory asset',
        tags: ['Victory Assets'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status e URL do asset'),
            new OA\Response(response: 404, description: 'Asset não gerado ou treino não encontrado'),
        ]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user  = $request->user();
        $log   = WorkoutLog::where('id', $uuid)->where('user_id', $user->id)->firstOrFail();
        $asset = VictoryAsset::where('workout_log_id', $log->id)->latest()->first();

        if (!$asset) {
            return response()->json(['message' => 'Nenhum asset gerado para este treino.'], 404);
        }

        return response()->json([
            'id'           => $asset->id,
            'type'         => $asset->type,
            'status'       => $asset->status,
            'url'          => $asset->public_url,
            'generated_at' => $asset->generated_at?->toIso8601String(),
        ]);
    }
}
