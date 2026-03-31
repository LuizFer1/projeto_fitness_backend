<?php

namespace App\Http\Controllers;

use App\Application\UseCases\Auth\LoginUserUseCase;
use App\Application\UseCases\Auth\RegisterUserUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private RegisterUserUseCase $registerUseCase;
    private LoginUserUseCase $loginUseCase;

    public function __construct(
        RegisterUserUseCase $registerUseCase,
        LoginUserUseCase $loginUseCase
    ) {
        $this->registerUseCase = $registerUseCase;
        $this->loginUseCase = $loginUseCase;
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:80',
            'last_name' => 'required|string|max:120',
            'email'     => 'required|email|max:180|unique:users,email',
            'cpf'       => 'required|string|max:14|unique:users,cpf',
            'password'  => 'required|string|min:8|confirmed',
        ]);

        $result = $this->registerUseCase->execute($data);

        return response()->json($result, 201);
    }

    public function login(Request $request, GamificationService $gamification): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->loginUseCase->execute($credentials);

        return response()->json($result);
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
