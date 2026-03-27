<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserGamification;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:80',
            'last_name' => 'required|string|max:120',
            'email'     => 'required|email|max:180|unique:users,email',
            'cpf'       => 'required|string|max:14|unique:users,cpf',
            'password'  => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'          => $data['name'],
            'last_name'     => $data['last_name'],
            'email'         => $data['email'],
            'cpf'           => $data['cpf'],
            'password_hash' => Hash::make($data['password']),
        ]);

        $user->refresh();

        UserGamification::create(['user_id' => $user->id]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request, GamificationService $gamification): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        // RF-01: Grant daily login XP
        $gamification->grantDailyLoginXp($user);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(Request $request, GamificationService $gamification): JsonResponse
    {
        $user = $request->user();

        // RF-01: Grant daily login XP on profile access
        $gamification->grantDailyLoginXp($user);

        return response()->json($user->load(['onboarding', 'gamification']));
    }
}
