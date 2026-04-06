<?php

namespace App\Http\Resources\PublicProfile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'target_value' => (float) $this->target_value,
            'current_value' => (float) $this->current_value,
            'unit' => $this->unit,
            'deadline' => $this->deadline?->toDateString(),
            'visibility' => $this->visibility,
            'status' => $this->status,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
