<?php

namespace App\Http\Controllers;

use App\Application\UseCases\Auth\LoginUserUseCase;
use App\Application\UseCases\Auth\RegisterUserUseCase;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

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

    #[OA\Post(
        path: '/api/register',
        summary: 'Registrar novo usuário',
        description: 'Cria uma nova conta de usuário.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['name', 'last_name', 'email', 'cpf', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 80, example: 'João'),
                    new OA\Property(property: 'last_name', type: 'string', maxLength: 120, example: 'Silva'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 180, example: 'joao@email.com'),
                    new OA\Property(property: 'cpf', type: 'string', maxLength: 14, example: '123.456.789-00'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'senha1234'),
                    new OA\Property(property: 'password_confirmation', type: 'string', example: 'senha1234'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuário registrado com sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'last_name' => 'required|string|max:120',
            'email' => 'required|email|max:180|unique:users,email',
            'cpf' => 'required|string|max:14|unique:users,cpf',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $result = $this->registerUseCase->execute($data);

        return response()->json($result, 201);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Autenticar usuário',
        description: 'Realiza login e retorna token de acesso.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'joao@email.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'senha1234'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login realizado com sucesso'),
            new OA\Response(response: 422, description: 'Credenciais inválidas'),
        ]
    )]
    public function login(Request $request, GamificationService $gamification): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->loginUseCase->execute($credentials);

        return response()->json($result);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Logout',
        description: 'Revoga o token de acesso atual.',
        tags: ['Auth'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logout realizado com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    #[OA\Get(
        path: '/api/me',
        summary: 'Perfil do usuário autenticado',
        description: 'Retorna os dados do usuário autenticado, incluindo onboarding e gamificação.',
        tags: ['Auth'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dados do usuário'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function me(Request $request, GamificationService $gamification): JsonResponse
    {
        $user = $request->user();

        // RF-01: Grant daily login XP on profile access
        $gamification->grantDailyLoginXp($user);

        return response()->json($user->load(['onboarding', 'gamification']));
    }
}
