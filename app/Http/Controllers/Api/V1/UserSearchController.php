<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserSearchController extends Controller
{
    /**
     * GET /v1/users/search?q=term — search users by name or username.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:3',
        ]);

        $user = $request->user();
        $term = '%' . $data['q'] . '%';

        // Get IDs of users who have blocked the authenticated user or whom the authenticated user has blocked
        $blockedIds = Friendship::blocked()
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                  ->orWhere('addressee_id', $user->id);
            })
            ->get()
            ->flatMap(fn ($f) => [$f->requester_id, $f->addressee_id])
            ->reject(fn ($id) => $id === $user->id)
            ->values()
            ->toArray();

        $results = DB::table('users')
            ->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', $term)
                  ->orWhere('username', 'LIKE', $term);
            })
            ->where('id', '!=', $user->id)
            ->whereNotIn('id', $blockedIds)
            ->select('id', 'username', 'name', 'last_name', 'avatar_url')
            ->paginate(20);

        // Get all friendships between auth user and result users
        $resultIds = collect($results->items())->pluck('id')->toArray();

        $friendships = Friendship::where(function ($q) use ($user, $resultIds) {
            $q->where('requester_id', $user->id)->whereIn('addressee_id', $resultIds);
        })->orWhere(function ($q) use ($user, $resultIds) {
            $q->whereIn('requester_id', $resultIds)->where('addressee_id', $user->id);
        })->get()->keyBy(function ($f) use ($user) {
            return $f->requester_id === $user->id ? $f->addressee_id : $f->requester_id;
        });

        $results->through(function ($item) use ($friendships) {
            $friendship = $friendships->get($item->id);

            return [
                'id' => $item->id,
                'username' => $item->username,
                'name' => $item->name,
                'last_name' => $item->last_name,
                'avatar_url' => $item->avatar_url,
                'is_friend' => $friendship && $friendship->status === 'accepted',
                'friendship_status' => $friendship?->status,
            ];
        });

        return response()->json($results);
    }
}
