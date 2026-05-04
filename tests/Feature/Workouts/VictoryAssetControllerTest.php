<?php

namespace Tests\Feature\Workouts;

use App\Jobs\GenerateVictoryAssetJob;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\VictoryAsset;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VictoryAssetControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithGam(): User
    {
        $user = User::factory()->create();
        UserGamification::create([
            'user_id'         => $user->id,
            'current_streak'  => 0,
            'xp_total'        => 0,
            'current_level'   => 1,
            'current_week_xp' => 0,
            'current_month_xp'=> 0,
            'total_workouts'  => 0,
            'total_water_days'=> 0,
            'max_streak'      => 0,
            'xp_to_next'      => 500,
        ]);
        return $user;
    }

    private function createWorkoutLog(User $user, array $overrides = []): WorkoutLog
    {
        return WorkoutLog::create(array_merge([
            'user_id'  => $user->id,
            'date'     => today()->toDateString(),
            'modality' => 'strength',
        ], $overrides));
    }

    public function test_enqueue_creates_asset_and_dispatches_job(): void
    {
        Queue::fake();
        $user = $this->createUserWithGam();
        $log  = $this->createWorkoutLog($user);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/workouts/{$log->id}/victory-asset")
            ->assertCreated();

        $this->assertEquals('pending', $response->json('status'));
        $this->assertNotNull($response->json('id'));

        $this->assertDatabaseHas('victory_assets', [
            'workout_log_id' => $log->id,
            'user_id'        => $user->id,
            'status'         => 'pending',
            'type'           => 'strength',
        ]);

        Queue::assertPushed(GenerateVictoryAssetJob::class);
    }

    public function test_cardio_workout_creates_cardio_type_asset(): void
    {
        Queue::fake();
        $user = $this->createUserWithGam();
        $log  = $this->createWorkoutLog($user, ['modality' => 'cardio']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/workouts/{$log->id}/victory-asset")
            ->assertCreated();

        $this->assertDatabaseHas('victory_assets', [
            'workout_log_id' => $log->id,
            'type'           => 'cardio',
        ]);
    }

    public function test_enqueue_returns_202_if_asset_already_pending(): void
    {
        Queue::fake();
        $user = $this->createUserWithGam();
        $log  = $this->createWorkoutLog($user);

        VictoryAsset::create([
            'workout_log_id' => $log->id,
            'user_id'        => $user->id,
            'type'           => 'strength',
            'status'         => 'pending',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/workouts/{$log->id}/victory-asset")
            ->assertStatus(202);

        Queue::assertNothingPushed();
    }

    public function test_show_returns_asset_status(): void
    {
        $user  = $this->createUserWithGam();
        $log   = $this->createWorkoutLog($user);

        VictoryAsset::create([
            'workout_log_id' => $log->id,
            'user_id'        => $user->id,
            'type'           => 'strength',
            'status'         => 'ready',
            'public_url'     => 'https://cdn.example.com/asset.png',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/workouts/{$log->id}/victory-asset")
            ->assertOk();

        $this->assertEquals('ready', $response->json('status'));
        $this->assertEquals('https://cdn.example.com/asset.png', $response->json('url'));
    }

    public function test_show_returns_404_when_no_asset_exists(): void
    {
        $user = $this->createUserWithGam();
        $log  = $this->createWorkoutLog($user);

        $this->actingAs($user)
            ->getJson("/api/v1/workouts/{$log->id}/victory-asset")
            ->assertNotFound();
    }

    public function test_cannot_access_another_users_workout(): void
    {
        Queue::fake();
        [$owner, $other] = User::factory()->count(2)->create()->all();
        $log = $this->createWorkoutLog($owner);

        $this->actingAs($other)
            ->postJson("/api/v1/workouts/{$log->id}/victory-asset")
            ->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/workouts/fake-uuid/victory-asset')->assertUnauthorized();
        $this->getJson('/api/v1/workouts/fake-uuid/victory-asset')->assertUnauthorized();
    }
}
