<?php

namespace Tests\Feature\BodyMeasurements;

use App\Models\BodyMeasurement;
use App\Models\User;
use App\Models\UserGamification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BodyMeasurementControllerTest extends TestCase
{
    use RefreshDatabase;

    private function gamification(User $user): void
    {
        UserGamification::create([
            'user_id' => $user->id,
            'xp_total' => 0, 'current_level' => 1, 'xp_to_next' => 200,
            'current_streak' => 0, 'max_streak' => 0,
            'current_week_xp' => 0, 'current_month_xp' => 0,
            'total_workouts' => 0, 'total_water_days' => 0,
        ]);
    }

    public function test_store_creates_measurement_and_grants_xp(): void
    {
        $user = User::factory()->create();
        $this->gamification($user);

        $response = $this->actingAs($user)->postJson('/api/v1/measurements', [
            'date' => '2026-04-18',
            'weight_kg' => 80.0,
        ])->assertCreated();

        $this->assertSame('80.00', $response->json('measurement.weight_kg'));
        $this->assertGreaterThanOrEqual(0, (int) $response->json('xp_gained'));

        $this->assertDatabaseHas('body_measurements', [
            'user_id' => $user->id,
            'weight_kg' => 80.0,
        ]);
    }

    public function test_index_lists_user_measurements(): void
    {
        $user = User::factory()->create();
        $this->gamification($user);

        $this->actingAs($user)->postJson('/api/v1/measurements', [
            'date' => '2026-04-17', 'weight_kg' => 80.0,
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson('/api/v1/measurements')->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_destroy_removes_own_measurement(): void
    {
        $user = User::factory()->create();
        $this->gamification($user);

        $create = $this->actingAs($user)->postJson('/api/v1/measurements', [
            'date' => '2026-04-18', 'weight_kg' => 80.0,
        ])->assertCreated();

        $id = $create->json('measurement.id');

        $this->actingAs($user)->deleteJson("/api/v1/measurements/{$id}")->assertOk();
        $this->assertDatabaseMissing('body_measurements', ['id' => $id]);
    }
}
