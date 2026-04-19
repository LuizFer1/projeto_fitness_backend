<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserGamification;
use App\Models\UserAchievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class RankingController extends Controller
{
    /**
     * GET /api/v1/rankings?period=weekly|monthly|all_time
     *
     * Returns top users + current user's position.
     * Cached for 5 minutes (RNF: max 5min delay).
     */
    #[OA\Get(
        path: '/api/v1/rankings',
        summary: 'Listar ranking de usuários',
        description: 'Retorna os top usuários do período e a posição do usuário atual. Cache de 5 minutos.',
        tags: ['Rankings'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', required: false, description: 'Período do ranking', schema: new OA\Schema(type: 'string', enum: ['weekly', 'monthly', 'all_time'], default: 'weekly')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Limite de resultados (máx 100)', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ranking do período'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Período inválido'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $period = $request->query('period', 'weekly');
        $user   = $request->user();
        $limit  = (int) $request->query('limit', 20);
        $limit  = min($limit, 100);

        if (!in_array($period, ['weekly', 'monthly', 'all_time'])) {
            return response()->json(['error' => 'Período inválido. Use: weekly, monthly, all_time'], 422);
        }

        $cacheKey = "ranking_{$period}_top{$limit}";

        $rankings = Cache::remember($cacheKey, 300, function () use ($period, $limit) {
            return $this->buildRanking($period, $limit);
        });

        // Get current user's position (always fresh for accuracy)
        $myPosition = $this->getUserPosition($user->id, $period);

        return response()->json([
            'period'   => $period,
            'rankings' => $rankings,
            'my_position' => $myPosition,
        ]);
    }

    /**
     * GET /api/v1/rankings/profile
     *
     * Returns the current user's full gamification profile.
     */
    #[OA\Get(
        path: '/api/v1/rankings/profile',
        summary: 'Perfil de gamificação do usuário',
        description: 'Retorna perfil completo de gamificação: XP, nível, streak, badges conquistadas e badges ainda não notificadas. Acessível também via /api/gamification/profile.',
        tags: ['Rankings'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Perfil de gamificação'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $gam  = $user->gamification;

        if (!$gam) {
            return response()->json([
                'xp_total'        => 0,
                'current_level'   => 1,
                'xp_to_next'      => 200,
                'current_streak'  => 0,
                'max_streak'      => 0,
                'current_week_xp' => 0,
                'current_month_xp'=> 0,
                'total_workouts'  => 0,
                'badges'          => [],
                'new_badges'      => [],
            ]);
        }

        $badges = UserAchievement::where('user_id', $user->id)
            ->with('achievement')
            ->orderByDesc('unlocked_at')
            ->get()
            ->map(fn ($ua) => [
                'slug'        => $ua->achievement->slug,
                'name'        => $ua->achievement->name,
                'description' => $ua->achievement->description,
                'icon'        => $ua->achievement->icon,
                'category'    => $ua->achievement->category,
                'xp_received' => $ua->xp_received,
                'unlocked_at' => $ua->unlocked_at,
            ]);

        $unnotifiedBadges = UserAchievement::where('user_id', $user->id)
            ->where('is_notified', false)
            ->with('achievement')
            ->get();

        // Mark them as notified
        if ($unnotifiedBadges->isNotEmpty()) {
            UserAchievement::where('user_id', $user->id)
                ->where('is_notified', false)
                ->update(['is_notified' => true]);
        }

        return response()->json([
            'xp_total'        => $gam->xp_total,
            'current_level'   => $gam->current_level,
            'xp_to_next'      => $gam->xp_to_next,
            'current_streak'  => $gam->current_streak,
            'max_streak'      => $gam->max_streak,
            'current_week_xp' => $gam->current_week_xp,
            'current_month_xp'=> $gam->current_month_xp,
            'total_workouts'  => $gam->total_workouts,
            'badges'          => $badges,
            'new_badges'      => $unnotifiedBadges->map(fn ($ua) => [
                'name' => $ua->achievement->name,
                'icon' => $ua->achievement->icon,
                'xp'   => $ua->xp_received,
            ]),
        ]);
    }

    // ── Private ──

    private function buildRanking(string $period, int $limit): array
    {
        $column = match ($period) {
            'weekly'   => 'current_week_xp',
            'monthly'  => 'current_month_xp',
            'all_time' => 'xp_total',
        };

        return UserGamification::select([
                'user_id',
                $column . ' as period_xp',
                'current_level',
                'xp_total',
            ])
            ->with(['user:id,name,last_name,nickname,avatar_url'])
            ->orderByDesc($column)
            ->limit($limit)
            ->get()
            ->map(function ($row, $index) {
                return [
                    'position'      => $index + 1,
                    'user_id'       => $row->user_id,
                    'name'          => $row->user->nickname ?? ($row->user->name . ' ' . $row->user->last_name),
                    'avatar_url'    => $row->user->avatar_url,
                    'period_xp'     => $row->period_xp,
                    'level'         => $row->current_level,
                    'total_xp'      => $row->xp_total,
                ];
            })
            ->toArray();
    }

    private function getUserPosition(string $userId, string $period): ?array
    {
        $column = match ($period) {
            'weekly'   => 'current_week_xp',
            'monthly'  => 'current_month_xp',
            'all_time' => 'xp_total',
        };

        $gam = UserGamification::where('user_id', $userId)->first();
        if (!$gam) {
            return null;
        }

        $myXp = $gam->{$column};

        $position = UserGamification::where($column, '>', $myXp)->count() + 1;

        return [
            'position'  => $position,
            'period_xp' => $myXp,
            'level'     => $gam->current_level,
            'total_xp'  => $gam->xp_total,
        ];
    }
}
