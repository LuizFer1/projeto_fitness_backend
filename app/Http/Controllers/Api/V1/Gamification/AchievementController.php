<?php

namespace App\Http\Controllers\Api\V1\Gamification;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AchievementController extends Controller
{
    #[OA\Get(
        path: '/api/v1/gamification/achievements',
        summary: 'Listar conquistas',
        description: 'Retorna as conquistas desbloqueadas do usuário autenticado.',
        tags: ['Gamification'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de conquistas'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $achievements = $user->achievements()
            ->with('achievement')
            ->get()
            ->map(fn ($ua) => [
                'id' => $ua->achievement->id,
                'slug' => $ua->achievement->slug,
                'name' => $ua->achievement->name,
                'description' => $ua->achievement->description,
                'icon' => $ua->achievement->icon,
                'category' => $ua->achievement->category,
                'xp_reward' => $ua->achievement->xp_reward,
                'unlocked_at' => $ua->unlocked_at,
            ]);

        return response()->json(['data' => $achievements]);
    }
}
