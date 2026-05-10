<?php

use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\V1\Admin\BadgeController as AdminBadgeController;
use App\Http\Controllers\Api\V1\Admin\ExerciseController as AdminExerciseController;
use App\Http\Controllers\Api\V1\Admin\QuestController as AdminQuestController;
use App\Http\Controllers\Api\V1\AiMealPlanController;
use App\Http\Controllers\Api\V1\AiPlanController;
use App\Http\Controllers\Api\V1\BiweeklyReportController;
use App\Http\Controllers\Api\V1\BodyMeasurementController;
use App\Http\Controllers\Api\V1\CardioWorkoutController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ExerciseCatalogController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\NetworkGraphController;
use App\Http\Controllers\Api\V1\FoodController;
use App\Http\Controllers\Api\V1\FriendController;
use App\Http\Controllers\Api\V1\Gamification\AchievementController;
use App\Http\Controllers\Api\V1\Gamification\LeaderboardController;
use App\Http\Controllers\Api\V1\Gamification\XpHistoryController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\MealLogController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\NutritionController;
use App\Http\Controllers\Api\V1\NutritionExportController;
use App\Http\Controllers\Api\V1\PersonalRecordController;
use App\Http\Controllers\Api\V1\PlanCatalogController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\PrivacySettingsController;
use App\Http\Controllers\Api\V1\ProgressPhotoController;
use App\Http\Controllers\Api\V1\PublicProfile\PublicProfileController;
use App\Http\Controllers\Api\V1\QuestController;
use App\Http\Controllers\Api\V1\RankingController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TdeeConfigController;
use App\Http\Controllers\Api\V1\UserSearchController;
use App\Http\Controllers\Api\V1\VictoryAssetController;
use App\Http\Controllers\Api\V1\WaterLogController;
use App\Http\Controllers\Api\V1\WorkoutLogController;
use App\Http\Controllers\Api\V1\WorkoutPlanCommentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\OnboardingController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthCheckController::class);

// Public catalog
Route::get('v1/plans/catalog', [PlanCatalogController::class, 'index']);

Route::bind('username', function (string $value) {
    return User::where('username', $value)
        ->where('is_active', true)
        ->firstOrFail();
});

