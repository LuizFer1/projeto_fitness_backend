<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Follower;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FollowController extends Controller
{
    #[OA\Post(
        path: '/api/v1/users/{username}/follow',
        summary: 'Seguir usuário',
        description: 'Segue um usuário. Auto-aceito para perfis públicos; pendente para perfis privados.',
        tags: ['Follow'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Follow criado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Já segue ou tentativa de seguir a si mesmo'),
        ]
    )]
    public function follow(Request $request, User $username): JsonResponse
    {
        $me = $request->user();
        $target = $username; // resolved by Route::bind('username')

        if ($me->id === $target->id) {
            return response()->json(['message' => 'Você não pode seguir a si mesmo.'], 422);
        }

        $existing = Follower::where('follower_id', $me->id)->where('followee_id', $target->id)->first();
        if ($existing) {
            return response()->json(['message' => 'Você já segue ou solicitou seguir este usuário.'], 422);
        }

        $isPrivate = $target->profile_visibility === 'private';
        $status = $isPrivate ? 'pending' : 'accepted';

        $follow = Follower::create([
            'follower_id' => $me->id,
            'followee_id' => $target->id,
            'status' => $status,
            'accepted_at' => $isPrivate ? null : now(),
        ]);

        return response()->json([
            'id' => $follow->id,
            'status' => $status,
        ], 201);
    }

    #[OA\Delete(
        path: '/api/v1/users/{username}/follow',
        summary: 'Deixar de seguir usuário',
        tags: ['Follow'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Unfollow realizado'),
            new OA\Response(response: 404, description: 'Não estava seguindo'),
        ]
    )]
    public function unfollow(Request $request, User $username): JsonResponse
    {
        $me = $request->user();
        $target = $username;

        $deleted = Follower::where('follower_id', $me->id)
            ->where('followee_id', $target->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Você não está seguindo este usuário.'], 404);
        }

        return response()->json(['message' => 'Deixou de seguir com sucesso.']);
    }

    #[OA\Get(
        path: '/api/v1/follow-requests',
        summary: 'Listar solicitações de follow pendentes',
        tags: ['Follow'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de solicitações'),
        ]
    )]
    public function requests(Request $request): JsonResponse
    {
        $user = $request->user();

        $pending = Follower::pending()
            ->where('followee_id', $user->id)
            ->with('follower:id,name,last_name,username,avatar_url')
            ->latest()
            ->paginate(20);

        return response()->json($pending);
    }

    #[OA\Post(
        path: '/api/v1/follow-requests/{id}/accept',
        summary: 'Aceitar solicitação de follow',
        tags: ['Follow'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Solicitação aceita'),
            new OA\Response(response: 404, description: 'Solicitação não encontrada'),
        ]
    )]
    public function accept(Request $request, string $id): JsonResponse
    {
        $follow = Follower::where('id', $id)
            ->where('followee_id', $request->user()->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $follow->update(['status' => 'accepted', 'accepted_at' => now()]);

        return response()->json(['message' => 'Solicitação aceita.']);
    }

    #[OA\Post(
        path: '/api/v1/follow-requests/{id}/reject',
        summary: 'Rejeitar solicitação de follow',
        tags: ['Follow'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Solicitação rejeitada'),
            new OA\Response(response: 404, description: 'Solicitação não encontrada'),
        ]
    )]
    public function reject(Request $request, string $id): JsonResponse
    {
        $deleted = Follower::where('id', $id)
            ->where('followee_id', $request->user()->id)
            ->where('status', 'pending')
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Solicitação não encontrada.'], 404);
        }

        return response()->json(['message' => 'Solicitação rejeitada.']);
    }

    #[OA\Get(
        path: '/api/v1/users/{username}/followers',
        summary: 'Listar seguidores do usuário',
        tags: ['Follow'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de seguidores'),
            new OA\Response(response: 403, description: 'Perfil privado'),
        ]
    )]
    public function followers(Request $request, User $username): JsonResponse
    {
        $target = $username;

        if ($target->profile_visibility === 'private') {
            $viewer = $request->user();
            $isFollowing = Follower::accepted()
                ->where('follower_id', $viewer->id)
                ->where('followee_id', $target->id)
                ->exists();

            if (! $isFollowing && $viewer->id !== $target->id) {
                return response()->json(['message' => 'Perfil privado.'], 403);
            }
        }

        $followers = Follower::accepted()
            ->where('followee_id', $target->id)
            ->with('follower:id,name,last_name,username,avatar_url')
            ->latest('accepted_at')
            ->paginate(30);

        return response()->json($followers);
    }

    #[OA\Get(
        path: '/api/v1/users/{username}/following',
        summary: 'Listar quem o usuário segue',
        tags: ['Follow'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de seguidos'),
            new OA\Response(response: 403, description: 'Perfil privado'),
        ]
    )]
    public function following(Request $request, User $username): JsonResponse
    {
        $target = $username;

        if ($target->profile_visibility === 'private') {
            $viewer = $request->user();
            $isFollowing = Follower::accepted()
                ->where('follower_id', $viewer->id)
                ->where('followee_id', $target->id)
                ->exists();

            if (! $isFollowing && $viewer->id !== $target->id) {
                return response()->json(['message' => 'Perfil privado.'], 403);
            }
        }

        $following = Follower::accepted()
            ->where('follower_id', $target->id)
            ->with('followee:id,name,last_name,username,avatar_url')
            ->latest('accepted_at')
            ->paginate(30);

        return response()->json($following);
    }
}
