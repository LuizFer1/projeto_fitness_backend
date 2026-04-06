<?php

namespace App\Application\UseCases\Gamification;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class GetFriendsLeaderboardUseCase
{
    public function execute(User $user): array
    {
        $cacheKey = "leaderboard:friends:{$user->uuid}";

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $friendUuids = Friendship::where('user_uuid', $user->uuid)
                ->where('status', 'accepted')
                ->pluck('friend_uuid')
                ->toArray();

            $allUuids = array_merge($friendUuids, [$user->uuid]);

            $users = User::whereIn('uuid', $allUuids)
                ->orderByDesc('xp_points')
                ->get(['uuid', 'name', 'avatar_url', 'level', 'xp_points']);

            $rank = 0;

            return $users->map(function ($u) use (&$rank) {
                $rank++;
                return [
                    'rank' => $rank,
                    'user_uuid' => $u->uuid,
                    'xp_points' => (int) $u->xp_points,
                    'user' => [
                        'uuid' => $u->uuid,
                        'name' => $u->name,
                        'avatar_url' => $u->avatar_url,
                        'level' => $u->level,
                    ],
                ];
            })->toArray();
        });
    }
}
