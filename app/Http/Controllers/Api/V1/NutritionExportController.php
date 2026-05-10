<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\NutritionExportJob;
use App\Models\NutritionExport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class NutritionExportController extends Controller
{
    #[OA\Get(
        path: '/api/v1/nutrition/export',
        summary: 'Exportar histórico nutricional',
        description: 'Retorna export pronto gerado hoje (se existir) ou enfileira novo e retorna 202.',
        tags: ['Nutrition'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d'])),
            new OA\Parameter(name: 'format', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'pdf'], default: 'csv')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Export pronto com URL de download'),
            new OA\Response(response: 202, description: 'Export enfileirado'),
            new OA\Response(response: 422, description: 'Parâmetros inválidos'),
        ]
    )]
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['required', 'in:7d,30d,90d'],
            'format' => ['sometimes', 'in:csv,pdf'],
        ]);

        $period = $request->query('period', '7d');
        $format = $request->query('format', 'csv');
        $user = $request->user();

        $days = (int) $period;
        $dateTo = Carbon::today()->toDateString();
        $dateFrom = Carbon::today()->subDays($days)->toDateString();

        // Return a ready export generated today if it exists
        $existing = NutritionExport::where('user_id', $user->id)
            ->where('period', $period)
            ->where('format', $format)
            ->where('status', 'ready')
            ->whereDate('generated_at', today())
            ->latest()
            ->first();

        if ($existing) {
            $url = $existing->s3_key
                ? Storage::disk('s3')->temporaryUrl($existing->s3_key, now()->addHour())
                : $existing->download_url;

            return response()->json([
                'id' => $existing->id,
                'status' => 'ready',
                'download_url' => $url,
                'period' => $period,
                'format' => $format,
                'generated_at' => $existing->generated_at?->toIso8601String(),
            ]);
        }

        $export = NutritionExport::create([
            'user_id' => $user->id,
            'period' => $period,
            'format' => $format,
            'status' => 'pending',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        NutritionExportJob::dispatch($export->id);

        return response()->json([
            'id' => $export->id,
            'status' => 'pending',
            'message' => 'Export em processamento. Consulte o status em /v1/nutrition/export/status/{id}.',
        ], 202);
    }

    #[OA\Get(
        path: '/api/v1/nutrition/export/status/{id}',
        summary: 'Consultar status do export nutricional',
        tags: ['Nutrition'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status do export'),
            new OA\Response(response: 404, description: 'Export não encontrado'),
        ]
    )]
    public function status(Request $request, string $id): JsonResponse
    {
        $export = NutritionExport::where('user_id', $request->user()->id)->findOrFail($id);

        $url = null;
        if ($export->status === 'ready' && $export->s3_key) {
            $url = Storage::disk('s3')->temporaryUrl($export->s3_key, now()->addHour());
        }

        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'period' => $export->period,
            'format' => $export->format,
            'download_url' => $url ?? $export->download_url,
            'generated_at' => $export->generated_at?->toIso8601String(),
        ]);
    }
}
