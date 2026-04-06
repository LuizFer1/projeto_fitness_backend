<?php

namespace App\Http\Resources\PublicProfile;

use App\Services\Gamification\LevelCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProfileResource extends JsonResource
{
    private string $friendshipStatus = 'none';

    public function withFriendshipStatus(string $status): static
    {
        $this->friendshipStatus = $status;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'username'               => $this->username,
            'name'                   => $this->name,
            'avatar'                 => $this->avatar_url,
            'bio'                    => $this->bio,
            'level'                  => $this->level ?? 1,
            'level_name'             => LevelCalculator::nameFor($this->level ?? 1),
            'xp_points'             => $this->xp_points ?? 0,
            'achievements_count'     => $this->achievements_count ?? 0,
            'goals_completed_count'  => $this->goals_completed_count ?? 0,
            'friendship_status'      => $this->friendshipStatus,
        ];
    }
}
