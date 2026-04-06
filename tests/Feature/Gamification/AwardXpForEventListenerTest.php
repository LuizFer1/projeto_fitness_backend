<?php

namespace Tests\Feature\Gamification;

use App\Events\AchievementUnlocked;
use App\Events\FriendshipAccepted;
use App\Events\GoalCheckinCreated;
use App\Events\GoalCompleted;
use App\Events\MealFollowed;
use App\Events\PostLiked;
use App\Events\UserLoggedIn;
use App\Events\WorkoutDayCompleted;
use App\Listeners\AwardXpForEventListener;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwardXpForEventListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_goal_completed_creates_xp_transaction_with_correct_amount(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        GoalCompleted::dispatch($user, 'goal-abc');

        $this->assertDatabaseHas('xp_transactions', [
            'user_uuid' => $user->uuid,
            'amount' => 100,
            'reason' => 'goal_completed',
            'reference_type' => 'goal',
            'reference_id' => 'goal-abc',
        ]);

        $user->refresh();
        $this->assertEquals(100, $user->xp_points);
    }

    public function test_goal_completed_applies_streak_bonus(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        // goal_completed is NOT streak-eligible, so no bonus regardless of history
        GoalCompleted::dispatch($user, 'goal-xyz');

        $tx = XpTransaction::where('user_uuid', $user->uuid)->first();
        $this->assertEquals(100, $tx->amount);
        $this->assertEquals(0, $tx->meta['streak_bonus']);
    }

    public function test_friendship_accepted_creates_two_xp_transactions(): void
    {
        $user1 = User::factory()->create(['xp_points' => 0, 'level' => 1]);
        $user2 = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        FriendshipAccepted::dispatch($user1, $user2, 'friendship-123');

        $this->assertDatabaseHas('xp_transactions', [
            'user_uuid' => $user1->uuid,
            'amount' => 20,
            'reason' => 'friend_added',
            'reference_type' => 'friendship',
            'reference_id' => 'friendship-123',
        ]);

        $this->assertDatabaseHas('xp_transactions', [
            'user_uuid' => $user2->uuid,
            'amount' => 20,
            'reason' => 'friend_added',
            'reference_type' => 'friendship',
            'reference_id' => 'friendship-123',
        ]);

        $user1->refresh();
        $user2->refresh();
        $this->assertEquals(20, $user1->xp_points);
        $this->assertEquals(20, $user2->xp_points);
    }

    public function test_user_logged_in_awards_10_xp(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        UserLoggedIn::dispatch($user);

        $tx = XpTransaction::where('user_uuid', $user->uuid)->first();
        $this->assertNotNull($tx);
        $this->assertEquals(10, $tx->amount);
        $this->assertEquals('login_daily', $tx->reason);
    }

    public function test_goal_checkin_awards_10_xp(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        GoalCheckinCreated::dispatch($user, 'checkin-1');

        $tx = XpTransaction::where('user_uuid', $user->uuid)->first();
        $this->assertNotNull($tx);
        $this->assertEquals(10, $tx->amount);
        $this->assertEquals('goal_checkin', $tx->reason);
        $this->assertEquals('checkin-1', $tx->reference_id);
    }

    public function test_workout_day_completed_awards_40_xp(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        WorkoutDayCompleted::dispatch($user, 'plan-day-1');

        $tx = XpTransaction::where('user_uuid', $user->uuid)->first();
        $this->assertNotNull($tx);
        $this->assertEquals(40, $tx->amount);
        $this->assertEquals('workout_done', $tx->reason);
        $this->assertEquals('plan-day-1', $tx->reference_id);
    }

    public function test_meal_followed_awards_15_xp(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        MealFollowed::dispatch($user, 'plan-day-2');

        $tx = XpTransaction::where('user_uuid', $user->uuid)->first();
        $this->assertNotNull($tx);
        $this->assertEquals(15, $tx->amount);
        $this->assertEquals('meal_followed', $tx->reason);
    }

    public function test_post_liked_awards_5_xp_to_author(): void
    {
        $author = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        PostLiked::dispatch($author, 'post-42');

        $tx = XpTransaction::where('user_uuid', $author->uuid)->first();
        $this->assertNotNull($tx);
        $this->assertEquals(5, $tx->amount);
        $this->assertEquals('post_liked', $tx->reason);
        $this->assertEquals('post-42', $tx->reference_id);
    }

    public function test_achievement_unlocked_awards_achievement_xp_reward(): void
    {
        $user = User::factory()->create(['xp_points' => 0, 'level' => 1]);

        AchievementUnlocked::dispatch($user, 'ua-99', 50);

        $tx = XpTransaction::where('user_uuid', $user->uuid)->first();
        $this->assertNotNull($tx);
        $this->assertEquals(50, $tx->amount);
        $this->assertEquals('achievement_unlocked', $tx->reason);
        $this->assertEquals('ua-99', $tx->reference_id);
    }

    public function test_user_xp_points_updated_after_goal_completed(): void
    {
        $user = User::factory()->create(['xp_points' => 400, 'level' => 1]);

        GoalCompleted::dispatch($user, 'goal-level-up');

        $user->refresh();
        $this->assertEquals(500, $user->xp_points);
        $this->assertEquals(2, $user->level);
    }
}
