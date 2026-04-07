<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FriendController extends Controller
{
    /**
     * GET /v1/friends — list accepted friends with pagination.
     */
    #[OA\Get(
        path: '/api/v1/friends',
        summary: 'Listar amigos',
        description: 'Retorna a lista paginada de amigos aceitos.',
        tags: ['Friends'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de amigos'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $friendships = Friendship::accepted()
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                  ->orWhere('addressee_id', $user->id);
            })
            ->with(['requester:id,name,last_name,username,avatar_url', 'addressee:id,name,last_name,username,avatar_url'])
            ->paginate(20);

        $friends = $friendships->through(function ($friendship) use ($user) {
            $friend = $friendship->requester_id === $user->id
                ? $friendship->addressee
                : $friendship->requester;

            return [
                'friendship_id' => $friendship->id,
                'id' => $friend->id,
                'name' => $friend->name,
                'last_name' => $friend->last_name,
                'username' => $friend->username,
                'avatar_url' => $friend->avatar_url,
                'accepted_at' => $friendship->accepted_at,
            ];
        });

        return response()->json($friends);
    }

    /**
     * GET /v1/friends/requests — list pending requests received.
     */
    #[OA\Get(
        path: '/api/v1/friends/requests',
        summary: 'Listar solicitações de amizade',
        description: 'Retorna a lista paginada de solicitações de amizade pendentes recebidas.',
        tags: ['Friends'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de solicitações'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function requests(Request $request): JsonResponse
    {
        $requests = Friendship::pending()
            ->where('addressee_id', $request->user()->id)
            ->with('requester:id,name,last_name,username,avatar_url')
            ->paginate(20);

        $requests->through(function ($friendship) {
            return [
                'friendship_id' => $friendship->id,
                'id' => $friendship->requester->id,
                'name' => $friendship->requester->name,
                'last_name' => $friendship->requester->last_name,
                'username' => $friendship->requester->username,
                'avatar_url' => $friendship->requester->avatar_url,
                'created_at' => $friendship->created_at,
            ];
        });

        return response()->json($requests);
    }

    /**
     * POST /v1/friends/request — send friend request.
     */
    #[OA\Post(
        path: '/api/v1/friends/request',
        summary: 'Enviar solicitação de amizade',
        description: 'Envia uma solicitação de amizade para outro usuário.',
        tags: ['Friends'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['username'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'joao_silva'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Solicitação enviada com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação ou solicitação já existente'),
        ]
    )]
    public function sendRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|exists:users,username',
        ]);

        $user = $request->user();
        $addressee = User::where('username', $data['username'])->firstOrFail();

        if ($addressee->id === $user->id) {
            return response()->json(['message' => 'You cannot send a friend request to yourself.'], 422);
        }

        // Check if a friendship already exists in either direction
        $existing = Friendship::where(function ($q) use ($user, $addressee) {
            $q->where('requester_id', $user->id)->where('addressee_id', $addressee->id);
        })->orWhere(function ($q) use ($user, $addressee) {
            $q->where('requester_id', $addressee->id)->where('addressee_id', $user->id);
        })->first();

        if ($existing) {
            if ($existing->status === 'blocked') {
                return response()->json(['message' => 'Unable to send friend request.'], 422);
            }
            return response()->json(['message' => 'A friendship request already exists.'], 422);
        }

        $friendship = Friendship::create([
            'requester_id' => $user->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Friend request sent.',
            'friendship' => $friendship,
        ], 201);
    }

    /**
     * POST /v1/friends/{id}/accept — accept a friend request.
     */
    #[OA\Post(
        path: '/api/v1/friends/{id}/accept',
        summary: 'Aceitar solicitação de amizade',
        description: 'Aceita uma solicitação de amizade pendente.',
        tags: ['Friends'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID da amizade', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Solicitação aceita'),
            new OA\Response(response: 403, description: 'Não autorizado'),
            new OA\Response(response: 422, description: 'Solicitação não pode ser aceita'),
        ]
    )]
    public function accept(string $id): JsonResponse
    {
        $friendship = Friendship::findOrFail($id);
        $user = request()->user();

        if ($friendship->addressee_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($friendship->status !== 'pending') {
            return response()->json(['message' => 'This request cannot be accepted.'], 422);
        }

        $friendship->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json(['message' => 'Friend request accepted.']);
    }

    /**
     * POST /v1/friends/{id}/reject — reject a friend request.
     */
    #[OA\Post(
        path: '/api/v1/friends/{id}/reject',
        summary: 'Rejeitar solicitação de amizade',
        description: 'Rejeita uma solicitação de amizade pendente.',
        tags: ['Friends'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID da amizade', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Solicitação rejeitada'),
            new OA\Response(response: 403, description: 'Não autorizado'),
            new OA\Response(response: 422, description: 'Solicitação não pode ser rejeitada'),
        ]
    )]
    public function reject(string $id): JsonResponse
    {
        $friendship = Friendship::findOrFail($id);
        $user = request()->user();

        if ($friendship->addressee_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($friendship->status !== 'pending') {
            return response()->json(['message' => 'This request cannot be rejected.'], 422);
        }

        $friendship->delete();

        return response()->json(['message' => 'Friend request rejected.']);
    }

    /**
     * DELETE /v1/friends/{id} — remove friendship.
     */
    #[OA\Delete(
        path: '/api/v1/friends/{id}',
        summary: 'Remover amizade',
        description: 'Remove uma amizade existente.',
        tags: ['Friends'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID da amizade', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Amizade removida'),
            new OA\Response(response: 403, description: 'Não autorizado'),
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $friendship = Friendship::findOrFail($id);
        $user = request()->user();

        if ($friendship->requester_id !== $user->id && $friendship->addressee_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $friendship->delete();

        return response()->json(['message' => 'Friendship removed.']);
    }

    /**
     * POST /v1/friends/{id}/block — block user.
     */
    #[OA\Post(
        path: '/api/v1/friends/{id}/block',
        summary: 'Bloquear usuário',
        description: 'Bloqueia um usuário em uma amizade existente.',
        tags: ['Friends'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID da amizade', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuário bloqueado'),
            new OA\Response(response: 403, description: 'Não autorizado'),
        ]
    )]
    public function block(string $id): JsonResponse
    {
        $friendship = Friendship::findOrFail($id);
        $user = request()->user();

        if ($friendship->requester_id !== $user->id && $friendship->addressee_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $friendship->update([
            'status' => 'blocked',
            'blocked_at' => now(),
        ]);

        return response()->json(['message' => 'User blocked.']);
    }
}
