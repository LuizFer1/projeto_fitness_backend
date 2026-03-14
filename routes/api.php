<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::group([], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);

        Route::get('onboarding',  [OnboardingController::class, 'show']);
        Route::post('onboarding', [OnboardingController::class, 'store']);

        Route::get('leaderboard/{period}', [\App\Http\Controllers\LeaderboardController::class, 'index']);
        Route::get('users/{userId}/profile', [\App\Http\Controllers\ProfileController::class, 'show']);

        Route::get('goals', [\App\Http\Controllers\GoalController::class, 'index']);
        Route::put('goals/exercise', [\App\Http\Controllers\GoalController::class, 'updateExercise']);
        Route::put('goals/alimentation', [\App\Http\Controllers\GoalController::class, 'updateAlimentation']);
    });
});

