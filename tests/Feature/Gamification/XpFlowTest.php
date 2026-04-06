<?php

namespace Tests\Feature\Gamification;

use App\Events\GoalCompleted;
use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\XpTransaction;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XpFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AchievementSeeder::class);
    }

    public function test_goal_completed_creates_xp_transaction_updates_user_and_unlocks_achievement(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        GoalCompleted::dispatch($user, 'goal-e2e-1');

        // XpTransaction created with correct amount
        $this->assertDatabaseHas('xp_transactions', [
            'user_uuid' => $user->uuid,
            'amount' => 100,
            'reason' => 'goal_completed',
            'reference_id' => 'goal-e2e-1',
        ]);

        // user.xp_points updated (100 for goal + 50 for goal_completed_1 achievement xp_reward)
        $user->refresh();
        $goalCompletedAchievement = Achievement::where('key', 'goal_completed_1')->first();
        $expectedXp = 100 + $goalCompletedAchievement->xp_reward;
        $this->assertEquals($expectedXp, $user->xp_points);

        // goal_completed_1 achievement unlocked
        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => $goalCompletedAchievement->id,
        ]);
    }

    public function test_goal_completed_triggers_level_up_when_crossing_threshold(): void
    {
        // User at 450 XP (level 1). GoalCompleted gives 100 XP → 550 → level 2
        $user = User::factory()->create(['xp_points' => 450, 'level' => 1]);

        GoalCompleted::dispatch($user, 'goal-levelup');

        $user->refresh();
        $this->assertGreaterThanOrEqual(500, $user->xp_points);
        $this->assertEquals(2, $user->level);
    }

    public function test_full_chain_goal_completed_xp_achievement_achievement_xp(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        GoalCompleted::dispatch($user, 'goal-chain-1');

        $user->refresh();

        // Should have 2 XP transactions: goal_completed (100) + achievement_unlocked (goal_completed_1 xp_reward)
        $transactions = XpTransaction::where('user_uuid', $user->uuid)->get();
        $this->assertEquals(2, $transactions->count());

        $goalTx = $transactions->where('reason', 'goal_completed')->first();
        $achievementTx = $transactions->where('reason', 'achievement_unlocked')->first();

        $this->assertNotNull($goalTx);
        $this->assertNotNull($achievementTx);
        $this->assertEquals(100, $goalTx->amount);

        $achievement = Achievement::where('key', 'goal_completed_1')->first();
        $this->assertEquals($achievement->xp_reward, $achievementTx->amount);

        // Total XP = goal (100) + achievement reward
        $this->assertEquals(100 + $achievement->xp_reward, $user->xp_points);
    }

    public function test_five_goals_completed_unlocks_both_goal_achievements(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        for ($i = 1; $i <= 5; $i++) {
            GoalCompleted::dispatch($user, "goal-multi-{$i}");
        }

        $goalCompleted1 = Achievement::where('key', 'goal_completed_1')->first();
        $goalCompleted5 = Achievement::where('key', 'goal_completed_5')->first();

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => $goalCompleted1->id,
        ]);

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => $goalCompleted5->id,
        ]);

        // Each achievement unlocked only once
        $this->assertEquals(1, UserAchievement::where('user_uuid', $user->uuid)
            ->where('achievement_id', $goalCompleted1->id)->count());
        $this->assertEquals(1, UserAchievement::where('user_uuid', $user->uuid)
            ->where('achievement_id', $goalCompleted5->id)->count());
    }
}
