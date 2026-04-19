<?php

namespace Tests\Feature\Quests;

use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Quest::query()->delete();
    }

    public function test_index_returns_active_quests(): void
    {
        $user = User::factory()->create();

        Quest::create([
            'slug' => 'active_q', 'name' => 'Active', 'type' => 'basic',
            'periodicity' => 'weekly', 'condition_type' => 'workouts_period',
            'condition_value' => 3, 'xp_reward' => 50, 'is_active' => true,
        ]);
        Quest::create([
            'slug' => 'inactive_q', 'name' => 'Inactive', 'type' => 'basic',
            'periodicity' => 'weekly', 'condition_type' => 'workouts_period',
            'condition_value' => 3, 'xp_reward' => 50, 'is_active' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/quests')->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains('active_q', $slugs);
        $this->assertNotContains('inactive_q', $slugs);
    }

    public function test_mine_returns_progress_shape_with_not_started_default(): void
    {
        $user = User::factory()->create();

        Quest::create([
            'slug' => 'q1', 'name' => 'Quest 1', 'type' => 'basic',
            'periodicity' => 'weekly', 'condition_type' => 'workouts_period',
            'condition_value' => 5, 'xp_reward' => 100, 'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/quests/mine')->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('not_started', $data[0]['status']);
        $this->assertSame(0, $data[0]['progress']);
        $this->assertSame(5, $data[0]['target']);
    }
}
