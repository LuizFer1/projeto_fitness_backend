<?php

namespace App\Http\Controllers\Api\V1\Gamification;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gamification\LeaderboardResource;
use App\Models\LeaderboardSnapshot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LeaderboardController extends Controller
{
    public function weekly(Request $request)
    {
        $periodKey = Carbon::now()->format('o-\\WW');

        return $this->respondForPeriod($request, 'weekly', $periodKey, 3600);
    }

    public function monthly(Request $request)
    {
        $periodKey = Carbon::now()->format('Y-m');

        return $this->respondForPeriod($request, 'monthly', $periodKey, 3600);
    }

    public function alltime(Request $request)
    {
        return $this->respondForPeriod($request, 'alltime', 'global', 21600);
    }

    private function respondForPeriod(Request $request, string $period, string $periodKey, int $ttl)
    {
        $cacheKey = "leaderboard:{$period}:{$periodKey}";

        $data = Cache::remember($cacheKey, $ttl, function () use ($period, $periodKey) {
            return LeaderboardSnapshot::where('period', $period)
                ->where('period_key', $periodKey)
                ->orderBy('rank')
                ->take(100)
                ->with('user')
                ->get()
                ->map(fn ($snapshot) => [
                    'rank' => $snapshot->rank,
                    'user_uuid' => $snapshot->user_uuid,
                    'xp_points' => (int) $snapshot->xp_points,
                    'user' => $snapshot->user ? [
                        'uuid' => $snapshot->user->uuid,
                        'name' => $snapshot->user->name,
                        'avatar_url' => $snapshot->user->avatar_url,
                        'level' => $snapshot->user->level,
                    ] : null,
                ])
                ->toArray();
        });

        $user = $request->user();
        $currentUserRank = null;

        if ($user) {
            $inTop100 = collect($data)->firstWhere('user_uuid', $user->uuid);

            if ($inTop100) {
                $currentUserRank = $inTop100['rank'];
            } else {
                $snapshot = LeaderboardSnapshot::where('period', $period)
                    ->where('period_key', $periodKey)
                    ->where('user_uuid', $user->uuid)
                    ->first();

                $currentUserRank = $snapshot?->rank;
            }
        }

        return LeaderboardResource::collection($data)
            ->additional([
                'meta' => [
                    'current_user_rank' => $currentUserRank,
                    'period' => $period,
                    'period_key' => $periodKey,
                ],
            ]);
    }
}
