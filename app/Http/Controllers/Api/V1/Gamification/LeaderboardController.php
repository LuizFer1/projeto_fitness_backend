<?php

namespace App\Http\Controllers\Api\V1\Gamification;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\UserGamification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LeaderboardController extends Controller
{
    public function weekly(Request $request): JsonResponse
    {
        return $this->leaderboard($request, 'weekly');
    }

    public function monthly(Request $request): JsonResponse
    {
        return $this->leaderboard($request, 'monthly');
    }

    public function alltime(Request $request): JsonResponse
    {
        return $this->leaderboard($request, 'all_time');
    }

    /**
     * GET /api/v1/gamification/leaderboard/friends
     *
     * Ranks accepted friends + the authenticated user by XP.
     */
    public function friends(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->query('limit', 20), 100);

        // Get accepted friend IDs (both directions)
        $friendIds = Friendship::accepted()
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                  ->orWhere('addressee_id', $user->id);
            })
            ->get()
            ->map(fn ($f) => $f->requester_id === $user->id ? $f->addressee_id : $f->requester_id)
            ->values()
            ->toArray();

        // Include the authenticated user
        $userIds = array_merge($friendIds, [$user->id]);

        $rankings = UserGamification::select([
                'user_id',
                'xp_total as period_xp',
                'current_level',
                'xp_total',
            ])
            ->with(['user:id,name,last_name,nickname,avatar_url'])
            ->whereIn('user_id', $userIds)
            ->orderByDesc('xp_total')
            ->limit($limit)
            ->get()
            ->map(function ($row, $index) {
                return [
                    'position'   => $index + 1,
                    'user_id'    => $row->user_id,
                    'name'       => $row->user->nickname ?? ($row->user->name . ' ' . $row->user->last_name),
                    'avatar_url' => $row->user->avatar_url,
                    'period_xp'  => $row->period_xp,
                    'level'      => $row->current_level,
                    'total_xp'   => $row->xp_total,
                ];
            })
            ->toArray();

        // Get current user's position in the friends ranking
        $myPosition = null;
        foreach ($rankings as $entry) {
            if ($entry['user_id'] === $user->id) {
                $myPosition = [
                    'position'  => $entry['position'],
                    'period_xp' => $entry['period_xp'],
                    'level'     => $entry['level'],
                    'total_xp'  => $entry['total_xp'],
                ];
                break;
            }
        }

        return response()->json([
            'period'      => 'friends',
            'rankings'    => $rankings,
            'my_position' => $myPosition,
        ]);
    }

    // ── Private ──

    private function leaderboard(Request $request, string $period): JsonResponse
    {
        $user  = $request->user();
        $limit = min((int) $request->query('limit', 20), 100);

        $column = match ($period) {
            'weekly'   => 'current_week_xp',
            'monthly'  => 'current_month_xp',
            'all_time' => 'xp_total',
        };

        $cacheKey = "leaderboard_{$period}_top{$limit}";

        $rankings = Cache::remember($cacheKey, 300, function () use ($column, $limit) {
            return UserGamification::select([
                    'user_id',
                    $column . ' as period_xp',
                    'current_level',
                    'xp_total',
                ])
                ->with(['user:id,name,last_name,nickname,avatar_url'])
                ->orderByDesc($column)
                ->limit($limit)
                ->get()
                ->map(function ($row, $index) {
                    return [
                        'position'   => $index + 1,
                        'user_id'    => $row->user_id,
                        'name'       => $row->user->nickname ?? ($row->user->name . ' ' . $row->user->last_name),
                        'avatar_url' => $row->user->avatar_url,
                        'period_xp'  => $row->period_xp,
                        'level'      => $row->current_level,
                        'total_xp'   => $row->xp_total,
                    ];
                })
                ->toArray();
        });

        // Current user's position (fresh)
        $gam = UserGamification::where('user_id', $user->id)->first();
        $myPosition = null;

        if ($gam) {
            $myXp = $gam->{$column};
            $position = UserGamification::where($column, '>', $myXp)->count() + 1;

            $myPosition = [
                'position'  => $position,
                'period_xp' => $myXp,
                'level'     => $gam->current_level,
                'total_xp'  => $gam->xp_total,
            ];
        }

        return response()->json([
            'period'      => $period,
            'rankings'    => $rankings,
            'my_position' => $myPosition,
        ]);
    }
}
