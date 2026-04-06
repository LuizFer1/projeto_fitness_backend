<?php

namespace App\Http\Resources\Gamification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'category' => $this->category,
            'xp_reward' => $this->xp_reward,
            'is_unlocked' => $this->unlocked_at !== null,
            'unlocked_at' => $this->unlocked_at,
        ];
    }
}
