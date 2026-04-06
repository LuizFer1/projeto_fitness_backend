<?php

namespace App\Listeners;

use App\Application\UseCases\Xp\AddXpUseCase;
use App\Events\AchievementUnlocked;
use App\Events\FriendshipAccepted;
use App\Events\GoalCheckinCreated;
use App\Events\GoalCompleted;
use App\Events\MealFollowed;
use App\Events\PostLiked;
use App\Events\UserLoggedIn;
use App\Events\WorkoutDayCompleted;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardXpForEventListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly AddXpUseCase $addXpUseCase,
    ) {}

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof UserLoggedIn => $this->addXpUseCase->execute(
                user: $event->user,
                baseAmount: 10,
                reason: 'login_daily',
                referenceType: 'login',
                referenceId: Carbon::now('America/Sao_Paulo')->toDateString(),
            ),

            $event instanceof GoalCheckinCreated => $this->addXpUseCase->execute(
                user: $event->user,
                baseAmount: 10,
                reason: 'goal_checkin',
                referenceType: 'checkin',
                referenceId: $event->checkinId,
            ),

            $event instanceof GoalCompleted => $this->addXpUseCase->execute(
                user: $event->user,
                baseAmount: 100,
                reason: 'goal_completed',
                referenceType: 'goal',
                referenceId: $event->goalId,
            ),

            $event instanceof WorkoutDayCompleted => $this->addXpUseCase->execute(
                user: $event->user,
                baseAmount: 40,
                reason: 'workout_done',
                referenceType: 'plan_day',
                referenceId: $event->planDayId,
            ),

            $event instanceof MealFollowed => $this->addXpUseCase->execute(
                user: $event->user,
                baseAmount: 15,
                reason: 'meal_followed',
                referenceType: 'plan_day',
                referenceId: $event->planDayId,
            ),

            $event instanceof FriendshipAccepted => $this->awardBothFriends($event),

            $event instanceof PostLiked => $this->addXpUseCase->execute(
                user: $event->author,
                baseAmount: 5,
                reason: 'post_liked',
                referenceType: 'post',
                referenceId: $event->postId,
            ),

            $event instanceof AchievementUnlocked => $this->addXpUseCase->execute(
                user: $event->user,
                baseAmount: $event->xpReward,
                reason: 'achievement_unlocked',
                referenceType: 'user_achievement',
                referenceId: $event->userAchievementId,
            ),

            default => null,
        };
    }

    private function awardBothFriends(FriendshipAccepted $event): void
    {
        $this->addXpUseCase->execute(
            user: $event->user,
            baseAmount: 20,
            reason: 'friend_added',
            referenceType: 'friendship',
            referenceId: $event->friendshipId,
        );

        $this->addXpUseCase->execute(
            user: $event->friend,
            baseAmount: 20,
            reason: 'friend_added',
            referenceType: 'friendship',
            referenceId: $event->friendshipId,
        );
    }
}