Route::group([], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('onboarding', [OnboardingController::class, 'show']);
        Route::post('onboarding', [OnboardingController::class, 'store']);

        // Goals
        Route::get('goals', [GoalController::class, 'index']);
        Route::put('goals/exercise', [GoalController::class, 'updateExercise']);
        Route::put('goals/alimentation', [GoalController::class, 'updateAlimentation']);

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('dashboard/alimentation', [DashboardController::class, 'alimentation']);
        Route::get('dashboard/exercise', [DashboardController::class, 'exercise']);

        // Gamification profile (alias for frontend compatibility)
        Route::get('gamification/profile', [RankingController::class, 'profile']);

        Route::prefix('v1')->group(function () {
            // User search
            Route::get('users/search', UserSearchController::class);

            Route::get('users/{username}', [PublicProfileController::class, 'show']);
            Route::get('users/{username}/achievements', [PublicProfileController::class, 'achievements']);
            Route::get('users/{username}/goals', [PublicProfileController::class, 'goals']);

            // Followers (RF-18)
            Route::post('users/{username}/follow', [FollowController::class, 'follow']);
            Route::delete('users/{username}/follow', [FollowController::class, 'unfollow']);
            Route::get('users/{username}/followers', [FollowController::class, 'followers']);
            Route::get('users/{username}/following', [FollowController::class, 'following']);
            Route::get('follow-requests', [FollowController::class, 'requests']);
            Route::post('follow-requests/{id}/accept', [FollowController::class, 'accept']);
            Route::post('follow-requests/{id}/reject', [FollowController::class, 'reject']);

            // Network graph — viz of follows + interactions.
            Route::get('network/graph', [NetworkGraphController::class, 'graph']);

            // User profile by ID (for leaderboard modal)
            Route::get('users/{userId}/profile', [PublicProfileController::class, 'compactById']);

            // Friends
            Route::get('friends', [FriendController::class, 'index']);
            Route::get('friends/requests', [FriendController::class, 'requests']);
            Route::post('friends/request', [FriendController::class, 'sendRequest']);
            Route::post('friends/{id}/accept', [FriendController::class, 'accept']);
            Route::post('friends/{id}/reject', [FriendController::class, 'reject']);
            Route::delete('friends/{id}', [FriendController::class, 'destroy']);
            Route::post('friends/{id}/block', [FriendController::class, 'block']);

            // Posts & Feed
            Route::post('posts', [PostController::class, 'store'])->middleware('idempotent');
            Route::get('feed', [PostController::class, 'feed']);
            Route::get('posts/{id}', [PostController::class, 'show']);
            Route::delete('posts/{id}', [PostController::class, 'destroy']);
            Route::post('posts/{id}/like', [PostController::class, 'like']);
            Route::post('posts/{id}/comments', [PostController::class, 'storeComment']);
            Route::delete('posts/{id}/comments/{commentId}', [PostController::class, 'destroyComment']);

            // Exercises catalog
            Route::get('exercises', [ExerciseCatalogController::class, 'index']);

            // AI Plans (meal) — must be declared BEFORE plans/{id} to avoid collision
            Route::get('plans/meals', [AiMealPlanController::class, 'index']);
            Route::get('plans/meals/{id}', [AiMealPlanController::class, 'show']);
            Route::post('plans/generate-meal', [AiMealPlanController::class, 'generateMealPlan']);
            Route::patch('plans/meals/{id}/activate', [AiMealPlanController::class, 'activate']);
            Route::patch('plans/meals/{id}/archive', [AiMealPlanController::class, 'archive']);
            Route::post('plans/meals/{id}/regenerate', [AiMealPlanController::class, 'regenerate']);

            // AI Plans (workout)
            Route::get('plans', [AiPlanController::class, 'index']);
            Route::post('plans/generate-workout', [AiPlanController::class, 'generateWorkout']);

            // Per-exercise comments + plan refinement (must come BEFORE plans/{id} to avoid route collision)
            Route::get('plans/{plan_id}/comments', [WorkoutPlanCommentController::class, 'index']);
            Route::post('plans/{plan_id}/exercises/{plan_workout_exercise_id}/comments', [WorkoutPlanCommentController::class, 'store']);
            Route::delete('plans/{plan_id}/comments/{comment_id}', [WorkoutPlanCommentController::class, 'destroy']);
            Route::post('plans/{id}/refine', [AiPlanController::class, 'refineWorkout']);

            Route::get('plans/{id}', [AiPlanController::class, 'show']);
            Route::patch('plans/{id}/activate', [AiPlanController::class, 'activate']);
            Route::patch('plans/{id}/archive', [AiPlanController::class, 'archive']);
            Route::post('plans/{id}/duplicate', [AiPlanController::class, 'duplicate']);

            // Workout logs
            Route::post('workouts/finish', [WorkoutLogController::class, 'finish'])->middleware('idempotent');
            Route::post('workouts/cardio', [CardioWorkoutController::class, 'store'])->middleware('idempotent');

            // Victory assets (RF-16, RF-17)
            Route::post('workouts/{uuid}/victory-asset', [VictoryAssetController::class, 'enqueue']);
            Route::get('workouts/{uuid}/victory-asset', [VictoryAssetController::class, 'show']);

            // Personal records & progressive overload
            Route::get('workouts/personal-records', [PersonalRecordController::class, 'index']);
            Route::get('workouts/exercises/{exercise_id}/suggest-load', [PersonalRecordController::class, 'suggestLoad']);
            Route::get('workouts/exercises/{exercise_id}/history', [PersonalRecordController::class, 'history']);

            // Meal logs
            Route::post('meals/analyze-text', [MealLogController::class, 'analyzeText'])->middleware('idempotent');
            Route::post('meals/analyze-image', [MealLogController::class, 'analyzeImage'])->middleware('idempotent');

            // Nutrition daily summary & adjustments
            Route::get('nutrition/today', [NutritionController::class, 'today']);
            Route::get('diet-adjustments', [NutritionController::class, 'adjustments']);

            // Nutrition history export (RF-06)
            Route::get('nutrition/export', [NutritionExportController::class, 'export']);
            Route::get('nutrition/export/status/{id}', [NutritionExportController::class, 'status']);

            // Progress photos (private diary)
            Route::post('progress-photos', [ProgressPhotoController::class, 'store']);
            Route::get('progress-photos', [ProgressPhotoController::class, 'index']);
            Route::get('progress-photos/{uuid}', [ProgressPhotoController::class, 'show']);
            Route::delete('progress-photos/{uuid}', [ProgressPhotoController::class, 'destroy']);

            // Push notification devices
            Route::post('devices', [DeviceController::class, 'store']);
            Route::delete('devices/{uuid}', [DeviceController::class, 'destroy']);

            // Notification preferences
            Route::get('notification-preferences', [NotificationPreferenceController::class, 'show']);
            Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);

            // External integrations (wearables)
            Route::get('integrations', [IntegrationController::class, 'index']);
            Route::post('integrations/healthkit/sync', [IntegrationController::class, 'syncHealthKit']);
            Route::get('integrations/googlefit/authorize', [IntegrationController::class, 'authorizeGoogleFit']);
            Route::get('integrations/googlefit/callback', [IntegrationController::class, 'callbackGoogleFit']);
            Route::delete('integrations/{provider}', [IntegrationController::class, 'disconnect']);

            // Rankings
            Route::get('rankings', [RankingController::class, 'index']);
            Route::get('rankings/profile', [RankingController::class, 'profile']);

            // Body measurements (weight log)
            Route::get('measurements', [BodyMeasurementController::class, 'index']);
            Route::post('measurements', [BodyMeasurementController::class, 'store'])->middleware('idempotent');
            Route::delete('measurements/{id}', [BodyMeasurementController::class, 'destroy']);

            // Water / hydration logs
            Route::get('water-logs', [WaterLogController::class, 'index']);
            Route::post('water-logs', [WaterLogController::class, 'store'])->middleware('idempotent');
            Route::delete('water-logs/{id}', [WaterLogController::class, 'destroy']);

            // Quests / Missions
            Route::get('quests', [QuestController::class, 'index']);
            Route::get('quests/mine', [QuestController::class, 'mine']);

            // Subscriptions
            Route::get('subscriptions/me', [SubscriptionController::class, 'me']);
            Route::post('subscriptions', [SubscriptionController::class, 'store'])->middleware('idempotent');
            Route::post('subscriptions/cancel', [SubscriptionController::class, 'cancel'])->middleware('idempotent');
            Route::post('subscriptions/resume', [SubscriptionController::class, 'resume'])->middleware('idempotent');

            // Biweekly reports (RF-25)
            Route::get('reports/biweekly', [BiweeklyReportController::class, 'index']);
            Route::get('reports/biweekly/{id}', [BiweeklyReportController::class, 'show']);
            Route::post('reports/biweekly/generate', [BiweeklyReportController::class, 'generate']);

            // TDEE formula config (RF-05)
            Route::put('onboarding/tdee-config', [TdeeConfigController::class, 'update']);

            // Privacy settings per metric (RF-26)
            Route::get('privacy-settings', [PrivacySettingsController::class, 'show']);
            Route::put('privacy-settings', [PrivacySettingsController::class, 'update']);

            // Food barcode lookup (RF-04)
            Route::get('foods/lookup', [FoodController::class, 'lookup']);

            // Admin CRUD
            Route::prefix('admin')->middleware('admin')->group(function () {
                Route::apiResource('badges', AdminBadgeController::class)->except(['show']);
                Route::apiResource('quests', AdminQuestController::class)->except(['show']);
                Route::post('exercises/bulk', [AdminExerciseController::class, 'bulkImport']);
                Route::apiResource('exercises', AdminExerciseController::class)->except(['show']);
            });
        });

        // Privacy / LGPD
        Route::get('v1/privacy/my-data', [PrivacyController::class, 'exportData']);
        Route::delete('v1/privacy/delete-account', [PrivacyController::class, 'deleteAccount']);

        // Leaderboard aliases (frontend compatibility)
        Route::get('leaderboard/weekly', [LeaderboardController::class, 'weekly']);

        Route::prefix('v1/gamification')->group(function () {
            Route::middleware('throttle:leaderboard')->group(function () {
                Route::get('leaderboard/weekly', [LeaderboardController::class, 'weekly']);
                Route::get('leaderboard/monthly', [LeaderboardController::class, 'monthly']);
                Route::get('leaderboard/alltime', [LeaderboardController::class, 'alltime']);
                Route::get('leaderboard/friends', [LeaderboardController::class, 'friends']);
            });

            Route::get('achievements', [AchievementController::class, 'index']);
            Route::get('xp-history', [XpHistoryController::class, 'index']);
        });

    });
});
