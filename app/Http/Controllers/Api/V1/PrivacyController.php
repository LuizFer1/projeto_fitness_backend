<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MealLog;
use App\Models\UserAchievement;
use App\Models\UserGoal;
use App\Models\WorkoutLog;
use App\Models\XpTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PrivacyController extends Controller
{
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();

        $profile = $user->only([
            'id', 'name', 'last_name', 'email', 'cpf',
            'avatar_url', 'nickname', 'bio', 'timezone',
            'created_at', 'updated_at',
        ]);

        $onboarding = $user->onboarding;

        $goals = UserGoal::where('user_id', $user->id)->get();

        $workoutLogs = WorkoutLog::where('user_id', $user->id)->get();

        $mealLogs = MealLog::where('user_id', $user->id)->get();

        $xpTransactions = XpTransaction::where('user_id', $user->id)->get();

        $achievements = UserAchievement::where('user_id', $user->id)
            ->with('achievement:id,slug,name,description,category')
            ->get();

        $gamification = $user->gamification;

        return response()->json([
            'profile' => $profile,
            'onboarding' => $onboarding,
            'goals' => $goals,
            'workout_logs' => $workoutLogs,
            'meal_logs' => $mealLogs,
            'xp_transactions' => $xpTransactions,
            'achievements' => $achievements,
            'gamification' => $gamification,
            'exported_at' => now()->toIso8601String(),
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->getAuthPassword())) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_PASSWORD',
                    'message' => 'The provided password is incorrect.',
                    'fields' => [],
                ],
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $userId = $user->id;

            // Delete related data from all tables with user_id
            DB::table('post_comments')->where('user_id', $userId)->delete();
            DB::table('post_likes')->where('user_id', $userId)->delete();
            DB::table('posts')->where('user_id', $userId)->delete();

            // Delete workout exercise logs via workout logs
            $workoutLogIds = WorkoutLog::where('user_id', $userId)->pluck('id');
            if ($workoutLogIds->isNotEmpty()) {
                DB::table('workout_exercise_logs')->whereIn('workout_log_id', $workoutLogIds)->delete();
            }
            DB::table('workout_logs')->where('user_id', $userId)->delete();

            DB::table('meal_logs')->where('user_id', $userId)->delete();
            DB::table('xp_transactions')->where('user_id', $userId)->delete();
            DB::table('user_achievements')->where('user_id', $userId)->delete();
            DB::table('user_gamification')->where('user_id', $userId)->delete();
            DB::table('user_goals')->where('user_id', $userId)->delete();
            DB::table('onboarding')->where('user_id', $userId)->delete();
            DB::table('daily_activity_limits')->where('user_id', $userId)->delete();
            DB::table('streak_history')->where('user_id', $userId)->delete();
            DB::table('user_quests')->where('user_id', $userId)->delete();
            DB::table('ranking_snapshots')->where('user_id', $userId)->delete();
            DB::table('ai_feedback_triggers')->where('user_id', $userId)->delete();
            DB::table('ai_plans')->where('user_id', $userId)->delete();
            DB::table('user_restrictions')->where('user_id', $userId)->delete();
            DB::table('body_measurements')->where('user_id', $userId)->delete();
            DB::table('water_logs')->where('user_id', $userId)->delete();

            // Friendships (uses requester_id / addressee_id)
            DB::table('friendships')
                ->where('requester_id', $userId)
                ->orWhere('addressee_id', $userId)
                ->delete();

            // Revoke all Sanctum tokens
            $user->tokens()->delete();

            // Delete the user
            $user->delete();
        });

        return response()->json([
            'message' => 'Account and all associated data have been permanently deleted.',
        ]);
    }
}
