<?php

namespace App\Http\Resources\Gamification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaderboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->resource['rank'],
            'user_uuid' => $this->resource['user_uuid'],
            'xp_points' => (int) $this->resource['xp_points'],
            'user' => $this->when(isset($this->resource['user']), function () {
                $user = $this->resource['user'];
                return [
                    'uuid' => $user['uuid'] ?? null,
                    'name' => $user['name'] ?? null,
                    'avatar_url' => $user['avatar_url'] ?? null,
                    'level' => $user['level'] ?? null,
                ];
            }),
        ];
    }
}
