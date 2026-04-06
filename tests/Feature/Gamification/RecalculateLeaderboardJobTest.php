<?php

namespace Tests\Feature\Gamification;

use App\Events\LeaderboardPositionChanged;
use App\Jobs\RecalculateLeaderboardJob;
use App\Models\LeaderboardSnapshot;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RecalculateLeaderboardJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_populates_leaderboard_snapshots_for_alltime(): void
    {
        $users = collect();
        for ($i = 0; $i < 5; $i++) {
            $users->push(User::factory()->create([
                'xp_points' => ($i + 1) * 100,
                'level' => 1,
            ]));
        }

        (new RecalculateLeaderboardJob)->handle();

        $snapshots = LeaderboardSnapshot::where('period', 'alltime')
            ->where('period_key', 'global')
            ->orderBy('rank')
            ->get();

        $this->assertCount(5, $snapshots);
        $this->assertEquals($users[4]->uuid, $snapshots[0]->user_uuid); // 500xp = rank 1
        $this->assertEquals(1, $snapshots[0]->rank);
        $this->assertEquals(500, $snapshots[0]->xp_points);
        $this->assertEquals($users[0]->uuid, $snapshots[4]->user_uuid); // 100xp = rank 5
        $this->assertEquals(5, $snapshots[4]->rank);
    }

    public function test_populates_weekly_leaderboard_from_xp_transactions(): void
    {
        $user1 = User::factory()->create(['xp_points' => 0, 'level' => 1]);
        $user2 = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        XpTransaction::factory()->create([
            'user_uuid' => $user1->uuid,
            'amount' => 200,
            'reason' => 'goal_completed',
            'reference_id' => 'g1',
            'created_at' => now(),
        ]);
        XpTransaction::factory()->create([
            'user_uuid' => $user2->uuid,
            'amount' => 300,
            'reason' => 'goal_completed',
            'reference_id' => 'g2',
            'created_at' => now(),
        ]);
        // Old transaction outside this week
        XpTransaction::factory()->create([
            'user_uuid' => $user1->uuid,
            'amount' => 9999,
            'reason' => 'goal_completed',
            'reference_id' => 'old',
            'created_at' => now()->subWeeks(2),
        ]);

        (new RecalculateLeaderboardJob)->handle();

        $periodKey = now()->format('o-\\WW');
        $snapshots = LeaderboardSnapshot::where('period', 'weekly')
            ->where('period_key', $periodKey)
            ->orderBy('rank')
            ->get();

        $this->assertCount(2, $snapshots);
        $this->assertEquals($user2->uuid, $snapshots[0]->user_uuid); // 300 > 200
        $this->assertEquals(1, $snapshots[0]->rank);
        $this->assertEquals(300, $snapshots[0]->xp_points);
    }

    public function test_populates_monthly_leaderboard_from_xp_transactions(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'amount' => 150,
            'reason' => 'login_daily',
            'reference_id' => 'd1',
            'created_at' => now(),
        ]);

        (new RecalculateLeaderboardJob)->handle();

        $periodKey = now()->format('Y-m');
        $snapshot = LeaderboardSnapshot::where('period', 'monthly')
            ->where('period_key', $periodKey)
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertEquals($user->uuid, $snapshot->user_uuid);
        $this->assertEquals(1, $snapshot->rank);
        $this->assertEquals(150, $snapshot->xp_points);
    }

    public function test_caches_leaderboard_data(): void
    {
        $user = User::factory()->create(['xp_points' => 500, 'level' => 2]);

        (new RecalculateLeaderboardJob)->handle();

        $weeklyKey = 'leaderboard:weekly:' . now()->format('o-\\WW');
        $monthlyKey = 'leaderboard:monthly:' . now()->format('Y-m');
        $alltimeKey = 'leaderboard:alltime:global';

        // Alltime should have data (user has xp_points > 0)
        $this->assertTrue(Cache::has($alltimeKey));
        $payload = Cache::get($alltimeKey);
        $this->assertCount(1, $payload);
        $this->assertEquals(1, $payload[0]['rank']);
        $this->assertEquals($user->uuid, $payload[0]['user_uuid']);
        $this->assertEquals(500, $payload[0]['xp_points']);
    }

    public function test_dispatches_leaderboard_position_changed_for_new_top_10(): void
    {
        Event::fake([LeaderboardPositionChanged::class]);

        // Create 5 users with xp_points > 0 and transactions this week
        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create([
                'xp_points' => ($i + 1) * 100,
                'level' => 1,
            ]);
            XpTransaction::factory()->create([
                'user_uuid' => $user->uuid,
                'amount' => ($i + 1) * 100,
                'reason' => 'goal_completed',
                'reference_id' => "goal-dispatch-{$i}",
                'created_at' => now(),
            ]);
        }

        // First run — all users are new to top 10 across all 3 periods
        (new RecalculateLeaderboardJob)->handle();

        Event::assertDispatched(LeaderboardPositionChanged::class, 15); // 5 users × 3 periods
    }

    public function test_does_not_dispatch_for_users_already_in_top_10(): void
    {
        Event::fake([LeaderboardPositionChanged::class]);

        $user = User::factory()->create(['xp_points' => 500, 'level' => 2]);

        // Pre-populate snapshot so user is already in top 10
        LeaderboardSnapshot::create([
            'user_uuid' => $user->uuid,
            'period' => 'alltime',
            'period_key' => 'global',
            'rank' => 1,
            'xp_points' => 500,
            'calculated_at' => now()->subHour(),
        ]);

        (new RecalculateLeaderboardJob)->handle();

        // Should NOT dispatch for alltime since user was already rank 1
        Event::assertNotDispatched(LeaderboardPositionChanged::class, function ($e) {
            return $e->oldRank === 1;
        });
    }

    public function test_handles_50_users_with_varied_xp(): void
    {
        $users = collect();
        for ($i = 0; $i < 50; $i++) {
            $user = User::factory()->create([
                'xp_points' => ($i + 1) * 10,
                'level' => 1,
            ]);
            $users->push($user);

            // Add some xp_transactions for weekly/monthly
            XpTransaction::factory()->create([
                'user_uuid' => $user->uuid,
                'amount' => ($i + 1) * 5,
                'reason' => 'goal_completed',
                'reference_id' => "goal-{$i}",
                'created_at' => now(),
            ]);
        }

        (new RecalculateLeaderboardJob)->handle();

        $weeklyKey = 'leaderboard:weekly:' . now()->format('o-\\WW');
        $monthlyKey = 'leaderboard:monthly:' . now()->format('Y-m');
        $alltimeKey = 'leaderboard:alltime:global';

        // All 3 caches should be populated
        $this->assertTrue(Cache::has($weeklyKey));
        $this->assertTrue(Cache::has($monthlyKey));
        $this->assertTrue(Cache::has($alltimeKey));

        // Check alltime snapshots
        $alltime = LeaderboardSnapshot::where('period', 'alltime')
            ->where('period_key', 'global')
            ->orderBy('rank')
            ->get();

        $this->assertCount(50, $alltime);
        $this->assertEquals(1, $alltime[0]->rank);
        $this->assertEquals(500, $alltime[0]->xp_points); // user 50 has 500xp

        // Check weekly snapshots
        $weekly = LeaderboardSnapshot::where('period', 'weekly')
            ->where('period_key', now()->format('o-\\WW'))
            ->orderBy('rank')
            ->get();

        $this->assertCount(50, $weekly);
        $this->assertEquals(1, $weekly[0]->rank);
        $this->assertEquals(250, $weekly[0]->xp_points); // user 50 has 250 xp in transactions
    }

    public function test_limits_to_100_entries(): void
    {
        for ($i = 0; $i < 110; $i++) {
            User::factory()->create([
                'xp_points' => ($i + 1) * 10,
                'level' => 1,
            ]);
        }

        (new RecalculateLeaderboardJob)->handle();

        $alltime = LeaderboardSnapshot::where('period', 'alltime')
            ->where('period_key', 'global')
            ->count();

        $this->assertEquals(100, $alltime);
    }
}
