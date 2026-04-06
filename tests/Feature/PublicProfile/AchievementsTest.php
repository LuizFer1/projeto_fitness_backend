<?php

namespace Tests\Feature\PublicProfile;

use App\Models\Achievement;
use App\Models\Friendship;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementsTest extends TestCase
{
    use RefreshDatabase;

    private User $authUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authUser = User::factory()->create();
    }

    public function test_returns_unlocked_achievements_for_active_user(): void
    {
        $target = User::factory()->create(['username' => 'achiever']);

        $achievement1 = Achievement::factory()->create(['title' => 'First Goal']);
        $achievement2 = Achievement::factory()->create(['title' => 'Streak Master']);

        UserAchievement::factory()->create([
            'user_uuid'      => $target->uuid,
            'achievement_id' => $achievement1->id,
            'unlocked_at'    => now()->subDays(2),
        ]);
        UserAchievement::factory()->create([
            'user_uuid'      => $target->uuid,
            'achievement_id' => $achievement2->id,
            'unlocked_at'    => now()->subDay(),
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/achiever/achievements');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'key', 'title', 'description', 'icon', 'category', 'xp_reward', 'unlocked_at'],
            ],
        ]);
        // Should NOT include is_unlocked field
        $response->assertJsonMissingPath('data.0.is_unlocked');
    }

    public function test_achievements_ordered_by_unlocked_at_desc(): void
    {
        $target = User::factory()->create(['username' => 'ordered']);

        $older = Achievement::factory()->create(['title' => 'Older']);
        $newer = Achievement::factory()->create(['title' => 'Newer']);

        UserAchievement::factory()->create([
            'user_uuid'      => $target->uuid,
            'achievement_id' => $older->id,
            'unlocked_at'    => now()->subDays(5),
        ]);
        UserAchievement::factory()->create([
            'user_uuid'      => $target->uuid,
            'achievement_id' => $newer->id,
            'unlocked_at'    => now()->subDay(),
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/ordered/achievements');

        $response->assertOk();
        $response->assertJsonPath('data.0.title', 'Newer');
        $response->assertJsonPath('data.1.title', 'Older');
    }

    public function test_blocked_by_target_returns_404(): void
    {
        $target = User::factory()->create(['username' => 'blockachieve']);

        Friendship::factory()->blocked()->create([
            'user_uuid'   => $target->uuid,
            'friend_uuid' => $this->authUser->uuid,
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/blockachieve/achievements');

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_auth_blocked_target_returns_404(): void
    {
        $target = User::factory()->create(['username' => 'iblocked']);

        Friendship::factory()->blocked()->create([
            'user_uuid'   => $this->authUser->uuid,
            'friend_uuid' => $target->uuid,
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/iblocked/achievements');

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_inactive_user_returns_404(): void
    {
        User::factory()->create([
            'username'  => 'inactiveach',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/inactiveach/achievements');

        $response->assertNotFound();
    }

    public function test_nonexistent_user_returns_404(): void
    {
        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/ghostuser/achievements');

        $response->assertNotFound();
    }

    public function test_unauthenticated_returns_401(): void
    {
        User::factory()->create(['username' => 'someone']);

        $response = $this->getJson('/api/v1/users/someone/achievements');

        $response->assertUnauthorized();
    }

    public function test_returns_empty_when_no_achievements(): void
    {
        User::factory()->create(['username' => 'noachs']);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/noachs/achievements');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}
