<?php

namespace App\Application\UseCases\PublicProfile;

use App\Models\User;
use App\Repositories\FriendshipRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetPublicAchievementsUseCase
{
    public function __construct(
        private FriendshipRepository $friendshipRepository,
    ) {}

    public function execute(User $target, User $authUser): ?LengthAwarePaginator
    {
        if ($this->friendshipRepository->isBlocked($authUser, $target)) {
            return null;
        }

        return $target->achievements()
            ->with('achievement')
            ->orderByDesc('unlocked_at')
            ->paginate(30);
    }
}
