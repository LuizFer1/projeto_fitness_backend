<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Models\UserQuest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $quests = Quest::where('is_active', true)->get();
        return response()->json(['data' => $quests]);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $quests = Quest::where('is_active', true)->get();
        $refPeriods = [
            'weekly'  => now()->format('o-\WW'),
            'monthly' => now()->format('Y-m'),
        ];

        $data = $quests->map(function (Quest $quest) use ($user, $refPeriods) {
            $refPeriod = match ($quest->periodicity) {
                'weekly'  => $refPeriods['weekly'],
                'monthly' => $refPeriods['monthly'],
                default   => null,
            };

            $userQuest = UserQuest::where('user_id', $user->id)
                ->where('quest_id', $quest->id)
                ->where(function ($q) use ($refPeriod) {
                    $refPeriod === null
                        ? $q->whereNull('ref_period')
                        : $q->where('ref_period', $refPeriod);
                })
                ->first();

            return [
                'quest'      => $quest,
                'status'     => $userQuest?->status ?? 'not_started',
                'progress'   => $userQuest?->current_progress ?? 0,
                'target'     => $userQuest?->target_progress ?? $quest->condition_value,
                'xp_received'=> $userQuest?->xp_received ?? 0,
                'completed_at' => $userQuest?->completed_at,
                'ref_period' => $refPeriod,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
