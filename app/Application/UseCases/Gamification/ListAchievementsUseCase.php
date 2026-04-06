<?php

namespace App\Application\UseCases\Gamification;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Support\Collection;

class ListAchievementsUseCase
{
    public function execute(User $user): Collection
    {
        $achievements = Achievement::leftJoin('user_achievements', function ($join) use ($user) {
            $join->on('achievements.id', '=', 'user_achievements.achievement_id')
                ->where('user_achievements.user_uuid', '=', $user->uuid);
        })
            ->select('achievements.*', 'user_achievements.unlocked_at')
            ->orderByRaw('user_achievements.unlocked_at IS NULL ASC')
            ->orderByRaw('user_achievements.unlocked_at DESC')
            ->orderBy('achievements.category')
            ->get();

        return $achievements;
    }
}
