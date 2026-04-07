<?php

namespace App\Http\Controllers\Api\V1\PublicProfile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PublicProfileController extends Controller
{
    #[OA\Get(
        path: '/api/v1/users/{username}',
        summary: 'Perfil público do usuário',
        description: 'Retorna os dados públicos de um usuário pelo username.',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, description: 'Username do usuário', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados do perfil público'),
            new OA\Response(response: 404, description: 'Usuário não encontrado'),
        ]
    )]
    public function show(User $username): JsonResponse
    {
        $user = $username;

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'avatar_url' => $user->avatar_url,
            'bio' => $user->bio,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/users/{username}/achievements',
        summary: 'Conquistas do usuário',
        description: 'Retorna as conquistas desbloqueadas de um usuário.',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, description: 'Username do usuário', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de conquistas'),
            new OA\Response(response: 404, description: 'Usuário não encontrado'),
        ]
    )]
    public function achievements(User $username): JsonResponse
    {
        $user = $username;

        $achievements = $user->achievements()
            ->with('achievement')
            ->get()
            ->map(fn ($ua) => [
                'id' => $ua->achievement->id,
                'name' => $ua->achievement->name,
                'description' => $ua->achievement->description,
                'icon' => $ua->achievement->icon,
                'unlocked_at' => $ua->unlocked_at,
            ]);

        return response()->json(['data' => $achievements]);
    }

    #[OA\Get(
        path: '/api/v1/users/{username}/goals',
        summary: 'Metas do usuário',
        description: 'Retorna as metas públicas de um usuário.',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, description: 'Username do usuário', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados das metas'),
            new OA\Response(response: 404, description: 'Usuário não encontrado'),
        ]
    )]
    public function goals(User $username): JsonResponse
    {
        $user = $username;

        $goal = $user->goal;

        if (! $goal) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'main_goal' => $goal->main_goal,
                'goal_calories_day' => $goal->goal_calories_day,
                'goal_steps_day' => $goal->goal_steps_day,
                'goal_weight_kg' => $goal->goal_weight_kg,
                'goal_workouts_week' => $goal->goal_workouts_week,
                'deadline' => $goal->deadline?->toDateString(),
            ],
        ]);
    }
}
