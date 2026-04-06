<?php

namespace App\Providers;

use App\Events\AchievementUnlocked;
use App\Events\FriendshipAccepted;
use App\Events\GoalCheckinCreated;
use App\Events\GoalCompleted;
use App\Events\MealFollowed;
use App\Events\PostLiked;
use App\Events\UserLoggedIn;
use App\Events\WorkoutDayCompleted;
use App\Listeners\AwardXpForEventListener;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $xpEvents = [
            UserLoggedIn::class,
            GoalCheckinCreated::class,
            GoalCompleted::class,
            WorkoutDayCompleted::class,
            MealFollowed::class,
            FriendshipAccepted::class,
            PostLiked::class,
            AchievementUnlocked::class,
        ];

        foreach ($xpEvents as $event) {
            Event::listen($event, AwardXpForEventListener::class);
        }
    }
}
