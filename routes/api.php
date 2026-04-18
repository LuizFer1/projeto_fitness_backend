<?php

use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\V1\AiMealPlanController;
use App\Http\Controllers\Api\V1\AiPlanController;
use App\Http\Controllers\Api\V1\BodyMeasurementController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\QuestController;
use App\Http\Controllers\Api\V1\FriendController;
use App\Http\Controllers\Api\V1\Gamification\AchievementController;
use App\Http\Controllers\Api\V1\Gamification\LeaderboardController;
use App\Http\Controllers\Api\V1\Gamification\XpHistoryController;
use App\Http\Controllers\Api\V1\MealLogController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\PublicProfile\PublicProfileController;
use App\Http\Controllers\Api\V1\RankingController;
use App\Http\Controllers\Api\V1\UserSearchController;
use App\Http\Controllers\Api\V1\WorkoutLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\OnboardingController;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthCheckController::class);

Route::bind('username', function (string $value) {
    return User::where('username', $value)
        ->where('is_active', true)
        ->firstOrFail();
});

Route::group([], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

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

            // User profile by ID (for leaderboard modal)
            Route::get('users/{userId}/profile', function (string $userId) {
                $user = \App\Models\User::findOrFail($userId);
                $gam = $user->gamification;
                $achievements = $user->achievements()
                    ->with('achievement')
                    ->get()
                    ->map(fn ($ua) => [
                        'name' => $ua->achievement->name,
                        'icon' => $ua->achievement->icon,
                        'unlocked_at' => $ua->unlocked_at,
                    ]);

                return response()->json([
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'last_name'  => $user->last_name,
                    'username'   => $user->username,
                    'avatar_url' => $user->avatar_url,
                    'bio'        => $user->bio,
                    'level'      => $gam->current_level ?? 1,
                    'xp_total'   => $gam->xp_total ?? 0,
                    'streak'     => $gam->current_streak ?? 0,
                    'badges'     => $achievements,
                ]);
            });

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
            Route::post('posts/{id}/like', [PostController::class, 'like']);
            Route::post('posts/{id}/comments', [PostController::class, 'storeComment']);
            Route::delete('posts/{id}/comments/{commentId}', [PostController::class, 'destroyComment']);

            // Exercises catalog
            Route::get('exercises', function (Request $request) {
                $query = Exercise::where('is_active', true);
                if ($request->filled('muscle_group')) {
                    $query->where('muscle_group', $request->query('muscle_group'));
                }
                if ($request->filled('category')) {
                    $query->where('category', $request->query('category'));
                }
                if ($request->filled('search')) {
                    $query->where('name', 'like', '%' . $request->query('search') . '%');
                }
                return response()->json(['data' => $query->orderBy('name')->paginate(50)]);
            });

            // AI Plans (workout)
            Route::get('plans', [AiPlanController::class, 'index']);
            Route::get('plans/{id}', [AiPlanController::class, 'show']);
            Route::post('plans/generate-workout', [AiPlanController::class, 'generateWorkout']);
            Route::patch('plans/{id}/activate', [AiPlanController::class, 'activate']);
            Route::patch('plans/{id}/archive', [AiPlanController::class, 'archive']);
            Route::post('plans/{id}/duplicate', [AiPlanController::class, 'duplicate']);

            // AI Plans (meal)
            Route::get('plans/meals', [AiMealPlanController::class, 'index']);
            Route::get('plans/meals/{id}', [AiMealPlanController::class, 'show']);
            Route::post('plans/generate-meal', [AiMealPlanController::class, 'generateMealPlan']);
            Route::patch('plans/meals/{id}/activate', [AiMealPlanController::class, 'activate']);
            Route::patch('plans/meals/{id}/archive', [AiMealPlanController::class, 'archive']);
            Route::post('plans/meals/{id}/regenerate', [AiMealPlanController::class, 'regenerate']);

            // Workout logs
            Route::post('workouts/finish', [WorkoutLogController::class, 'finish']);

            // Meal logs
            Route::post('meals/analyze-text', [MealLogController::class, 'analyzeText']);
            Route::post('meals/analyze-image', [MealLogController::class, 'analyzeImage']);

            // Rankings
            Route::get('rankings', [RankingController::class, 'index']);
            Route::get('rankings/profile', [RankingController::class, 'profile']);

            // Body measurements (weight log)
            Route::get('measurements', [BodyMeasurementController::class, 'index']);
            Route::post('measurements', [BodyMeasurementController::class, 'store']);
            Route::delete('measurements/{id}', [BodyMeasurementController::class, 'destroy']);

            // Quests / Missions
            Route::get('quests', [QuestController::class, 'index']);
            Route::get('quests/mine', [QuestController::class, 'mine']);
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
