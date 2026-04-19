<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WaterLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class WaterLogController extends Controller
{
    #[OA\Get(
        path: '/api/v1/water-logs',
        summary: 'Listar registros de hidratação',
        description: 'Retorna os registros de consumo de água do usuário. Sem filtro, lista os mais recentes; com `date`, retorna apenas os do dia informado.',
        tags: ['Water Logs'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', required: false, description: 'Filtrar por dia específico (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Quantidade de registros (máx 365)', schema: new OA\Schema(type: 'integer', default: 60)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de registros de hidratação + total do dia'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->query('limit', 60), 365);
        $date = $request->query('date');

        $query = WaterLog::where('user_id', $user->id);
        if ($date) {
            $query->whereDate('date', $date);
        }

        $logs = $query->orderByDesc('date')
            ->orderByDesc('time')
            ->limit($limit)
            ->get();

        $todayTotal = WaterLog::where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->sum('liters');

        return response()->json([
            'data'        => $logs,
            'today_total' => round((float) $todayTotal, 2),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/water-logs',
        summary: 'Registrar consumo de água',
        description: 'Cria um registro de consumo de água. Múltiplos registros por dia são permitidos (cada copo/garrafa). Endpoint idempotente via header Idempotency-Key.',
        tags: ['Water Logs'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, description: 'Chave de idempotência (opcional)', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['liters'],
                properties: [
                    new OA\Property(property: 'liters', type: 'number', example: 0.25),
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-04-19'),
                    new OA\Property(property: 'time', type: 'string', example: '14:30'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Registro criado + total do dia'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'liters' => 'required|numeric|min:0.01|max:10',
            'date'   => 'nullable|date',
            'time'   => 'nullable|date_format:H:i',
        ]);

        $user = $request->user();
        $date = $data['date'] ?? now()->toDateString();

        $log = WaterLog::create([
            'user_id' => $user->id,
            'date'    => $date,
            'liters'  => $data['liters'],
            'time'    => $data['time'] ?? now()->format('H:i'),
        ]);

        $dayTotal = WaterLog::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->sum('liters');

        return response()->json([
            'log'       => $log,
            'day_total' => round((float) $dayTotal, 2),
        ], 201);
    }

    #[OA\Delete(
        path: '/api/v1/water-logs/{id}',
        summary: 'Remover registro de hidratação',
        description: 'Remove um registro de consumo de água do usuário.',
        tags: ['Water Logs'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do registro', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Registro removido'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $log = WaterLog::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $log->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
