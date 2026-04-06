<?php

namespace App\Domain\Onboarding;

use App\Models\UserOnboarding;

interface OnboardingRepositoryInterface
{
    /**
     * Check if a user already has an onboarding profile.
     */
    public function hasOnboarding(string $userUuid): bool;

    /**
     * Update or create a user's onboarding profile.
     */
    public function updateOrCreate(string $userUuid, array $data): UserOnboarding;
}
