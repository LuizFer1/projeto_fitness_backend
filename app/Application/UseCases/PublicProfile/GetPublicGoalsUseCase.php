<?php

namespace App\Application\UseCases\PublicProfile;

use App\Models\User;
use App\Repositories\FriendshipRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetPublicGoalsUseCase
{
    public function __construct(
        private FriendshipRepository $friendshipRepository,
    ) {}

    public function execute(User $target, User $authUser): ?LengthAwarePaginator
    {
        if ($this->friendshipRepository->isBlocked($authUser, $target)) {
            return null;
        }

        $areFriends = $this->friendshipRepository->areFriends($authUser, $target);

        return $target->goals()
            ->where('status', '!=', 'archived')
            ->where(function ($query) use ($areFriends) {
                $query->where('visibility', 'public');
                if ($areFriends) {
                    $query->orWhere('visibility', 'friends');
                }
            })
            ->orderByDesc('created_at')
            ->paginate(15);
    }
}
