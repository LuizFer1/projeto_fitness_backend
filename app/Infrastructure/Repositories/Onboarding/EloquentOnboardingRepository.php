<?php

namespace App\Infrastructure\Repositories\Onboarding;

use App\Domain\Onboarding\OnboardingRepositoryInterface;
use App\Models\UserOnboarding;

class EloquentOnboardingRepository implements OnboardingRepositoryInterface
{
    public function hasOnboarding(string $userUuid): bool
    {
        return UserOnboarding::where('user_uuid', $userUuid)->exists();
    }

    public function updateOrCreate(string $userUuid, array $data): UserOnboarding
    {
        return UserOnboarding::updateOrCreate(
            ['user_uuid' => $userUuid],
            $data
        );
    }
}
