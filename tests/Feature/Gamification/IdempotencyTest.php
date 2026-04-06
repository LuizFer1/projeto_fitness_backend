<?php

namespace Tests\Feature\Gamification;

use App\Application\UseCases\Xp\AddXpUseCase;
use App\Events\GoalCompleted;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_xp_use_case_duplicate_reference_returns_null(): void
    {
        $useCase = new AddXpUseCase();
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        $tx1 = $useCase->execute($user, 100, 'goal_completed', 'goal', 'idem-1');
        $tx2 = $useCase->execute($user, 100, 'goal_completed', 'goal', 'idem-1');

        $this->assertNotNull($tx1);
        $this->assertNull($tx2);

        $count = XpTransaction::where('user_uuid', $user->uuid)
            ->where('reference_id', 'idem-1')
            ->count();
        $this->assertEquals(1, $count);

        $user->refresh();
        $this->assertEquals(100, $user->xp_points);
    }

    public function test_same_event_dispatched_twice_creates_only_one_transaction(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        GoalCompleted::dispatch($user, 'idem-goal-1');
        GoalCompleted::dispatch($user, 'idem-goal-1');

        $count = XpTransaction::where('user_uuid', $user->uuid)
            ->where('reason', 'goal_completed')
            ->where('reference_id', 'idem-goal-1')
            ->count();
        $this->assertEquals(1, $count);

        $user->refresh();
        // Only 100 XP from goal + achievement XP (not doubled)
        $this->assertEquals(100 + $this->getAchievementXp($user), $user->xp_points);
    }

    public function test_different_reference_ids_create_separate_transactions(): void
    {
        $useCase = new AddXpUseCase();
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        $tx1 = $useCase->execute($user, 100, 'goal_completed', 'goal', 'diff-1');
        $tx2 = $useCase->execute($user, 100, 'goal_completed', 'goal', 'diff-2');

        $this->assertNotNull($tx1);
        $this->assertNotNull($tx2);

        $count = XpTransaction::where('user_uuid', $user->uuid)
            ->where('reason', 'goal_completed')
            ->count();
        $this->assertEquals(2, $count);

        $user->refresh();
        $this->assertEquals(200, $user->xp_points);
    }

    public function test_idempotency_preserves_correct_xp_total(): void
    {
        $useCase = new AddXpUseCase();
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        // First call succeeds
        $useCase->execute($user, 50, 'login_daily', null, 'login-1');
        // Duplicate — should be ignored
        $useCase->execute($user, 50, 'login_daily', null, 'login-1');
        // Different reference — should succeed
        $useCase->execute($user, 100, 'goal_completed', 'goal', 'goal-1');

        $user->refresh();
        $this->assertEquals(150, $user->xp_points);
        $this->assertEquals(2, XpTransaction::where('user_uuid', $user->uuid)->count());
    }

    /**
     * Get total achievement XP awarded to user (from achievement_unlocked transactions).
     */
    private function getAchievementXp(User $user): int
    {
        return (int) XpTransaction::where('user_uuid', $user->uuid)
            ->where('reason', 'achievement_unlocked')
            ->sum('amount');
    }
}
