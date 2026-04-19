<?php

namespace Tests\Feature\WaterLogs;

use App\Models\User;
use App\Models\WaterLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaterLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_water_log_and_returns_day_total(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson('/api/v1/water-logs', [
            'liters' => 0.25,
            'date'   => '2026-04-19',
            'time'   => '08:00',
        ])->assertCreated();

        $this->assertSame('0.25', $first->json('log.liters'));
        $this->assertEquals(0.25, $first->json('day_total'));

        $second = $this->actingAs($user)->postJson('/api/v1/water-logs', [
            'liters' => 0.5,
            'date'   => '2026-04-19',
        ])->assertCreated();

        $this->assertEquals(0.75, $second->json('day_total'));

        $this->assertDatabaseCount('water_logs', 2);
    }

    public function test_index_filters_by_date_and_returns_today_total(): void
    {
        $user = User::factory()->create();
        $today = now()->toDateString();

        WaterLog::create(['user_id' => $user->id, 'date' => $today, 'liters' => 0.5]);
        WaterLog::create(['user_id' => $user->id, 'date' => $today, 'liters' => 0.3]);
        WaterLog::create(['user_id' => $user->id, 'date' => '2026-04-10', 'liters' => 1.0]);

        $filtered = $this->actingAs($user)->getJson("/api/v1/water-logs?date={$today}")->assertOk();
        $this->assertCount(2, $filtered->json('data'));
        $this->assertEquals(0.8, $filtered->json('today_total'));

        $all = $this->actingAs($user)->getJson('/api/v1/water-logs')->assertOk();
        $this->assertCount(3, $all->json('data'));
    }

    public function test_destroy_removes_own_log(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)->postJson('/api/v1/water-logs', [
            'liters' => 0.25,
        ])->assertCreated();

        $id = $create->json('log.id');

        $this->actingAs($user)->deleteJson("/api/v1/water-logs/{$id}")->assertOk();
        $this->assertDatabaseMissing('water_logs', ['id' => $id]);
    }

    public function test_destroy_cannot_remove_other_users_log(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $log = WaterLog::create([
            'user_id' => $owner->id,
            'date'    => now()->toDateString(),
            'liters'  => 0.5,
        ]);

        $this->actingAs($stranger)->deleteJson("/api/v1/water-logs/{$log->id}")->assertNotFound();
        $this->assertDatabaseHas('water_logs', ['id' => $log->id]);
    }

    public function test_store_validates_liters_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/water-logs', [
            'liters' => 0,
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/v1/water-logs', [
            'liters' => 20,
        ])->assertUnprocessable();
    }
}
