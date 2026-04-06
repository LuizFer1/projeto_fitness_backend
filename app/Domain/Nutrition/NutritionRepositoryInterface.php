<?php

namespace App\Domain\Nutrition;

use App\Models\NutritionDaily;

interface NutritionRepositoryInterface
{
    /**
     * Create or update the daily nutrition goal for a specific user and date.
     */
    public function createOrUpdateDailyGoal(string $userUuid, string $date, int $tdee): NutritionDaily;
}
