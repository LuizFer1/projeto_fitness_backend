<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::group([], function () {
    Route::post('register', [AuthController::class , 'register']);
    Route::post('login', [AuthController::class , 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {

            Route::post('logout', [AuthController::class , 'logout']);
            Route::get('me', [AuthController::class , 'me']);

            Route::get('onboarding', [OnboardingController::class , 'show']);
            Route::post('onboarding', [OnboardingController::class , 'store']);

            Route::get('leaderboard/{period}', [\App\Http\Controllers\LeaderboardController::class , 'index']);
            Route::get('users/{userId}/profile', [\App\Http\Controllers\ProfileController::class , 'show']);

            Route::get('goals', [\App\Http\Controllers\GoalController::class , 'index']);
            Route::put('goals/exercise', [\App\Http\Controllers\GoalController::class , 'updateExercise']);
            Route::put('goals/alimentation', [\App\Http\Controllers\GoalController::class , 'updateAlimentation']);

            Route::post('v1/workouts/finish', [\App\Http\Controllers\Api\V1\WorkoutLogController::class , 'finish']);
            Route::post('v1/meals/analyze-text', [\App\Http\Controllers\Api\V1\MealLogController::class , 'analyzeText']);
            Route::post('v1/meals/analyze-image', [\App\Http\Controllers\Api\V1\MealLogController::class , 'analyzeImage']);

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
