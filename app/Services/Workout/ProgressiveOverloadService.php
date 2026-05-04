<?php

namespace App\Services\Workout;

use App\Domain\Workout\EpleyCalculator;
use App\Models\Exercise;
use App\Models\ExercisePersonalRecord;
use App\Models\User;
use App\Models\WorkoutExerciseLog;
use App\Services\GamificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProgressiveOverloadService
{
    public function __construct(private GamificationService $gamification) {}

    /**
     * Suggests the next training load for an exercise.
     * Applies 2.5%–5% increments every 14 days of stable performance (SRS RF-09).
     */
    public function suggestNextLoad(User $user, string $exerciseId): array
    {
        $last14Days = WorkoutExerciseLog::join('workout_logs', 'workout_exercise_logs.workout_log_id', '=', 'workout_logs.id')
            ->where('workout_logs.user_id', $user->id)
            ->where('workout_exercise_logs.exercise_id', $exerciseId)
            ->where('workout_logs.date', '>=', Carbon::now()->subDays(14)->toDateString())
            ->orderByDesc('workout_logs.date')
            ->select('workout_exercise_logs.weight_kg', 'workout_exercise_logs.reps', 'workout_logs.date')
            ->get();

        if ($last14Days->isEmpty()) {
            return ['suggested_weight_kg' => null, 'reason' => 'no_recent_data'];
        }

        $lastWeight = (float) $last14Days->first()->weight_kg;
        $lastReps   = (int) $last14Days->first()->reps;

        // Check if last 2 sessions had the same load (stable performance)
        $isStable = $last14Days->count() >= 2
            && $last14Days[0]->weight_kg == $last14Days[1]->weight_kg;

        $incrementPct = $isStable ? 0.025 : 0.0; // 2.5% when stable
        $suggested    = $isStable
            ? round($lastWeight * (1 + $incrementPct) / 2.5) * 2.5 // round to nearest 2.5kg
            : $lastWeight;

        return [
            'last_weight_kg'     => $lastWeight,
            'last_reps'          => $lastReps,
            'suggested_weight_kg'=> $suggested,
            'increment_pct'      => $incrementPct * 100,
            'reason'             => $isStable ? 'stable_performance' : 'first_session_or_variable',
        ];
    }

    /**
     * Calculates 1RM via Epley and records it as a PR if it beats the current best.
     * Grants PR XP and triggers badge if it's a new record (SRS RF-08).
     */
    public function recordPotentialPR(
        User $user, string $exerciseId, float $weightKg, int $reps, string $workoutLogId
    ): ?ExercisePersonalRecord {
        $oneRm = EpleyCalculator::calculate($weightKg, $reps);

        if ($oneRm <= 0) {
            return null;
        }

        $currentBest = ExercisePersonalRecord::where('user_id', $user->id)
            ->where('exercise_id', $exerciseId)
            ->max('one_rm_kg');

        if ($currentBest !== null && $oneRm <= (float) $currentBest) {
            return null; // Not a new PR
        }

        return DB::transaction(function () use ($user, $exerciseId, $weightKg, $reps, $oneRm, $workoutLogId) {
            $pr = ExercisePersonalRecord::create([
                'user_id'        => $user->id,
                'exercise_id'    => $exerciseId,
                'workout_log_id' => $workoutLogId,
                'one_rm_kg'      => $oneRm,
                'weight_kg'      => $weightKg,
                'reps'           => $reps,
                'achieved_at'    => now()->toDateString(),
            ]);

            $this->gamification->grantPrSetXp($user, $exerciseId);

            return $pr;
        });
    }

    /**
     * Returns the personal record history for an exercise.
     */
    public function getHistory(User $user, string $exerciseId): \Illuminate\Database\Eloquent\Collection
    {
        return ExercisePersonalRecord::where('user_id', $user->id)
            ->where('exercise_id', $exerciseId)
            ->orderByDesc('achieved_at')
            ->get();
    }
}
