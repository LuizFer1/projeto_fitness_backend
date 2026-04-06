<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show($userId)
    {
        $user = User::with('gamification')->findOrFail($userId);

        return response()->json([
            'nome' => $user->name,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar_url,
            'level' => $user->gamification ? $user->gamification->current_level : 1,
            'xp_total' => $user->gamification ? $user->gamification->xp_total : 0,
            'bio' => $user->bio,
        ]);
    }
}
