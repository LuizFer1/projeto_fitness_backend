<?php

namespace App\Listeners;

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
use App\Services\Gamification\StreakCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;

class UnlockAchievementListener implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof GoalCreated => $this->checkGoalCreated($event->user),
            $event instanceof GoalCheckinCreated => $this->checkFirstCheckin($event->user),
            $event instanceof GoalCompleted => $this->checkGoalCompleted($event->user),
            $event instanceof WorkoutDayCompleted => $this->checkWorkout($event->user),
            $event instanceof FriendshipAccepted => $this->checkFriendship($event),
            $event instanceof PlanGenerated => $this->tryUnlock($event->user, 'first_plan_generated'),
            $event instanceof RefinePlanCompleted => $this->tryUnlock($event->user, 'plan_refined'),
            default => null,
        };
    }

    private function checkGoalCreated(User $user): void
    {
        $goalCount = $user->xpTransactions()
            ->where('reason', 'goal_completed')
            ->count();

        // Use xp_transactions with reason referencing goals, but for goal_created
        // we just check if this is the first GoalCreated event (count >= 1 implied by event firing)
        $this->tryUnlock($user, 'first_goal_created');
    }

    private function checkFirstCheckin(User $user): void
    {
        $this->tryUnlock($user, 'first_checkin');
    }

    private function checkGoalCompleted(User $user): void
    {
        $completedCount = $user->xpTransactions()
            ->where('reason', 'goal_completed')
            ->count();

        if ($completedCount >= 1) {
            $this->tryUnlock($user, 'goal_completed_1');
        }

        if ($completedCount >= 5) {
            $this->tryUnlock($user, 'goal_completed_5');
        }
    }

    private function checkWorkout(User $user): void
    {
        $this->tryUnlock($user, 'first_workout_done');
        $this->checkStreaks($user);
    }

    private function checkFriendship(FriendshipAccepted $event): void
    {
        foreach ([$event->user, $event->friend] as $user) {
            $friendCount = $user->xpTransactions()
                ->where('reason', 'friend_added')
                ->count();

            if ($friendCount >= 1) {
                $this->tryUnlock($user, 'first_friend');
            }

            if ($friendCount >= 10) {
                $this->tryUnlock($user, 'social_butterfly');
            }
        }
    }

    private function checkStreaks(User $user): void
    {
        $streak = StreakCalculator::currentStreak($user);

        if ($streak >= 3) {
            $this->tryUnlock($user, 'streak_3_days');
        }

        if ($streak >= 7) {
            $this->tryUnlock($user, 'streak_7_days');
        }

        if ($streak >= 30) {
            $this->tryUnlock($user, 'streak_30_days');
        }
    }

    private function tryUnlock(User $user, string $achievementKey): void
    {
        $achievement = Achievement::where('key', $achievementKey)->first();

        if (! $achievement) {
            return;
        }

        $alreadyUnlocked = UserAchievement::where('user_uuid', $user->uuid)
            ->where('achievement_id', $achievement->id)
            ->exists();

        if ($alreadyUnlocked) {
            return;
        }

        try {
            $userAchievement = UserAchievement::create([
                'user_uuid' => $user->uuid,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
            ]);

            AchievementUnlocked::dispatch(
                $user,
                (string) $userAchievement->id,
                $achievement->xp_reward,
            );
        } catch (QueryException $e) {
            // Unique constraint violation — already unlocked (race condition)
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed') ||
                str_contains($e->getMessage(), '23000')) {
                return;
            }
            throw $e;
        }
    }
}
