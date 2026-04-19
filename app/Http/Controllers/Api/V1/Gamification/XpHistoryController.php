<?php

namespace App\Http\Controllers\Api\V1\Gamification;

use App\Http\Controllers\Controller;
use App\Models\XpTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class XpHistoryController extends Controller
{
    #[OA\Get(
        path: '/api/v1/gamification/xp-history',
        summary: 'Histórico de XP',
        description: 'Retorna o histórico paginado de transações de XP do usuário autenticado.',
        tags: ['Gamification'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Histórico de XP paginado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $transactions = XpTransaction::where('user_id', $request->user()->id)
            ->orderByDesc('date')
            ->paginate(20);

        return response()->json($transactions);
    }
}
