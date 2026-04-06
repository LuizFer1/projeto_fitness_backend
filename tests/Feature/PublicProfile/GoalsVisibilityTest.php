<?php

namespace Tests\Feature\PublicProfile;

use App\Models\Friendship;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $authUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authUser = User::factory()->create();
    }

    // ── Visibility rules ──

    public function test_public_goal_visible_to_any_authenticated_user(): void
    {
        $target = User::factory()->create(['username' => 'goaluser']);
        Goal::factory()->public()->create([
            'user_uuid' => $target->uuid,
            'title' => 'Run 5K',
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/goaluser/goals');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Run 5K');
    }

    public function test_private_goal_never_visible_to_stranger(): void
    {
        $target = User::factory()->create(['username' => 'privategoals']);
        Goal::factory()->private()->create([
            'user_uuid' => $target->uuid,
            'title' => 'Secret Goal',
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/privategoals/goals');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_private_goal_never_visible_to_friend(): void
    {
        $target = User::factory()->create(['username' => 'privfriend']);

        Friendship::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'friend_uuid' => $target->uuid,
            'status' => 'accepted',
        ]);

        Goal::factory()->private()->create([
            'user_uuid' => $target->uuid,
            'title' => 'Private Even From Friends',
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/privfriend/goals');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_friends_goal_visible_to_friend(): void
    {
        $target = User::factory()->create(['username' => 'friendgoal']);

        Friendship::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'friend_uuid' => $target->uuid,
            'status' => 'accepted',
        ]);

        Goal::factory()->friends()->create([
            'user_uuid' => $target->uuid,
            'title' => 'Friends Only Goal',
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/friendgoal/goals');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Friends Only Goal');
    }

    public function test_friends_goal_not_visible_to_stranger(): void
    {
        $target = User::factory()->create(['username' => 'notfriend']);
        Goal::factory()->friends()->create([
            'user_uuid' => $target->uuid,
            'title' => 'Friends Only',
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/notfriend/goals');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_friends_goal_not_visible_to_pending_friend(): void
    {
        $target = User::factory()->create(['username' => 'pendingfr']);

        Friendship::factory()->pending()->create([
            'user_uuid' => $this->authUser->uuid,
            'friend_uuid' => $target->uuid,
        ]);

        Goal::factory()->friends()->create([
            'user_uuid' => $target->uuid,
            'title' => 'Pending Friend Goal',
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/pendingfr/goals');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // ── Archived goals excluded ──

    public function test_archived_goals_not_visible(): void
    {
        $target = User::factory()->create(['username' => 'archivedg']);
        Goal::factory()->public()->archived()->create([
            'user_uuid' => $target->uuid,
            'title' => 'Archived Goal',
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/archivedg/goals');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // ── Mixed visibility ──

    public function test_mixed_visibility_returns_only_visible_goals(): void
    {
        $target = User::factory()->create(['username' => 'mixedgoals']);

        Goal::factory()->public()->create(['user_uuid' => $target->uuid, 'title' => 'Public Goal']);
        Goal::factory()->private()->create(['user_uuid' => $target->uuid, 'title' => 'Private Goal']);
        Goal::factory()->friends()->create(['user_uuid' => $target->uuid, 'title' => 'Friends Goal']);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/mixedgoals/goals');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Public Goal');
    }

    public function test_mixed_visibility_friend_sees_public_and_friends(): void
    {
        $target = User::factory()->create(['username' => 'mixedfriend']);

        Friendship::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'friend_uuid' => $target->uuid,
            'status' => 'accepted',
        ]);

        Goal::factory()->public()->create(['user_uuid' => $target->uuid, 'title' => 'Public Goal']);
        Goal::factory()->private()->create(['user_uuid' => $target->uuid, 'title' => 'Private Goal']);
        Goal::factory()->friends()->create(['user_uuid' => $target->uuid, 'title' => 'Friends Goal']);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/mixedfriend/goals');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    // ── Block / inactive / auth ──

    public function test_blocked_by_target_returns_404(): void
    {
        $target = User::factory()->create(['username' => 'blockgoals']);

        Friendship::factory()->blocked()->create([
            'user_uuid' => $target->uuid,
            'friend_uuid' => $this->authUser->uuid,
        ]);

        Goal::factory()->public()->create(['user_uuid' => $target->uuid]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/blockgoals/goals');

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_auth_blocked_target_returns_404(): void
    {
        $target = User::factory()->create(['username' => 'iblockedgoal']);

        Friendship::factory()->blocked()->create([
            'user_uuid' => $this->authUser->uuid,
            'friend_uuid' => $target->uuid,
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/iblockedgoal/goals');

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_inactive_user_returns_404(): void
    {
        User::factory()->create([
            'username' => 'inactivegoal',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/inactivegoal/goals');

        $response->assertNotFound();
    }

    public function test_nonexistent_user_returns_404(): void
    {
        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/noonehere/goals');

        $response->assertNotFound();
    }

    public function test_unauthenticated_returns_401(): void
    {
        User::factory()->create(['username' => 'someonegoal']);

        $response = $this->getJson('/api/v1/users/someonegoal/goals');

        $response->assertUnauthorized();
    }

    // ── Response structure ──

    public function test_goal_response_structure(): void
    {
        $target = User::factory()->create(['username' => 'structgoal']);
        Goal::factory()->public()->create(['user_uuid' => $target->uuid]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/structgoal/goals');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'title', 'type', 'target_value', 'current_value',
                    'unit', 'deadline', 'visibility', 'status',
                    'completed_at', 'created_at',
                ],
            ],
        ]);
    }

    public function test_returns_empty_when_no_goals(): void
    {
        User::factory()->create(['username' => 'nogoals']);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/nogoals/goals');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}
