<?php

namespace App\Application\UseCases\Workout;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Services\GamificationService;
use Illuminate\Support\Facades\DB;

class RegisterCardioSessionUseCase
{
    public function __construct(private GamificationService $gamification) {}

    /**
     * Persists a cardio workout session.
     *
     * Accepts optional GPS data (polyline, distance, pace, HR, elevation).
     * Checks external_source + external_id uniqueness for HealthKit/Google Fit/Garmin imports.
     */
    public function execute(User $user, array $data): WorkoutLog
    {
        // Dedup check for external sources (RNF-08)
        if (!empty($data['external_source']) && $data['external_source'] !== 'manual' && !empty($data['external_id'])) {
            $existing = WorkoutLog::where('external_source', $data['external_source'])
                ->where('external_id', $data['external_id'])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($user, $data) {
            $log = WorkoutLog::create([
                'user_id'             => $user->id,
                'date'                => $data['date'] ?? now()->toDateString(),
                'modality'            => 'cardio',
                'duration_min'        => $data['duration_min'] ?? null,
                'calories_burned'     => $data['calories_burned'] ?? null,
                'distance_m'          => $data['distance_m'] ?? null,
                'pace_seconds_per_km' => $data['pace_seconds_per_km'] ?? null,
                'avg_hr'              => $data['avg_hr'] ?? null,
                'max_hr'              => $data['max_hr'] ?? null,
                'elevation_gain_m'    => $data['elevation_gain_m'] ?? null,
                'route_polyline'      => $data['route_polyline'] ?? null,
                'route_geojson_path'  => $data['route_geojson_path'] ?? null,
                'external_source'     => $data['external_source'] ?? 'manual',
                'external_id'         => $data['external_id'] ?? null,
                'mood'                => $data['mood'] ?? 'neutral',
                'observations'        => $data['observations'] ?? null,
            ]);

            $this->gamification->grantCardioCompletedXp($user, $log->id);
            $this->gamification->checkWorkoutBadges($user);

            return $log;
        });
    }
}
