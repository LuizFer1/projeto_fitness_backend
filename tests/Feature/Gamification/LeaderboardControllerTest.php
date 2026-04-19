<?php

namespace Tests\Feature\Gamification;

use App\Models\Friendship;
use App\Models\User;
use App\Models\UserGamification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function gamification(User $user, array $attrs = []): UserGamification
    {
        return UserGamification::create(array_merge([
            'user_id' => $user->id,
            'xp_total' => 0, 'current_level' => 1, 'xp_to_next' => 200,
            'current_streak' => 0, 'max_streak' => 0,
            'current_week_xp' => 0, 'current_month_xp' => 0,
            'total_workouts' => 0, 'total_water_days' => 0,
        ], $attrs));
    }

    public function test_friends_leaderboard_includes_self_and_friends(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();

        $this->gamification($me, ['xp_total' => 300]);
        $this->gamification($friend, ['xp_total' => 500]);
        $this->gamification($stranger, ['xp_total' => 1000]);

        Friendship::create([
            'requester_id' => $me->id,
            'addressee_id' => $friend->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($me)->getJson('/api/v1/gamification/leaderboard/friends')->assertOk();

        $userIds = collect($response->json('rankings'))->pluck('user_id')->all();
        $this->assertEqualsCanonicalizing([$me->id, $friend->id], $userIds);
        $this->assertNotContains($stranger->id, $userIds);

        $this->assertSame(1, $response->json('rankings.0.position'));
        $this->assertSame($friend->id, $response->json('rankings.0.user_id'));
    }

    public function test_weekly_leaderboard_returns_my_position(): void
    {
        $me = User::factory()->create();
        $top = User::factory()->create();

        $this->gamification($me, ['current_week_xp' => 100, 'xp_total' => 100]);
        $this->gamification($top, ['current_week_xp' => 500, 'xp_total' => 500]);

        $response = $this->actingAs($me)->getJson('/api/v1/gamification/leaderboard/weekly')->assertOk();

        $this->assertSame(2, $response->json('my_position.position'));
        $this->assertSame(100, $response->json('my_position.period_xp'));
    }
}
