<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * POST /v1/posts — create a text post.
     */
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
