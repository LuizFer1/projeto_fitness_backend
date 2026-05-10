<?php

namespace Tests\Feature\Workouts;

use App\Models\User;
use App\Models\UserGamification;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardioWorkoutControllerTest extends TestCase
{
    use RefreshDatabase;

    private function gamificationFor(User $user): void
    {
        UserGamification::create([
            'user_id' => $user->id,
            'xp_total' => 0,
            'current_level' => 1,
            'current_streak' => 0,
            'max_streak' => 0,
            'total_workouts' => 0,
            'total_water_days' => 0,
            'current_week_xp' => 0,
            'current_month_xp' => 0,
            'xp_to_next' => 500,
        ]);
    }

    public function test_store_creates_cardio_session(): void
    {
        $user = User::factory()->create();
        $this->gamificationFor($user);

        $response = $this->actingAs($user)->postJson('/api/v1/workouts/cardio', [
            'date' => '2026-04-18',
            'duration_min' => 30,
            'calories_burned' => 280,
            'distance_m' => 5000,
            'pace_seconds_per_km' => 360,
            'avg_hr' => 150,
            'max_hr' => 175,
            'mood' => 'good',
            'external_source' => 'manual',
        ])->assertCreated();

        $response->assertJsonPath('workout_log.modality', 'cardio')
            ->assertJsonPath('workout_log.duration_min', 30)
            ->assertJsonPath('workout_log.distance_m', 5000)
            ->assertJsonPath('workout_log.external_source', 'manual');

        $this->assertDatabaseHas('workout_logs', [
            'user_id' => $user->id,
            'modality' => 'cardio',
            'duration_min' => 30,
        ]);
    }

    public function test_store_dedups_imports_by_external_source_and_id(): void
    {
        $user = User::factory()->create();
        $this->gamificationFor($user);

        $first = $this->actingAs($user)->postJson('/api/v1/workouts/cardio', [
            'date' => '2026-04-18',
            'duration_min' => 30,
            'distance_m' => 5000,
            'external_source' => 'healthkit',
            'external_id' => 'hk-123',
        ])->assertCreated();

        $second = $this->actingAs($user)->postJson('/api/v1/workouts/cardio', [
            'date' => '2026-04-19',
            'duration_min' => 45,
            'distance_m' => 9000,
            'external_source' => 'healthkit',
            'external_id' => 'hk-123',
        ])->assertCreated();

        // Same external_id → returns the original log (no new row)
        $this->assertSame($first->json('workout_log.id'), $second->json('workout_log.id'));
        $this->assertEquals(1, WorkoutLog::where('user_id', $user->id)->count());
    }

    public function test_store_validates_heart_rate_bounds(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/workouts/cardio', [
            'avg_hr' => 12,
        ])->assertUnprocessable();
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/workouts/cardio', [])->assertUnauthorized();
    }
}
