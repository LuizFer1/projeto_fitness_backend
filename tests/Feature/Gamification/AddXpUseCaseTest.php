<?php

namespace Tests\Feature\Gamification;

use App\Application\UseCases\Xp\AddXpUseCase;
use App\Events\LevelChanged;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AddXpUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private AddXpUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new AddXpUseCase();
    }

    public function test_creates_xp_transaction_and_updates_user_xp(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        $tx = $this->useCase->execute($user, 100, 'goal_completed', 'goal', 'goal-1');

        $this->assertNotNull($tx);
        $this->assertDatabaseHas('xp_transactions', [
            'user_uuid' => $user->uuid,
            'amount' => 100,
            'reason' => 'goal_completed',
            'reference_type' => 'goal',
            'reference_id' => 'goal-1',
        ]);

        $user->refresh();
        $this->assertEquals(100, $user->xp_points);
    }

    public function test_idempotent_duplicate_returns_null(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        $tx1 = $this->useCase->execute($user, 100, 'goal_completed', 'goal', 'goal-1');
        $tx2 = $this->useCase->execute($user, 100, 'goal_completed', 'goal', 'goal-1');

        $this->assertNotNull($tx1);
        $this->assertNull($tx2);
        $this->assertEquals(1, XpTransaction::where('user_uuid', $user->uuid)->count());

        $user->refresh();
        $this->assertEquals(100, $user->xp_points);
    }

    public function test_dispatches_level_changed_when_crossing_threshold(): void
    {
        Event::fake([LevelChanged::class]);

        $user = User::factory()->create(['xp_points' => 450, 'level' => 1]);

        $this->useCase->execute($user, 100, 'goal_completed', 'goal', 'goal-cross');

        $user->refresh();
        $this->assertEquals(550, $user->xp_points);
        $this->assertEquals(2, $user->level);

        Event::assertDispatched(LevelChanged::class, function (LevelChanged $e) use ($user) {
            return $e->user->uuid === $user->uuid
                && $e->oldLevel === 1
                && $e->newLevel === 2;
        });
    }

    public function test_does_not_dispatch_level_changed_when_same_level(): void
    {
        Event::fake([LevelChanged::class]);

        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        $this->useCase->execute($user, 10, 'login_daily', null, 'day-1');

        $user->refresh();
        $this->assertEquals(1, $user->level);

        Event::assertNotDispatched(LevelChanged::class);
    }

    public function test_applies_streak_bonus_for_eligible_reason(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        // Day 1 activity (2 days ago)
        XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'amount' => 10,
            'reason' => 'login_daily',
            'reference_id' => 'day-1',
            'created_at' => now()->subDays(2),
        ]);

        // Day 2 activity (yesterday)
        XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'amount' => 10,
            'reason' => 'login_daily',
            'reference_id' => 'day-2',
            'created_at' => now()->subDays(1),
        ]);

        // Day 3 (today) — streak = 2 (yesterday + 2 days ago), bonus = min(2-1, 10) * 5 = 5
        $tx = $this->useCase->execute($user, 10, 'login_daily', null, 'day-3', now());

        $this->assertNotNull($tx);
        $this->assertEquals(15, $tx->amount); // 10 base + 5 streak bonus
        $this->assertEquals(10, $tx->meta['base_amount']);
        $this->assertEquals(5, $tx->meta['streak_bonus']);
    }

    public function test_no_streak_bonus_for_non_eligible_reason(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        $tx = $this->useCase->execute($user, 100, 'goal_completed', 'goal', 'goal-2');

        $this->assertNotNull($tx);
        $this->assertEquals(100, $tx->amount);
        $this->assertEquals(0, $tx->meta['streak_bonus']);
    }
}
