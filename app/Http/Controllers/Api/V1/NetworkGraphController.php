<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Follower;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class NetworkGraphController extends Controller
{
    /**
     * How far back interactions are aggregated. 90d keeps the graph relevant
     * without scanning the full post history.
     */
    private const INTERACTION_WINDOW_DAYS = 90;

    /** Hard cap on first-degree connections to render. */
    private const MAX_NODES = 80;

    #[OA\Get(
        path: '/api/v1/network/graph',
        summary: 'Grafo de rede social do usuário',
        description: 'Retorna nodes (eu, follows, followers) e edges (relações de follow + interações em posts).',
        tags: ['Network'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Grafo da rede'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function graph(Request $request): JsonResponse
    {
        $me = $request->user();
        $myId = $me->id;

        // 1) First-degree set: me + accepted follows + accepted followers.
        $followingIds = Follower::query()
            ->where('follower_id', $myId)
            ->where('status', 'accepted')
            ->pluck('followee_id')
            ->all();

        $followerIds = Follower::query()
            ->where('followee_id', $myId)
            ->where('status', 'accepted')
            ->pluck('follower_id')
            ->all();

        $firstDegree = collect([$myId, ...$followingIds, ...$followerIds])
            ->unique()
            ->values();

        // Apply soft cap to keep payload + render time bounded.
        $nodeIds = $firstDegree->take(self::MAX_NODES)->all();
        $nodeIdSet = array_flip($nodeIds);

        // 2) Hydrate node profiles in one query.
        $users = User::query()
            ->whereIn('id', $nodeIds)
            ->get(['id', 'name', 'last_name', 'username', 'avatar_url']);

        $nodes = $users->map(function (User $u) use ($myId) {
            return [
                'id' => $u->id,
                'name' => trim($u->name.' '.($u->last_name ?? '')),
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
                'is_me' => $u->id === $myId,
            ];
        })->values();

        // 3) Pull every accepted follow row where BOTH endpoints are in the set.
        //    This gives us inter-node follows (mutual + one-way) without N queries.
        $follows = Follower::query()
            ->where('status', 'accepted')
            ->whereIn('follower_id', $nodeIds)
            ->whereIn('followee_id', $nodeIds)
            ->get(['follower_id', 'followee_id']);

        // 4) Aggregate interactions inside the node set within the window.
        //    Comments: count comments by user A on posts authored by user B.
        //    Likes: same shape.
        $since = now()->subDays(self::INTERACTION_WINDOW_DAYS);

        $commentCounts = PostComment::query()
            ->join('posts', 'posts.id', '=', 'post_comments.post_id')
            ->whereIn('post_comments.user_id', $nodeIds)
            ->whereIn('posts.user_id', $nodeIds)
            ->whereColumn('post_comments.user_id', '!=', 'posts.user_id')
            ->where('post_comments.created_at', '>=', $since)
            ->select(
                'post_comments.user_id as actor_id',
                'posts.user_id as author_id',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('post_comments.user_id', 'posts.user_id')
            ->get();

        $likeCounts = PostLike::query()
            ->join('posts', 'posts.id', '=', 'post_likes.post_id')
            ->whereIn('post_likes.user_id', $nodeIds)
            ->whereIn('posts.user_id', $nodeIds)
            ->whereColumn('post_likes.user_id', '!=', 'posts.user_id')
            ->where('post_likes.created_at', '>=', $since)
            ->select(
                'post_likes.user_id as actor_id',
                'posts.user_id as author_id',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('post_likes.user_id', 'posts.user_id')
            ->get();

        // 5) Merge follow + interaction signals into one edge per UNORDERED pair.
        //    Key by sorted "min|max" so A→B and B→A collapse.
        $edges = [];
        $key = fn (string $a, string $b): string => $a < $b ? "$a|$b" : "$b|$a";

        $ensureEdge = function (string $key, string $a, string $b) use (&$edges) {
            if (! isset($edges[$key])) {
                $edges[$key] = [
                    'source' => $a < $b ? $a : $b,
                    'target' => $a < $b ? $b : $a,
                    'follow_status' => 'none', // none|out|in|mutual (out/in relative to ME)
                    'interaction_count' => 0,
                    'comments' => 0,
                    'likes' => 0,
                ];
            }
        };

        // Follow status (relative to me when I'm one of the endpoints).
        foreach ($follows as $f) {
            $a = $f->follower_id;
            $b = $f->followee_id;
            if (! isset($nodeIdSet[$a]) || ! isset($nodeIdSet[$b])) {
                continue;
            }
            $k = $key($a, $b);
            $ensureEdge($k, $a, $b);

            // Direction handling
            if ($edges[$k]['follow_status'] === 'none') {
                if ($a === $myId) {
                    $edges[$k]['follow_status'] = 'out';
                } elseif ($b === $myId) {
                    $edges[$k]['follow_status'] = 'in';
                } else {
                    // Edge between two other users: just mark as "one-way" to start;
                    // promoted to "mutual" if reverse row exists.
                    $edges[$k]['follow_status'] = 'one_way';
                }
            } else {
                // Already saw the other direction — mutual.
                $edges[$k]['follow_status'] = 'mutual';
            }
        }

        $addInteraction = function (string $actor, string $author, int $cnt, string $kind) use (&$edges, $ensureEdge, $key, $nodeIdSet) {
            if (! isset($nodeIdSet[$actor]) || ! isset($nodeIdSet[$author])) {
                return;
            }
            $k = $key($actor, $author);
            $ensureEdge($k, $actor, $author);
            $edges[$k][$kind] += $cnt;
            // Comments are weighted heavier than likes for edge thickness.
            $edges[$k]['interaction_count'] += $kind === 'comments' ? $cnt * 2 : $cnt;
        };

        foreach ($commentCounts as $row) {
            $addInteraction($row->actor_id, $row->author_id, (int) $row->cnt, 'comments');
        }
        foreach ($likeCounts as $row) {
            $addInteraction($row->actor_id, $row->author_id, (int) $row->cnt, 'likes');
        }

        return response()->json([
            'me' => $myId,
            'window_days' => self::INTERACTION_WINDOW_DAYS,
            'truncated' => $firstDegree->count() > self::MAX_NODES,
            'nodes' => $nodes,
            'edges' => array_values($edges),
        ]);
    }
}
