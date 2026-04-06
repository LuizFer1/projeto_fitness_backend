<?php

use App\Http\Controllers\Api\V1\Gamification\AchievementController;
use App\Http\Controllers\Api\V1\Gamification\LeaderboardController;
use App\Http\Controllers\Api\V1\Gamification\XpHistoryController;
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

