<?php

namespace App\Http\Resources;

use App\Models\MealLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MealLog
 */
class MealLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date instanceof Carbon ? $this->date->toDateString() : $this->date,
            'meal_type' => $this->meal_type,
            'calories_consumed' => (float) $this->calories_consumed,
            'protein_g' => (float) $this->protein_g,
            'carbs_g' => (float) $this->carbs_g,
            'fat_g' => (float) $this->fat_g,
            'fiber_g' => $this->fiber_g !== null ? (float) $this->fiber_g : null,
            'user_note' => $this->user_note,
            'ai_feedback' => $this->ai_feedback,
            'items_json' => $this->items_json,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
