<?php

namespace Tests\Feature\Gamification;

use App\Jobs\RecalculateLeaderboardJob;
use App\Models\LeaderboardSnapshot;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_flow_seed_users_run_job_verify_weekly_endpoint(): void
    {
        $users = collect();
        for ($i = 0; $i < 20; $i++) {
            $user = User::factory()->create([
                'xp_points' => ($i + 1) * 50,
                'level' => 1,
            ]);
            $users->push($user);

            XpTransaction::factory()->create([
                'user_uuid' => $user->uuid,
                'amount' => ($i + 1) * 30,
                'reason' => 'goal_completed',
                'reference_id' => "lb-goal-{$i}",
                'created_at' => now(),
            ]);
        }

        (new RecalculateLeaderboardJob)->handle();

        // Verify snapshots populated
        $weeklyKey = now()->format('o-\\WW');
        $weeklySnapshots = LeaderboardSnapshot::where('period', 'weekly')
            ->where('period_key', $weeklyKey)
            ->orderBy('rank')
            ->get();

        $this->assertCount(20, $weeklySnapshots);
        $this->assertEquals(1, $weeklySnapshots[0]->rank);
        $this->assertEquals(600, $weeklySnapshots[0]->xp_points); // user 20: 20*30 = 600

        // Verify cache populated
        $this->assertTrue(Cache::has("leaderboard:weekly:{$weeklyKey}"));

        // Verify endpoint returns correct data
        $authUser = $users->last(); // highest XP user
        $response = $this->actingAs($authUser)->getJson('/api/v1/gamification/leaderboard/weekly');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(20, $data);
        $this->assertEquals(1, $data[0]['rank']);
        $this->assertEquals(600, $data[0]['xp_points']);
        $response->assertJsonPath('meta.period', 'weekly');
        $response->assertJsonPath('meta.current_user_rank', 1);
    }

    public function test_alltime_leaderboard_uses_user_xp_points(): void
    {
        $user1 = User::factory()->create(['xp_points' => 1000, 'level' => 3]);
        $user2 = User::factory()->create(['xp_points' => 500, 'level' => 2]);
        $user3 = User::factory()->create(['xp_points' => 2000, 'level' => 4]);

        (new RecalculateLeaderboardJob)->handle();

        $response = $this->actingAs($user2)->getJson('/api/v1/gamification/leaderboard/alltime');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals(2000, $data[0]['xp_points']); // user3 first
        $this->assertEquals(1000, $data[1]['xp_points']); // user1 second
        $this->assertEquals(500, $data[2]['xp_points']);   // user2 third
        $response->assertJsonPath('meta.current_user_rank', 3);
    }

    public function test_leaderboard_capped_at_100_entries(): void
    {
        for ($i = 0; $i < 110; $i++) {
            User::factory()->create([
                'xp_points' => ($i + 1) * 10,
                'level' => 1,
            ]);
        }

        (new RecalculateLeaderboardJob)->handle();

        $alltimeKey = 'leaderboard:alltime:global';
        $cached = Cache::get($alltimeKey);
        $this->assertCount(100, $cached);

        $snapshots = LeaderboardSnapshot::where('period', 'alltime')
            ->where('period_key', 'global')
            ->count();
        $this->assertEquals(100, $snapshots);
    }
}
