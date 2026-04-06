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
}
