<?php

namespace App\Http\Resources;

use App\Models\UserGoal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserGoal
 */
class UserGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'main_goal' => $this->main_goal,
            'diet_objective' => $this->diet_objective,
            'goal_calories_day' => $this->goal_calories_day !== null ? (int) $this->goal_calories_day : null,
            'goal_steps_day' => $this->goal_steps_day !== null ? (int) $this->goal_steps_day : null,
            'goal_weight_kg' => $this->goal_weight_kg !== null ? (float) $this->goal_weight_kg : null,
            'goal_protein_g' => $this->goal_protein_g !== null ? (float) $this->goal_protein_g : null,
            'goal_carbs_g' => $this->goal_carbs_g !== null ? (float) $this->goal_carbs_g : null,
            'goal_fat_g' => $this->goal_fat_g !== null ? (float) $this->goal_fat_g : null,
            'goal_workouts_week' => $this->goal_workouts_week !== null ? (int) $this->goal_workouts_week : null,
            'goal_water_liters' => $this->goal_water_liters !== null ? (float) $this->goal_water_liters : null,
            'deadline' => $this->deadline instanceof Carbon ? $this->deadline->toDateString() : $this->deadline,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Empty / not-yet-created shape used by GET /api/goals when the user has no goal row.
     * Matches the same key set as toArray() so the frontend can rely on a stable schema.
     */
    public static function emptyShape(string $userId): array
    {
        return [
            'id' => null,
            'user_id' => $userId,
            'main_goal' => null,
            'diet_objective' => null,
            'goal_calories_day' => null,
            'goal_steps_day' => null,
            'goal_weight_kg' => null,
            'goal_protein_g' => null,
            'goal_carbs_g' => null,
            'goal_fat_g' => null,
            'goal_workouts_week' => null,
            'goal_water_liters' => null,
            'deadline' => null,
            'is_active' => false,
            'created_at' => null,
            'updated_at' => null,
        ];
    }
}
