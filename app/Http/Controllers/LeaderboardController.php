<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index(Request $request, $period)
    {
        $validPeriods = ['weekly' => 'current_week_xp', 'monthly' => 'current_month_xp', 'all_time' => 'xp_total'];

        if (!isset($validPeriods[$period])) {
            return response()->json(['error' => 'Invalid period. Use weekly, monthly, or all_time.'], 400);
        }

        $sortField = $validPeriods[$period];
        $currentUserId = $request->user()->id;

        $users = User::select(
                'users.id as user_id', 
                'users.name', 
                'users.avatar_url', 
                'user_gamification.id as entry_id', 
                "user_gamification.{$sortField} as pontos"
            )
            ->leftJoin('user_gamification', 'users.id', '=', 'user_gamification.user_id')
            ->orderByRaw("COALESCE(user_gamification.{$sortField}, 0) DESC")
            ->get();

        $leaderboard = $users->map(function ($user, $index) use ($currentUserId) {
            return [
                'id' => $user->entry_id ?? $user->user_id,
                'user_id' => $user->user_id,
                'nome' => $user->name,
                'avatar' => $user->avatar_url,
                'pontos' => (int) $user->pontos,
                'pos' => $index + 1,
                'souEu' => $user->user_id === $currentUserId,
            ];
        });

        return response()->json($leaderboard);
    }
}
