<?php

namespace Tests\Feature\Gamification;

use App\Jobs\RecalculateLeaderboardJob;
use App\Models\LeaderboardSnapshot;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LeaderboardEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_endpoint_returns_top_100_from_cache(): void
    {
        $user = User::factory()->create(['xp_points' => 100, 'level' => 1]);

        XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'amount' => 100,
            'reason' => 'goal_completed',
            'reference_id' => 'g1',
            'created_at' => now(),
        ]);

        (new RecalculateLeaderboardJob)->handle();

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/leaderboard/weekly');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['rank', 'user_uuid', 'xp_points'],
            ],
            'meta' => ['current_user_rank', 'period', 'period_key'],
        ]);

        $response->assertJsonPath('meta.period', 'weekly');
        $response->assertJsonPath('meta.current_user_rank', 1);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_monthly_endpoint_returns_data(): void
    {
        $user = User::factory()->create(['xp_points' => 200, 'level' => 1]);

        XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'amount' => 200,
            'reason' => 'goal_completed',
            'reference_id' => 'g2',
            'created_at' => now(),
        ]);

        (new RecalculateLeaderboardJob)->handle();

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/leaderboard/monthly');

        $response->assertOk();
        $response->assertJsonPath('meta.period', 'monthly');
        $response->assertJsonPath('meta.period_key', now()->format('Y-m'));
        $response->assertJsonPath('meta.current_user_rank', 1);
    }

    public function test_alltime_endpoint_returns_data(): void
    {
        $user = User::factory()->create(['xp_points' => 500, 'level' => 2]);

        (new RecalculateLeaderboardJob)->handle();

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/leaderboard/alltime');

        $response->assertOk();
        $response->assertJsonPath('meta.period', 'alltime');
        $response->assertJsonPath('meta.period_key', 'global');
        $response->assertJsonPath('meta.current_user_rank', 1);
        $response->assertJsonPath('data.0.xp_points', 500);
    }

    public function test_endpoint_serves_from_cache(): void
    {
        $user = User::factory()->create(['xp_points' => 100, 'level' => 1]);

        $periodKey = now()->format('o-\\WW');
        $cacheKey = "leaderboard:weekly:{$periodKey}";

        Cache::put($cacheKey, [
            [
                'rank' => 1,
                'user_uuid' => $user->uuid,
                'xp_points' => 999,
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'avatar_url' => null,
                    'level' => 1,
                ],
            ],
        ], 3600);

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/leaderboard/weekly');

        $response->assertOk();
        $response->assertJsonPath('data.0.xp_points', 999);
        $response->assertJsonPath('meta.current_user_rank', 1);
    }

    public function test_current_user_rank_null_when_not_ranked(): void
    {
        $rankedUser = User::factory()->create(['xp_points' => 500, 'level' => 2]);
        $unrankedUser = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        (new RecalculateLeaderboardJob)->handle();

        $response = $this->actingAs($unrankedUser)->getJson('/api/v1/gamification/leaderboard/alltime');

        $response->assertOk();
        $response->assertJsonPath('meta.current_user_rank', null);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/gamification/leaderboard/weekly');

        $response->assertUnauthorized();
    }

    public function test_leaderboard_returns_correct_order(): void
    {
        $users = collect();
        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create([
                'xp_points' => ($i + 1) * 100,
                'level' => 1,
            ]);
            $users->push($user);
        }

        (new RecalculateLeaderboardJob)->handle();

        $authUser = $users->first();
        $response = $this->actingAs($authUser)->getJson('/api/v1/gamification/leaderboard/alltime');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(5, $data);
        $this->assertEquals(1, $data[0]['rank']);
        $this->assertEquals(500, $data[0]['xp_points']);
        $this->assertEquals(5, $data[4]['rank']);
        $this->assertEquals(100, $data[4]['xp_points']);
        $response->assertJsonPath('meta.current_user_rank', 5);
    }
}
