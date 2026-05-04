<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateBiweeklyReportJob;
use App\Models\BiweeklyReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class BiweeklyReportController extends Controller
{
    #[OA\Get(
        path: '/api/v1/reports/biweekly',
        summary: 'Listar relatórios quinzenais',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Lista paginada de relatórios')]
    )]
    public function index(Request $request): JsonResponse
    {
        $reports = BiweeklyReport::where('user_id', $request->user()->id)
            ->orderByDesc('period_start')
            ->paginate(10);

        return response()->json($reports);
    }

    #[OA\Get(
        path: '/api/v1/reports/biweekly/{id}',
        summary: 'Detalhes do relatório quinzenal',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Relatório com URL de download se pronto'),
            new OA\Response(response: 404, description: 'Relatório não encontrado'),
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $report = BiweeklyReport::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $downloadUrl = null;
        if ($report->status === 'ready' && $report->s3_key) {
            $downloadUrl = Storage::disk('s3')->temporaryUrl($report->s3_key, now()->addHour());
        }

        return response()->json([
            'id'           => $report->id,
            'period_start' => $report->period_start?->toDateString(),
            'period_end'   => $report->period_end?->toDateString(),
            'status'       => $report->status,
            'summary'      => $report->summary_data,
            'download_url' => $downloadUrl ?? $report->public_url,
            'generated_at' => $report->generated_at?->toIso8601String(),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/reports/biweekly/generate',
        summary: 'Gerar relatório quinzenal manualmente',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Relatório já existe para este período'),
            new OA\Response(response: 202, description: 'Relatório enfileirado'),
        ]
    )]
    public function generate(Request $request): JsonResponse
    {
        $user  = $request->user();
        $end   = now()->subDay()->toDateString();
        $start = now()->subDays(14)->toDateString();

        $existing = BiweeklyReport::where('user_id', $user->id)
            ->where('period_start', $start)
            ->first();

        if ($existing) {
            return response()->json([
                'id'     => $existing->id,
                'status' => $existing->status,
                'message' => 'Relatório para este período já existe.',
            ], 200);
        }

        $report = BiweeklyReport::create([
            'user_id'      => $user->id,
            'period_start' => $start,
            'period_end'   => $end,
            'status'       => 'pending',
        ]);

        GenerateBiweeklyReportJob::dispatch($report->id);

        return response()->json(['id' => $report->id, 'status' => 'pending'], 202);
    }
}
