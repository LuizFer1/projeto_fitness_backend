<?php

namespace Tests\Feature\Gamification;

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AchievementSeeder::class);
        $this->user = User::factory()->create();
    }

    public function test_achievements_endpoint_returns_all_14_achievements(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/gamification/achievements');

        $response->assertOk();
        $response->assertJsonCount(14, 'data');
    }

    public function test_achievements_shows_unlocked_status(): void
    {
        $achievements = Achievement::whereIn('key', ['first_goal_created', 'first_checkin', 'streak_3_days'])
            ->get();

        foreach ($achievements as $achievement) {
            UserAchievement::create([
                'user_uuid' => $this->user->uuid,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/gamification/achievements');

        $response->assertOk();

        $data = $response->json('data');
        $unlocked = collect($data)->where('is_unlocked', true);
        $locked = collect($data)->where('is_unlocked', false);

        $this->assertCount(3, $unlocked);
        $this->assertCount(11, $locked);
    }

    public function test_unlocked_achievements_appear_first(): void
    {
        $achievement = Achievement::where('key', 'first_goal_created')->first();

        UserAchievement::create([
            'user_uuid' => $this->user->uuid,
            'achievement_id' => $achievement->id,
            'unlocked_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/gamification/achievements');

        $data = $response->json('data');

        $this->assertTrue($data[0]['is_unlocked']);
    }

    public function test_achievement_resource_structure(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/gamification/achievements');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'key',
                    'title',
                    'description',
                    'icon',
                    'category',
                    'xp_reward',
                    'is_unlocked',
                    'unlocked_at',
                ],
            ],
        ]);
    }

    public function test_locked_achievements_have_null_unlocked_at(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/gamification/achievements');

        $data = $response->json('data');

        foreach ($data as $item) {
            $this->assertFalse($item['is_unlocked']);
            $this->assertNull($item['unlocked_at']);
        }
    }

    public function test_achievements_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/gamification/achievements');

        $response->assertUnauthorized();
    }

    public function test_other_users_unlocks_not_visible(): void
    {
        $otherUser = User::factory()->create();
        $achievement = Achievement::where('key', 'first_goal_created')->first();

        UserAchievement::create([
            'user_uuid' => $otherUser->uuid,
            'achievement_id' => $achievement->id,
            'unlocked_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/gamification/achievements');

        $data = $response->json('data');
        $unlocked = collect($data)->where('is_unlocked', true);

        $this->assertCount(0, $unlocked);
    }
}
