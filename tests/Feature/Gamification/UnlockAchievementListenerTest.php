<?php

namespace Tests\Feature\Gamification;

use App\Events\AchievementUnlocked;
use App\Events\FriendshipAccepted;
use App\Events\GoalCheckinCreated;
use App\Events\GoalCompleted;
use App\Events\GoalCreated;
use App\Events\PlanGenerated;
use App\Events\RefinePlanCompleted;
use App\Events\WorkoutDayCompleted;
use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\XpTransaction;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UnlockAchievementListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AchievementSeeder::class);
    }

    public function test_goal_created_unlocks_first_goal_created(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        GoalCreated::dispatch($user, 'goal-1');

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => Achievement::where('key', 'first_goal_created')->first()->id,
        ]);
    }

    public function test_goal_checkin_unlocks_first_checkin(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        GoalCheckinCreated::dispatch($user, 'checkin-1');

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => Achievement::where('key', 'first_checkin')->first()->id,
        ]);
    }

    public function test_goal_completed_unlocks_goal_completed_1(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        GoalCompleted::dispatch($user, 'goal-1');

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => Achievement::where('key', 'goal_completed_1')->first()->id,
        ]);
    }

    public function test_five_goals_completed_unlocks_goal_completed_5(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        // Complete 5 goals — each creates an xp_transaction with reason=goal_completed
        for ($i = 1; $i <= 5; $i++) {
            GoalCompleted::dispatch($user, "goal-$i");
        }

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => Achievement::where('key', 'goal_completed_1')->first()->id,
        ]);

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => Achievement::where('key', 'goal_completed_5')->first()->id,
        ]);
    }

    public function test_workout_completed_unlocks_first_workout_done(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        WorkoutDayCompleted::dispatch($user, 'plan-day-1');

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => Achievement::where('key', 'first_workout_done')->first()->id,
        ]);
    }

    public function test_friendship_accepted_unlocks_first_friend_for_both_users(): void
    {
        $user1 = User::factory()->create(['xp_points' => 0, 'level' => 1]);
        $user2 = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        FriendshipAccepted::dispatch($user1, $user2, 'friendship-1');

        $firstFriendId = Achievement::where('key', 'first_friend')->first()->id;

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user1->uuid,
            'achievement_id' => $firstFriendId,
        ]);

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user2->uuid,
            'achievement_id' => $firstFriendId,
        ]);
    }

    public function test_plan_generated_unlocks_first_plan_generated(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        PlanGenerated::dispatch($user, 'plan-1');

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => Achievement::where('key', 'first_plan_generated')->first()->id,
        ]);
    }

    public function test_refine_plan_completed_unlocks_plan_refined(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        RefinePlanCompleted::dispatch($user, 'plan-1');

        $this->assertDatabaseHas('user_achievements', [
            'user_uuid' => $user->uuid,
            'achievement_id' => Achievement::where('key', 'plan_refined')->first()->id,
        ]);
    }

    public function test_re_dispatching_event_does_not_duplicate_achievement(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        GoalCreated::dispatch($user, 'goal-1');
        GoalCreated::dispatch($user, 'goal-2');

        $achievementId = Achievement::where('key', 'first_goal_created')->first()->id;

        $this->assertEquals(1, UserAchievement::where('user_uuid', $user->uuid)
            ->where('achievement_id', $achievementId)
            ->count());
    }

    public function test_achievement_unlocked_event_dispatched_with_xp_reward(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        // After GoalCreated, the achievement unlocks and AchievementUnlocked is dispatched,
        // which triggers AwardXpForEventListener to award achievement XP
        GoalCreated::dispatch($user, 'goal-1');

        $achievement = Achievement::where('key', 'first_goal_created')->first();

        // The AchievementUnlocked event triggers XP award via AwardXpForEventListener
        $this->assertDatabaseHas('xp_transactions', [
            'user_uuid' => $user->uuid,
            'reason' => 'achievement_unlocked',
        ]);

        $tx = XpTransaction::where('user_uuid', $user->uuid)
            ->where('reason', 'achievement_unlocked')
            ->first();

        $this->assertEquals($achievement->xp_reward, $tx->amount);
    }
}
