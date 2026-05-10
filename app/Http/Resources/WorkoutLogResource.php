<?php

namespace App\Http\Resources;

use App\Models\WorkoutLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkoutLog
 */
class WorkoutLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date instanceof Carbon ? $this->date->toDateString() : $this->date,
            'modality' => $this->modality ?? 'strength',
            'duration_min' => $this->duration_min,
            'calories_burned' => $this->calories_burned !== null ? (float) $this->calories_burned : null,
            'muscles_trained' => $this->muscles_trained ?? [],
            'observations' => $this->observations,
            'ai_feedback' => $this->ai_feedback,
            'mood' => $this->mood,
            'distance_m' => $this->distance_m ?? null,
            'pace_seconds_per_km' => $this->pace_seconds_per_km ?? null,
            'avg_hr' => $this->avg_hr ?? null,
            'max_hr' => $this->max_hr ?? null,
            'route_polyline' => $this->route_polyline ?? null,
            'external_source' => $this->external_source ?? 'manual',
            'exercises' => $this->whenLoaded('workoutLogExercises'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
