<?php

namespace Tests\Unit\Services\Gamification;

use App\Models\User;
use App\Models\XpTransaction;
use App\Services\Gamification\StreakCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function createXp(string $reason, Carbon $at): void
    {
        XpTransaction::factory()->create([
            'user_uuid' => $this->user->uuid,
            'reason' => $reason,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    // ── currentStreak tests ──

    public function test_currentStreak_returns_0_when_no_transactions(): void
    {
        $this->assertSame(0, StreakCalculator::currentStreak($this->user));
    }

    public function test_currentStreak_returns_1_for_single_day_activity(): void
    {
        $now = Carbon::now('America/Sao_Paulo');
        $this->createXp('login_daily', $now);

        $this->assertSame(1, StreakCalculator::currentStreak($this->user, $now));
    }

    public function test_currentStreak_counts_consecutive_days(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');

        // 3 consecutive days
        $this->createXp('login_daily', $now->copy());
        $this->createXp('workout_done', $now->copy()->subDay());
        $this->createXp('goal_checkin', $now->copy()->subDays(2));

        $this->assertSame(3, StreakCalculator::currentStreak($this->user, $now));
    }

    public function test_currentStreak_gap_of_1_day_breaks_streak(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');

        // Today and 2 days ago (gap on yesterday)
        $this->createXp('login_daily', $now->copy());
        $this->createXp('login_daily', $now->copy()->subDays(2));

        $this->assertSame(1, StreakCalculator::currentStreak($this->user, $now));
    }

    public function test_currentStreak_multiple_events_same_day_count_as_one(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');

        // 3 events on the same day
        $this->createXp('login_daily', $now->copy()->setHour(8));
        $this->createXp('workout_done', $now->copy()->setHour(12));
        $this->createXp('goal_checkin', $now->copy()->setHour(18));

        $this->assertSame(1, StreakCalculator::currentStreak($this->user, $now));
    }

    public function test_currentStreak_ignores_non_eligible_reasons(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');

        // Only non-eligible reason today
        $this->createXp('login_daily', $now->copy());
        // Yesterday only has non-eligible reason
        XpTransaction::factory()->create([
            'user_uuid' => $this->user->uuid,
            'reason' => 'goal_completed',
            'created_at' => $now->copy()->subDay(),
            'updated_at' => $now->copy()->subDay(),
        ]);

        $this->assertSame(1, StreakCalculator::currentStreak($this->user, $now));
    }

    public function test_currentStreak_starts_from_yesterday_if_no_activity_today(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');

        // Activity yesterday and day before only
        $this->createXp('login_daily', $now->copy()->subDay());
        $this->createXp('login_daily', $now->copy()->subDays(2));

        $this->assertSame(2, StreakCalculator::currentStreak($this->user, $now));
    }

    // ── bonusFor tests ──

    public function test_bonusFor_day_1_returns_0(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');
        $this->createXp('login_daily', $now->copy());

        $this->assertSame(0, StreakCalculator::bonusFor($this->user, 'login_daily', $now));
    }

    public function test_bonusFor_day_2_returns_5(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');
        $this->createXp('login_daily', $now->copy());
        $this->createXp('login_daily', $now->copy()->subDay());

        $this->assertSame(5, StreakCalculator::bonusFor($this->user, 'login_daily', $now));
    }

    public function test_bonusFor_day_3_returns_10(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');
        $this->createXp('login_daily', $now->copy());
        $this->createXp('login_daily', $now->copy()->subDay());
        $this->createXp('login_daily', $now->copy()->subDays(2));

        $this->assertSame(10, StreakCalculator::bonusFor($this->user, 'login_daily', $now));
    }

    public function test_bonusFor_day_11_caps_at_50(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');

        for ($i = 0; $i < 11; $i++) {
            $this->createXp('login_daily', $now->copy()->subDays($i));
        }

        $this->assertSame(50, StreakCalculator::bonusFor($this->user, 'login_daily', $now));
    }

    public function test_bonusFor_day_15_still_caps_at_50(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');

        for ($i = 0; $i < 15; $i++) {
            $this->createXp('login_daily', $now->copy()->subDays($i));
        }

        $this->assertSame(50, StreakCalculator::bonusFor($this->user, 'login_daily', $now));
    }

    public function test_bonusFor_non_eligible_reason_returns_0(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');

        for ($i = 0; $i < 5; $i++) {
            $this->createXp('login_daily', $now->copy()->subDays($i));
        }

        $this->assertSame(0, StreakCalculator::bonusFor($this->user, 'goal_completed', $now));
    }

    public function test_bonusFor_works_with_workout_done_reason(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');
        $this->createXp('workout_done', $now->copy());
        $this->createXp('workout_done', $now->copy()->subDay());
        $this->createXp('workout_done', $now->copy()->subDays(2));

        $this->assertSame(10, StreakCalculator::bonusFor($this->user, 'workout_done', $now));
    }

    public function test_bonusFor_works_with_goal_checkin_reason(): void
    {
        $now = Carbon::parse('2026-04-06 10:00:00', 'America/Sao_Paulo');
        $this->createXp('goal_checkin', $now->copy());
        $this->createXp('goal_checkin', $now->copy()->subDay());

        $this->assertSame(5, StreakCalculator::bonusFor($this->user, 'goal_checkin', $now));
    }
}
