<?php

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

            Route::post('logout', [AuthController::class , 'logout']);
            Route::get('me', [AuthController::class , 'me']);

            Route::get('onboarding', [OnboardingController::class , 'show']);
            Route::post('onboarding', [OnboardingController::class , 'store']);

        Route::prefix('v1')->group(function () {
            Route::get('users/{username}', [PublicProfileController::class, 'show']);
            Route::get('users/{username}/achievements', [PublicProfileController::class, 'achievements']);
            Route::get('users/{username}/goals', [PublicProfileController::class, 'goals']);
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

            Route::post('v1/plans/generate-meal', [\App\Http\Controllers\Api\V1\AiMealPlanController::class , 'generateMealPlan']);
            Route::get('v1/plans/meals', [\App\Http\Controllers\Api\V1\AiMealPlanController::class , 'index']);
            Route::get('v1/plans/meals/{id}', [\App\Http\Controllers\Api\V1\AiMealPlanController::class , 'show']);
            Route::patch('v1/plans/meals/{id}/activate', [\App\Http\Controllers\Api\V1\AiMealPlanController::class , 'activate']);
            Route::patch('v1/plans/meals/{id}/archive', [\App\Http\Controllers\Api\V1\AiMealPlanController::class , 'archive']);
            Route::post('v1/plans/meals/{id}/regenerate', [\App\Http\Controllers\Api\V1\AiMealPlanController::class , 'regenerate']);

            Route::post('v1/plans/generate-workout', [\App\Http\Controllers\Api\V1\AiPlanController::class , 'generateWorkout']);
            Route::get('v1/plans', [\App\Http\Controllers\Api\V1\AiPlanController::class , 'index']);
            Route::get('v1/plans/{id}', [\App\Http\Controllers\Api\V1\AiPlanController::class , 'show']);
            Route::patch('v1/plans/{id}/activate', [\App\Http\Controllers\Api\V1\AiPlanController::class , 'activate']);
            Route::patch('v1/plans/{id}/archive', [\App\Http\Controllers\Api\V1\AiPlanController::class , 'archive']);
            Route::post('v1/plans/{id}/duplicate', [\App\Http\Controllers\Api\V1\AiPlanController::class , 'duplicate']);

            Route::get('v1/rankings', [\App\Http\Controllers\Api\V1\RankingController::class , 'index']);
            Route::get('v1/rankings/profile', [\App\Http\Controllers\Api\V1\RankingController::class , 'profile']);
        }
        );
    });
