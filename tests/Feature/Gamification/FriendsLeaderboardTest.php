<?php

namespace Tests\Feature\Gamification;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FriendsLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_friends_leaderboard_returns_friends_and_self_ordered_by_xp(): void
    {
        $user = User::factory()->create(['xp_points' => 300, 'level' => 1]);

        $friends = collect();
        foreach ([500, 100, 400, 200, 50] as $xp) {
            $friend = User::factory()->create(['xp_points' => $xp, 'level' => 1]);
            Friendship::create([
                'user_uuid' => $user->uuid,
                'friend_uuid' => $friend->uuid,
                'status' => 'accepted',
            ]);
            $friends->push($friend);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/leaderboard/friends');

        $response->assertOk();
        $data = $response->json('data');

        // 5 friends + self = 6
        $this->assertCount(6, $data);

        // Ordered by xp_points desc: 500, 400, 300(self), 200, 100, 50
        $this->assertEquals(500, $data[0]['xp_points']);
        $this->assertEquals(400, $data[1]['xp_points']);
        $this->assertEquals(300, $data[2]['xp_points']);
        $this->assertEquals($user->uuid, $data[2]['user_uuid']);
        $this->assertEquals(200, $data[3]['xp_points']);
        $this->assertEquals(100, $data[4]['xp_points']);
        $this->assertEquals(50, $data[5]['xp_points']);

        // Ranks are sequential
        $this->assertEquals(1, $data[0]['rank']);
        $this->assertEquals(6, $data[5]['rank']);

        // Meta
        $response->assertJsonPath('meta.period', 'friends');
        $response->assertJsonPath('meta.period_key', $user->uuid);
    }

    public function test_friends_leaderboard_includes_self_even_with_no_friends(): void
    {
        $user = User::factory()->create(['xp_points' => 100, 'level' => 1]);

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/leaderboard/friends');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($user->uuid, $data[0]['user_uuid']);
        $this->assertEquals(100, $data[0]['xp_points']);
        $this->assertEquals(1, $data[0]['rank']);
    }

    public function test_friends_leaderboard_excludes_non_accepted_friendships(): void
    {
        $user = User::factory()->create(['xp_points' => 100, 'level' => 1]);
        $pending = User::factory()->create(['xp_points' => 999, 'level' => 1]);

        Friendship::create([
            'user_uuid' => $user->uuid,
            'friend_uuid' => $pending->uuid,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/leaderboard/friends');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_friends_leaderboard_returns_user_details(): void
    {
        $user = User::factory()->create(['xp_points' => 100, 'level' => 1]);

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/leaderboard/friends');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'rank',
                    'user_uuid',
                    'xp_points',
                    'user' => ['uuid', 'name', 'avatar_url', 'level'],
                ],
            ],
            'meta' => ['period', 'period_key'],
        ]);
    }

    public function test_friends_leaderboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/gamification/leaderboard/friends');

        $response->assertUnauthorized();
    }

    public function test_friends_leaderboard_is_cached(): void
    {
        $user = User::factory()->create(['xp_points' => 100, 'level' => 1]);

        // First request populates cache
        $this->actingAs($user)->getJson('/api/v1/gamification/leaderboard/friends');

        $cacheKey = "leaderboard:friends:{$user->uuid}";
        $this->assertTrue(Cache::has($cacheKey));
    }
}
