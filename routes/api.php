<?php

use App\Http\Controllers\Api\V1\FriendController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\UserSearchController;
use App\Http\Controllers\Api\V1\Gamification\AchievementController;
use App\Http\Controllers\Api\V1\Gamification\LeaderboardController;
use App\Http\Controllers\Api\V1\Gamification\XpHistoryController;
use App\Http\Controllers\Api\V1\PublicProfile\PublicProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OnboardingController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::bind('username', function (string $value) {
    return User::where('username', $value)
        ->where('is_active', true)
        ->firstOrFail();
});

Route::group([], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);

        Route::get('onboarding',  [OnboardingController::class, 'show']);
        Route::post('onboarding', [OnboardingController::class, 'store']);

        Route::prefix('v1')->group(function () {
            // User search
            Route::get('users/search', UserSearchController::class);

            Route::get('users/{username}', [PublicProfileController::class, 'show']);
            Route::get('users/{username}/achievements', [PublicProfileController::class, 'achievements']);
            Route::get('users/{username}/goals', [PublicProfileController::class, 'goals']);

            // Friends
            Route::get('friends', [FriendController::class, 'index']);
            Route::get('friends/requests', [FriendController::class, 'requests']);
            Route::post('friends/request', [FriendController::class, 'sendRequest']);
            Route::post('friends/{id}/accept', [FriendController::class, 'accept']);
            Route::post('friends/{id}/reject', [FriendController::class, 'reject']);
            Route::delete('friends/{id}', [FriendController::class, 'destroy']);
            Route::post('friends/{id}/block', [FriendController::class, 'block']);

            // Posts & Feed
            Route::post('posts', [PostController::class, 'store']);
            Route::get('feed', [PostController::class, 'feed']);
            Route::get('posts/{id}', [PostController::class, 'show']);
            Route::delete('posts/{id}', [PostController::class, 'destroy']);
        });

        Route::prefix('v1/gamification')->group(function () {
            Route::middleware('throttle:leaderboard')->group(function () {
                Route::get('leaderboard/weekly',  [LeaderboardController::class, 'weekly']);
                Route::get('leaderboard/monthly', [LeaderboardController::class, 'monthly']);
                Route::get('leaderboard/alltime', [LeaderboardController::class, 'alltime']);
                Route::get('leaderboard/friends', [LeaderboardController::class, 'friends']);
            });

            Route::get('achievements', [AchievementController::class, 'index']);
            Route::get('xp-history', [XpHistoryController::class, 'index']);
        });

    });
});

