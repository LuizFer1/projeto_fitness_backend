<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Models\UserQuest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class QuestController extends Controller
{
    #[OA\Get(
        path: '/api/v1/quests',
        summary: 'Listar quests ativas',
        description: 'Retorna todas as quests (missões) ativas disponíveis no sistema.',
        tags: ['Quests'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de quests'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $quests = Quest::where('is_active', true)->get();
        return response()->json(['data' => $quests]);
    }

    #[OA\Get(
        path: '/api/v1/quests/mine',
        summary: 'Minhas quests',
        description: 'Retorna as quests ativas com o progresso atual do usuário por período (semanal/mensal) ou avulso.',
        tags: ['Quests'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Quests e progresso do usuário'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $quests = Quest::where('is_active', true)->get();

        $data = $quests->map(fn (Quest $quest) => $this->formatQuestProgress($quest, $user->id));

        return response()->json(['data' => $data]);
    }

    private function formatQuestProgress(Quest $quest, $userId): array
    {
        $refPeriod = $this->resolveRefPeriod($quest->periodicity);
        $userQuest = $this->findUserQuest($userId, $quest->id, $refPeriod);

        return [
            'quest'        => $quest,
            'status'       => $userQuest?->status ?? 'not_started',
            'progress'     => $userQuest?->current_progress ?? 0,
            'target'       => $userQuest?->target_progress ?? $quest->condition_value,
            'xp_received'  => $userQuest?->xp_received ?? 0,
            'completed_at' => $userQuest?->completed_at,
            'ref_period'   => $refPeriod,
        ];
    }

    private function resolveRefPeriod(?string $periodicity): ?string
    {
        return match ($periodicity) {
            'weekly'  => now()->format('o-\WW'),
            'monthly' => now()->format('Y-m'),
            default   => null,
        };
    }

    private function findUserQuest($userId, $questId, ?string $refPeriod): ?UserQuest
    {
        return UserQuest::where('user_id', $userId)
            ->where('quest_id', $questId)
            ->where(fn ($q) => $refPeriod === null ? $q->whereNull('ref_period') : $q->where('ref_period', $refPeriod))
            ->first();
    }
}
