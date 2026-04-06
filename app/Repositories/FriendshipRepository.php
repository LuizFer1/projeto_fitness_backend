<?php

namespace App\Repositories;

use App\Models\Friendship;
use App\Models\User;

class FriendshipRepository
{
    /**
     * Returns the friendship status between two users.
     * Possible values: 'none', 'accepted', 'pending', 'blocked'
     */
    public function statusBetween(User $userA, User $userB): string
    {
        $friendship = Friendship::where(function ($q) use ($userA, $userB) {
            $q->where('user_uuid', $userA->uuid)
              ->where('friend_uuid', $userB->uuid);
        })->orWhere(function ($q) use ($userA, $userB) {
            $q->where('user_uuid', $userB->uuid)
              ->where('friend_uuid', $userA->uuid);
        })->first();

        return $friendship?->status ?? 'none';
    }

    public function areFriends(User $userA, User $userB): bool
    {
        return $this->statusBetween($userA, $userB) === 'accepted';
    }

    public function isBlocked(User $userA, User $userB): bool
    {
        return $this->statusBetween($userA, $userB) === 'blocked';
    }
}
