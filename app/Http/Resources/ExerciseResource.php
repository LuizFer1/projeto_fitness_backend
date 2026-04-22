<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $exerciseWeekly = $this['exerciseWeekly'] ?? null;

        return [
            'weeklyGoal'   => $exerciseWeekly ? $exerciseWeekly->goal_sessions : 5,
            'weeklyDone'   => $exerciseWeekly ? $exerciseWeekly->done_sessions : 0,
            'stats'        => $this->formatStats($exerciseWeekly),
            'todayWorkout' => $this->formatTodayWorkout($this['todayWorkout'] ?? null),
            'weekDays'     => $this->formatWeekDays($this['weekPlanDays'] ?? collect()),
            'history'      => $this->formatHistory($this['history'] ?? collect()),
            'records'      => $this->formatRecords($this['records'] ?? collect()),
            'muscleGroups' => $this->formatMuscleGroups($this['muscleGroups'] ?? collect()),
            'userGoals'    => $this['goals'] ?? collect(),
        ];
    }

    private function formatStats($exerciseWeekly): array
    {
        return [
            'caloriesBurned' => $exerciseWeekly ? $exerciseWeekly->calories_burned : 0,
            'totalTime'      => $exerciseWeekly ? $exerciseWeekly->total_minutes : 0,
            'streak'         => $exerciseWeekly ? $exerciseWeekly->streak_days : 0,
        ];
    }

    private function formatTodayWorkout($todayWorkout): ?array
    {
        if (!$todayWorkout) {
            return null;
        }

        return [
            'id'        => (string) $todayWorkout->id,
            'name'      => $todayWorkout->name,
            'duration'  => $todayWorkout->duration_min,
            'calories'  => $todayWorkout->calories,
            'level'     => $todayWorkout->level,
            'exercises' => $todayWorkout->exercises->map(fn ($ex) => [
                'id'     => (string) $ex->id,
                'name'   => $ex->name,
                'sets'   => $ex->sets,
                'reps'   => $ex->reps,
                'weight' => $ex->weight,
                'rest'   => $ex->rest_seconds,
                'done'   => (bool) $ex->done,
            ])->values()->all(),
        ];
    }

    private function formatWeekDays(Collection $weekPlanDays): array
    {
        return $weekPlanDays->map(fn ($day) => [
            'label' => $day->day_label,
            'group' => $day->muscle_group,
            'done'  => (bool) $day->done,
            'today' => (bool) $day->is_today,
        ])->values()->all();
    }

    private function formatHistory(Collection $history): array
    {
        return $history->map(fn ($workout) => [
            'id'       => (string) $workout->id,
            'date'     => $workout->workout_date->toDateString(),
            'name'     => $workout->name,
            'duration' => $workout->duration_min,
            'kcal'     => $workout->calories,
        ])->values()->all();
    }

    private function formatRecords(Collection $records): array
    {
        return $records->map(fn ($record) => [
            'id'       => (string) $record->id,
            'exercise' => $record->exercise,
            'value'    => $record->value,
            'icon'     => $record->icon,
        ])->values()->all();
    }

    private function formatMuscleGroups(Collection $muscleGroups): array
    {
        return $muscleGroups->map(fn ($group) => [
            'name'     => $group->name,
            'sessions' => $group->sessions,
            'color'    => $group->color_hex,
        ])->values()->all();
    }
}
