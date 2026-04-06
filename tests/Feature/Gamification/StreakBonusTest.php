<?php

namespace Tests\Feature\Gamification;

use App\Application\UseCases\Xp\AddXpUseCase;
use App\Models\User;
use App\Models\XpTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakBonusTest extends TestCase
{
    use RefreshDatabase;

    private AddXpUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new AddXpUseCase();
    }

    public function test_three_consecutive_days_progressive_streak_bonus(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        $today = Carbon::now()->startOfDay()->addHours(10);

        // Pre-seed day 1 (2 days ago) — standalone, no streak bonus
        XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'amount' => 10,
            'reason' => 'login_daily',
            'reference_id' => 'day-s1',
            'created_at' => $today->copy()->subDays(2),
        ]);

        // Pre-seed day 2 (yesterday) — streak = 1 (day 1 only), bonus = 0
        XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'amount' => 10,
            'reason' => 'login_daily',
            'reference_id' => 'day-s2',
            'created_at' => $today->copy()->subDays(1),
        ]);

        // Day 3 (today) via AddXpUseCase — streak = 2 (day 1 + day 2), bonus = min(2-1, 10)*5 = 5
        $tx3 = $this->useCase->execute($user, 10, 'login_daily', null, 'day-s3', $today);
        $this->assertNotNull($tx3);
        $this->assertEquals(15, $tx3->amount); // 10 base + 5 streak bonus
        $this->assertEquals(5, $tx3->meta['streak_bonus']);
        $this->assertEquals(2, $tx3->meta['streak_day']);
    }

    public function test_streak_broken_by_gap_day(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        $baseTime = Carbon::now()->startOfDay()->addHours(10);

        // Day 1 (3 days ago)
        $this->useCase->execute($user, 10, 'login_daily', null, 'gap-d1', $baseTime->copy()->subDays(3));

        // Day 2 (2 days ago) — has streak
        $this->useCase->execute($user, 10, 'login_daily', null, 'gap-d2', $baseTime->copy()->subDays(2));

        // Skip day 3 (yesterday) — gap!

        // Day 4 (today) — streak should be broken, bonus = 0
        $tx = $this->useCase->execute($user, 10, 'login_daily', null, 'gap-d4', $baseTime);
        $this->assertNotNull($tx);
        $this->assertEquals(10, $tx->amount); // No streak bonus
        $this->assertEquals(0, $tx->meta['streak_bonus']);
    }

    public function test_streak_bonus_capped_at_50(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        $baseTime = Carbon::now()->startOfDay()->addHours(10);

        // Create 11 days of consecutive activity (days -11 through -1)
        for ($i = 11; $i >= 1; $i--) {
            XpTransaction::factory()->create([
                'user_uuid' => $user->uuid,
                'amount' => 10,
                'reason' => 'login_daily',
                'reference_id' => "cap-d{$i}",
                'created_at' => $baseTime->copy()->subDays($i),
            ]);
        }

        // Day 12 (today) — streak = 11, bonus = min(11-1, 10) * 5 = 50 (capped)
        $tx = $this->useCase->execute($user, 10, 'login_daily', null, 'cap-d12', $baseTime);
        $this->assertNotNull($tx);
        $this->assertEquals(60, $tx->amount); // 10 base + 50 streak bonus (capped)
        $this->assertEquals(50, $tx->meta['streak_bonus']);
    }

    public function test_non_eligible_reason_gets_no_streak_bonus(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        $baseTime = Carbon::now()->startOfDay()->addHours(10);

        // Build a streak with login_daily
        XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'amount' => 10,
            'reason' => 'login_daily',
            'reference_id' => 'ne-d1',
            'created_at' => $baseTime->copy()->subDays(1),
        ]);

        // goal_completed is not streak-eligible
        $tx = $this->useCase->execute($user, 100, 'goal_completed', 'goal', 'ne-goal', $baseTime);
        $this->assertNotNull($tx);
        $this->assertEquals(100, $tx->amount);
        $this->assertEquals(0, $tx->meta['streak_bonus']);
    }
}
