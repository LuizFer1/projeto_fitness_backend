<?php

namespace App\Application\UseCases\PublicProfile;

use App\Models\User;
use App\Repositories\FriendshipRepository;

class GetPublicProfileUseCase
{
    public function __construct(
        private FriendshipRepository $friendshipRepository,
    ) {}

    /**
     * Returns the target user with counts loaded, or null if blocked.
     */
    public function execute(User $target, User $authUser): ?User
    {
        if ($this->friendshipRepository->isBlocked($authUser, $target)) {
            return null;
        }

        $target->loadCount([
            'achievements',
        ]);

        // goals_completed_count — set to 0 until Goals module is implemented
        $target->goals_completed_count = 0;

        return $target;
    }
}
