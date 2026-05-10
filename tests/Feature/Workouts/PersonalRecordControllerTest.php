<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\ExercisePersonalRecord;
use App\Models\User;
use App\Models\WorkoutExerciseLog;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalRecordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_top_pr_per_exercise(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();

        $log = WorkoutLog::create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'duration_min' => 60,
        ]);

        ExercisePersonalRecord::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'workout_log_id' => $log->id,
            'one_rm_kg' => 80,
            'weight_kg' => 70,
            'reps' => 5,
            'achieved_at' => '2026-04-01',
        ]);
        ExercisePersonalRecord::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'workout_log_id' => $log->id,
            'one_rm_kg' => 95,
            'weight_kg' => 80,
            'reps' => 5,
            'achieved_at' => '2026-04-15',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/workouts/personal-records')
            ->assertOk();

        $records = $response->json('personal_records');
        $this->assertCount(1, $records);
        $this->assertEquals(95, (float) $records[0]['one_rm_kg']);
    }

    public function test_history_returns_records_ordered_desc(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();

        $log = WorkoutLog::create(['user_id' => $user->id, 'date' => today()->toDateString()]);

        foreach ([['80', '2026-03-01'], ['95', '2026-04-15'], ['100', '2026-05-01']] as [$kg, $date]) {
            ExercisePersonalRecord::create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'workout_log_id' => $log->id,
                'one_rm_kg' => $kg,
                'weight_kg' => $kg,
                'reps' => 5,
                'achieved_at' => $date,
            ]);
        }

        $response = $this->actingAs($user)
            ->getJson("/api/v1/workouts/exercises/{$exercise->id}/history")
            ->assertOk();

        $history = $response->json('history');
        $this->assertCount(3, $history);
        $this->assertEquals(100, (float) $history[0]['one_rm_kg']);
    }

    public function test_suggest_load_returns_no_data_when_no_history(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/workouts/exercises/{$exercise->id}/suggest-load")
            ->assertOk();

        $this->assertNull($response->json('suggested_weight_kg'));
        $this->assertEquals('no_recent_data', $response->json('reason'));
    }

    public function test_suggest_load_increments_when_two_stable_sessions(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();

        foreach ([today()->toDateString(), today()->copy()->subDay()->toDateString()] as $d) {
            $log = WorkoutLog::create(['user_id' => $user->id, 'date' => $d, 'duration_min' => 60]);
            WorkoutExerciseLog::create([
                'workout_log_id' => $log->id,
                'exercise_id' => $exercise->id,
                'sets' => 3,
                'reps' => 10,
                'weight_kg' => 60,
            ]);
        }

        $response = $this->actingAs($user)
            ->getJson("/api/v1/workouts/exercises/{$exercise->id}/suggest-load")
            ->assertOk();

        $this->assertEquals('stable_performance', $response->json('reason'));
        $this->assertEquals(60.0, (float) $response->json('last_weight_kg'));
        $this->assertGreaterThanOrEqual(60.0, (float) $response->json('suggested_weight_kg'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/workouts/personal-records')->assertUnauthorized();
    }
}
