<?php

namespace Tests\Feature\Admin;

use App\Models\Achievement;
use App\Models\Exercise;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_create_update_delete_badge(): void
    {
        $payload = [
            'slug' => 'test_badge', 'name' => 'Test', 'category' => 'workout',
            'condition_type' => 'total_workouts', 'condition_value' => 10,
        ];

        $create = $this->actingAs($this->admin)->postJson('/api/v1/admin/badges', $payload)->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin)->putJson("/api/v1/admin/badges/{$id}", ['name' => 'Updated'])
            ->assertOk()->assertJsonPath('data.name', 'Updated');

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/badges/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('achievements', ['id' => $id]);
    }

    public function test_admin_can_create_quest(): void
    {
        $payload = [
            'slug' => 'test_quest', 'name' => 'Test Quest', 'type' => 'basic',
            'periodicity' => 'weekly', 'condition_type' => 'workouts_period',
            'condition_value' => 3, 'xp_reward' => 50,
        ];

        $this->actingAs($this->admin)->postJson('/api/v1/admin/quests', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'test_quest');
    }

    public function test_admin_can_bulk_import_exercises(): void
    {
        $payload = [
            'exercises' => [
                ['name' => 'Push up', 'muscle_group' => 'chest', 'category' => 'strength'],
                ['name' => 'Squat',   'muscle_group' => 'legs',  'category' => 'strength'],
            ],
        ];

        $this->actingAs($this->admin)->postJson('/api/v1/admin/exercises/bulk', $payload)
            ->assertCreated()
            ->assertJsonPath('count', 2);

        $this->assertDatabaseHas('exercises', ['name' => 'Push up']);
        $this->assertDatabaseHas('exercises', ['name' => 'Squat']);
    }

    public function test_badge_store_validates_category(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/badges', [
            'slug' => 'x', 'name' => 'X', 'category' => 'invalid',
            'condition_type' => 'total_workouts', 'condition_value' => 1,
        ])->assertStatus(422);
    }
}
