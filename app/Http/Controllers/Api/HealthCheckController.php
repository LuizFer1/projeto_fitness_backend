<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use OpenApi\Attributes as OA;

class HealthCheckController extends Controller
{
    #[OA\Get(
        path: '/api/health',
        summary: 'Health check',
        description: 'Verifica o status da aplicação, banco de dados e Redis.',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'Todos os serviços estão saudáveis'),
            new OA\Response(response: 503, description: 'Um ou mais serviços estão indisponíveis'),
        ]
    )]
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $allHealthy = collect($checks)->every(fn (array $check) => $check['status'] === 'up');

        $status = $allHealthy ? 'healthy' : 'unhealthy';
        $statusCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $statusCode);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'up'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::connection()->ping();

            return ['status' => 'up'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }
}
