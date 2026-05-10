<?php

namespace Tests\Feature\Follow;

use App\Models\Follower;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $username, string $visibility = 'public'): User
    {
        return User::factory()->create([
            'username' => $username,
            'is_active' => true,
            'profile_visibility' => $visibility,
        ]);
    }

    public function test_follow_public_profile_is_auto_accepted(): void
    {
        $me = $this->makeUser('me');
        $target = $this->makeUser('target', 'public');

        $response = $this->actingAs($me)
            ->postJson('/api/v1/users/target/follow');

        $response->assertCreated()->assertJsonPath('status', 'accepted');
        $this->assertDatabaseHas('followers', [
            'follower_id' => $me->id,
            'followee_id' => $target->id,
            'status' => 'accepted',
        ]);
    }

    public function test_follow_private_profile_stays_pending(): void
    {
        $me = $this->makeUser('me2');
        $target = $this->makeUser('target2', 'private');

        $response = $this->actingAs($me)
            ->postJson('/api/v1/users/target2/follow');

        $response->assertCreated()->assertJsonPath('status', 'pending');
        $this->assertDatabaseHas('followers', [
            'follower_id' => $me->id,
            'followee_id' => $target->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_follow_self(): void
    {
        $me = $this->makeUser('selfuser');

        $this->actingAs($me)
            ->postJson('/api/v1/users/selfuser/follow')
            ->assertStatus(422);
    }

    public function test_cannot_follow_twice(): void
    {
        $me = $this->makeUser('me3');
        $target = $this->makeUser('target3', 'public');

        $this->actingAs($me)->postJson('/api/v1/users/target3/follow')->assertCreated();
        $this->actingAs($me)->postJson('/api/v1/users/target3/follow')->assertStatus(422);
    }

    public function test_unfollow_removes_record(): void
    {
        $me = $this->makeUser('me4');
        $target = $this->makeUser('target4', 'public');

        Follower::create([
            'follower_id' => $me->id,
            'followee_id' => $target->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->actingAs($me)
            ->deleteJson('/api/v1/users/target4/follow')
            ->assertOk();

        $this->assertDatabaseMissing('followers', [
            'follower_id' => $me->id,
            'followee_id' => $target->id,
        ]);
    }

    public function test_requests_lists_pending_incoming(): void
    {
        $me = $this->makeUser('meprivate', 'private');
        $sender = $this->makeUser('sender1');

        Follower::create([
            'follower_id' => $sender->id,
            'followee_id' => $me->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($me)->getJson('/api/v1/follow-requests')->assertOk();

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_accept_follow_request(): void
    {
        $me = $this->makeUser('me5', 'private');
        $sender = $this->makeUser('sender5');

        $follow = Follower::create([
            'follower_id' => $sender->id,
            'followee_id' => $me->id,
            'status' => 'pending',
        ]);

        $this->actingAs($me)
            ->postJson("/api/v1/follow-requests/{$follow->id}/accept")
            ->assertOk();

        $this->assertDatabaseHas('followers', ['id' => $follow->id, 'status' => 'accepted']);
    }

    public function test_reject_follow_request(): void
    {
        $me = $this->makeUser('me6', 'private');
        $sender = $this->makeUser('sender6');

        $follow = Follower::create([
            'follower_id' => $sender->id,
            'followee_id' => $me->id,
            'status' => 'pending',
        ]);

        $this->actingAs($me)
            ->postJson("/api/v1/follow-requests/{$follow->id}/reject")
            ->assertOk();

        $this->assertDatabaseMissing('followers', ['id' => $follow->id]);
    }

    public function test_followers_list_returns_accepted_followers(): void
    {
        $target = $this->makeUser('target7', 'public');
        $follower = $this->makeUser('follower7');
        $viewer = $this->makeUser('viewer7');

        Follower::create([
            'follower_id' => $follower->id,
            'followee_id' => $target->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/users/target7/followers')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_private_followers_blocked_for_non_followers(): void
    {
        $target = $this->makeUser('target8', 'private');
        $viewer = $this->makeUser('viewer8');

        $this->actingAs($viewer)
            ->getJson('/api/v1/users/target8/followers')
            ->assertStatus(403);
    }
}
