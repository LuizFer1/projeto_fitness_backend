<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PostController extends Controller
{
    /**
     * POST /v1/posts — create a text post.
     */
    #[OA\Post(
        path: '/api/v1/posts',
        summary: 'Criar post',
        description: 'Cria um novo post de texto.',
        tags: ['Posts'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['content', 'visibility'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', maxLength: 500, example: 'Treino de hoje foi incrível!'),
                    new OA\Property(property: 'visibility', type: 'string', enum: ['public', 'friends_only'], example: 'public'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Post criado com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => 'required|string|max:500',
            'visibility' => 'required|in:public,friends_only',
        ]);

        $post = Post::create([
            'user_id' => $request->user()->id,
            'type' => 'text',
            'content' => $data['content'],
            'visibility' => $data['visibility'],
        ]);

        $post->loadCount(['likes', 'comments']);

        return response()->json([
            'data' => $this->formatPost($post, $request->user()->id),
        ], 201);
    }

    /**
     * GET /v1/feed — posts from friends + own, cursor pagination.
     */
    #[OA\Get(
        path: '/api/v1/feed',
        summary: 'Feed de posts',
        description: 'Retorna posts do usuário e de amigos com paginação por cursor.',
        tags: ['Posts'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de posts'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get accepted friend IDs
        $friendIds = Friendship::accepted()
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                  ->orWhere('addressee_id', $user->id);
            })
            ->get()
            ->map(function ($friendship) use ($user) {
                return $friendship->requester_id === $user->id
                    ? $friendship->addressee_id
                    : $friendship->requester_id;
            })
            ->toArray();

        $userIds = array_merge([$user->id], $friendIds);

        $posts = Post::whereIn('user_id', $userIds)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('visibility', 'public')
                  ->orWhere('visibility', 'friends_only');
            })
            ->with('user:id,name,last_name,username,avatar_url')
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(15);

        $posts->through(fn ($post) => $this->formatPost($post, $user->id));

        return response()->json($posts);
    }

    /**
     * GET /v1/posts/{id} — single post with comments and like count.
     */
    #[OA\Get(
        path: '/api/v1/posts/{id}',
        summary: 'Detalhes do post',
        description: 'Retorna um post com comentários e contagem de likes.',
        tags: ['Posts'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do post', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados do post'),
            new OA\Response(response: 404, description: 'Post não encontrado'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $user = request()->user();

        $post = Post::with([
                'user:id,name,last_name,username,avatar_url',
                'comments' => fn ($q) => $q->with('user:id,name,last_name,username,avatar_url')->orderBy('created_at', 'asc'),
            ])
            ->withCount(['likes', 'comments'])
            ->findOrFail($id);

        $data = $this->formatPost($post, $user->id);
        $data['comments'] = $post->comments->map(fn ($comment) => [
            'id' => $comment->id,
            'user' => $comment->user,
            'content' => $comment->content,
            'created_at' => $comment->created_at,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * DELETE /v1/posts/{id} — soft delete own post.
     */
    #[OA\Delete(
        path: '/api/v1/posts/{id}',
        summary: 'Excluir post',
        description: 'Exclui (soft delete) um post do usuário autenticado.',
        tags: ['Posts'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do post', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Post excluído'),
            new OA\Response(response: 403, description: 'Não autorizado'),
            new OA\Response(response: 404, description: 'Post não encontrado'),
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $user = request()->user();

        if ($post->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    /**
     * POST /v1/posts/{id}/like — toggle like.
     */
    #[OA\Post(
        path: '/api/v1/posts/{id}/like',
        summary: 'Curtir/descurtir post',
        description: 'Alterna o like do usuário autenticado em um post.',
        tags: ['Posts'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do post', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status do like atualizado'),
            new OA\Response(response: 404, description: 'Post não encontrado'),
        ]
    )]
    public function like(string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $user = request()->user();

        $existing = PostLike::where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            PostLike::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);
        }

        $likeCount = $post->likes()->count();

        return response()->json([
            'data' => [
                'liked' => ! $existing,
                'like_count' => $likeCount,
            ],
        ]);
    }

    /**
     * POST /v1/posts/{id}/comments — create comment.
     */
    #[OA\Post(
        path: '/api/v1/posts/{id}/comments',
        summary: 'Comentar em post',
        description: 'Cria um comentário em um post.',
        tags: ['Posts'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do post', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', maxLength: 500, example: 'Ótimo treino!'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Comentário criado'),
            new OA\Response(response: 404, description: 'Post não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function storeComment(Request $request, string $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        $data = $request->validate([
            'content' => 'required|string|max:500',
        ]);

        $comment = PostComment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        $comment->load('user:id,name,last_name,username,avatar_url');

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'user' => $comment->user,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
            ],
        ], 201);
    }

    /**
     * DELETE /v1/posts/{id}/comments/{commentId} — soft delete own comment.
     */
    #[OA\Delete(
        path: '/api/v1/posts/{id}/comments/{commentId}',
        summary: 'Excluir comentário',
        description: 'Exclui (soft delete) um comentário do usuário autenticado.',
        tags: ['Posts'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do post', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'commentId', in: 'path', required: true, description: 'ID do comentário', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Comentário excluído'),
            new OA\Response(response: 403, description: 'Não autorizado'),
            new OA\Response(response: 404, description: 'Comentário não encontrado'),
        ]
    )]
    public function destroyComment(string $id, string $commentId): JsonResponse
    {
        $comment = PostComment::where('post_id', $id)
            ->where('id', $commentId)
            ->firstOrFail();

        $user = request()->user();

        if ($comment->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    /**
     * Format a post for API response.
     */
    private function formatPost(Post $post, string $userId): array
    {
        return [
            'id' => $post->id,
            'user' => $post->user,
            'type' => $post->type,
            'content' => $post->content,
            'metadata' => $post->metadata,
            'visibility' => $post->visibility,
            'like_count' => $post->likes_count,
            'comment_count' => $post->comments_count,
            'is_liked_by_me' => $post->likes()->where('user_id', $userId)->exists(),
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
        ];
    }
}
