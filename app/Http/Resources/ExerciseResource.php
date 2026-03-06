<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $exerciseWeekly = $this['exerciseWeekly'] ?? null;
        $todayWorkout   = $this['todayWorkout'] ?? null;
        $weekPlanDays   = $this['weekPlanDays'] ?? collect();
        $history        = $this['history'] ?? collect();
        $records        = $this['records'] ?? collect();
        $muscleGroups   = $this['muscleGroups'] ?? collect();
        $goals          = $this['goals'] ?? collect();

        // ── Today's Workout ───────────────────────────────────────────
        $formattedToday = null;
        if ($todayWorkout) {
            $formattedToday = [
                'id'        => (string) $todayWorkout->id,
                'name'      => $todayWorkout->name,
                'duration'  => $todayWorkout->duration_min,
                'calories'  => $todayWorkout->calories,
                'level'     => $todayWorkout->level,
                'exercises' => $todayWorkout->exercises->map(function ($ex) {
                    return [
                        'id'     => (string) $ex->id,
                        'name'   => $ex->name,
                        'sets'   => $ex->sets,
                        'reps'   => $ex->reps,
                        'weight' => $ex->weight,
                        'rest'   => $ex->rest_seconds,
                        'done'   => (bool) $ex->done,
                    ];
                })->values()->all(),
            ];
        }

        // ── Weekly Plan Grid ──────────────────────────────────────────
        $formattedWeekDays = $weekPlanDays->map(function ($day) {
            return [
                'label' => $day->day_label,
                'group' => $day->muscle_group,
                'done'  => (bool) $day->done,
                'today' => (bool) $day->is_today,
            ];
        })->values()->all();

        // ── Workout History ───────────────────────────────────────────
        $formattedHistory = $history->map(function ($workout) {
            return [
                'id'       => (string) $workout->id,
                'date'     => $workout->workout_date->toDateString(),
                'name'     => $workout->name,
                'duration' => $workout->duration_min,
                'kcal'     => $workout->calories,
            ];
        })->values()->all();

        // ── Personal Records ──────────────────────────────────────────
        $formattedRecords = $records->map(function ($record) {
            return [
                'id'       => (string) $record->id,
                'exercise' => $record->exercise,
                'value'    => $record->value,
                'icon'     => $record->icon,
            ];
        })->values()->all();

        // ── Muscle Groups ─────────────────────────────────────────────
        $formattedMuscleGroups = $muscleGroups->map(function ($group) {
            return [
                'name'     => $group->name,
                'sessions' => $group->sessions,
                'color'    => $group->color_hex,
            ];
        })->values()->all();

        return [
            'weeklyGoal'  => $exerciseWeekly ? $exerciseWeekly->goal_sessions : 5,
            'weeklyDone'  => $exerciseWeekly ? $exerciseWeekly->done_sessions : 0,
            'stats'       => [
                'caloriesBurned' => $exerciseWeekly ? $exerciseWeekly->calories_burned : 0,
                'totalTime'      => $exerciseWeekly ? $exerciseWeekly->total_minutes : 0,
                'streak'         => $exerciseWeekly ? $exerciseWeekly->streak_days : 0,
            ],
            'todayWorkout'  => $formattedToday,
            'weekDays'      => $formattedWeekDays,
            'history'       => $formattedHistory,
            'records'       => $formattedRecords,
            'muscleGroups'  => $formattedMuscleGroups,
            'userGoals'     => $goals,
        ];
    }
}
