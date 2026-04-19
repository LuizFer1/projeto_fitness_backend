<?php

namespace Tests\Unit\Services;

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserGamification;
use App\Models\XpTransaction;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private GamificationService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GamificationService;
        $this->user = User::factory()->create();
        UserGamification::create([
            'user_id' => $this->user->id,
            'xp_total' => 0,
            'current_level' => 1,
            'xp_to_next' => 200,
            'current_streak' => 0,
            'max_streak' => 0,
            'total_workouts' => 0,
        ]);
        $this->user->refresh();
    }

    // ── grantDailyLoginXp ─────────────────────────────────────────

    public function test_grant_daily_login_xp_creates_transaction(): void
    {
        $tx = $this->service->grantDailyLoginXp($this->user);

        $this->assertNotNull($tx);
        $this->assertEquals('daily_login', $tx->type);
        $this->assertEquals(10, $tx->xp_gained);

        $this->user->refresh();
        $this->assertEquals(10, $this->user->gamification->xp_total);
    }

    public function test_grant_daily_login_xp_only_once_per_day(): void
    {
        $this->service->grantDailyLoginXp($this->user);
        $second = $this->service->grantDailyLoginXp($this->user);

        $this->assertNull($second);
        $this->assertEquals(1, XpTransaction::where('user_id', $this->user->id)->where('type', 'daily_login')->count());
    }

    // ── grantWorkoutCompletedXp ───────────────────────────────────

    public function test_grant_workout_completed_xp_creates_transaction(): void
    {
        $tx = $this->service->grantWorkoutCompletedXp($this->user, 'workout-log-1');

        $this->assertNotNull($tx);
        $this->assertEquals('workout_completed', $tx->type);
        $this->assertEquals(30, $tx->xp_gained);

        $this->user->refresh();
        $this->assertEquals(30, $this->user->gamification->xp_total);
        $this->assertEquals(1, $this->user->gamification->total_workouts);
    }

    public function test_grant_workout_completed_xp_max_once_per_day(): void
    {
        $this->service->grantWorkoutCompletedXp($this->user, 'workout-log-1');
        $second = $this->service->grantWorkoutCompletedXp($this->user, 'workout-log-2');

        $this->assertNull($second);
        $this->assertEquals(1, XpTransaction::where('user_id', $this->user->id)->where('type', 'workout_completed')->count());
    }

    // ── checkLevelUp ──────────────────────────────────────────────

    public function test_check_level_up_promotes_at_correct_threshold(): void
    {
        $gam = $this->user->gamification;
        $gam->update(['xp_total' => 200]);

        $result = $this->service->checkLevelUp($this->user);

        $this->assertTrue($result);
        $this->user->refresh();
        $this->assertEquals(2, $this->user->gamification->current_level);
    }

    public function test_check_level_up_returns_false_when_no_promotion(): void
    {
        $gam = $this->user->gamification;
        $gam->update(['xp_total' => 100]);

        $result = $this->service->checkLevelUp($this->user);

        $this->assertFalse($result);
        $this->user->refresh();
        $this->assertEquals(1, $this->user->gamification->current_level);
    }

    public function test_check_level_up_skips_multiple_levels(): void
    {
        $gam = $this->user->gamification;
        $gam->update(['xp_total' => 1500]);

        $result = $this->service->checkLevelUp($this->user);

        $this->assertTrue($result);
        $this->user->refresh();
        $this->assertEquals(5, $this->user->gamification->current_level);
    }

    // ── awardBadge ────────────────────────────────────────────────

    public function test_award_badge_creates_user_achievement(): void
    {
        // streak_7 is seeded by the migration
        $achievement = Achievement::where('slug', 'streak_7')->first();
        $this->assertNotNull($achievement, 'streak_7 achievement must exist from migration seed');

        $ua = $this->service->awardBadge($this->user, 'streak_7');

        $this->assertNotNull($ua);
        $this->assertEquals($this->user->id, $ua->user_id);
        $this->assertEquals($achievement->xp_reward, $ua->xp_received);

        $this->user->refresh();
        $this->assertGreaterThan(0, $this->user->gamification->xp_total);
    }

    public function test_award_badge_is_idempotent(): void
    {
        $first = $this->service->awardBadge($this->user, 'streak_7');
        $second = $this->service->awardBadge($this->user, 'streak_7');

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertEquals(1, UserAchievement::where('user_id', $this->user->id)->count());
    }

    public function test_award_badge_returns_null_for_nonexistent_badge(): void
    {
        $result = $this->service->awardBadge($this->user, 'nonexistent_badge_slug');

        $this->assertNull($result);
        $this->assertEquals(0, UserAchievement::where('user_id', $this->user->id)->count());
    }
}
