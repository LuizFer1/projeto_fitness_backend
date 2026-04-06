<?php

namespace App\Http\Resources\PublicProfile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicAchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->achievement->id,
            'key' => $this->achievement->key,
            'title' => $this->achievement->title,
            'description' => $this->achievement->description,
            'icon' => $this->achievement->icon,
            'category' => $this->achievement->category,
            'xp_reward' => $this->achievement->xp_reward,
            'unlocked_at' => $this->unlocked_at,
        ];
    }
}
