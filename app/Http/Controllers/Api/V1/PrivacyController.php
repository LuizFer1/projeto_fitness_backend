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
use OpenApi\Attributes as OA;

class PrivacyController extends Controller
{
    #[OA\Get(
        path: '/api/v1/privacy/my-data',
        summary: 'Exportar meus dados',
        description: 'Exporta todos os dados pessoais do usuário autenticado (LGPD).',
        tags: ['Privacy'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dados exportados com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
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

    #[OA\Delete(
        path: '/api/v1/privacy/delete-account',
        summary: 'Excluir conta',
        description: 'Exclui permanentemente a conta do usuário e todos os dados associados (LGPD).',
        tags: ['Privacy'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', example: 'senha1234'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Conta excluída com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Senha incorreta'),
        ]
    )]
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
