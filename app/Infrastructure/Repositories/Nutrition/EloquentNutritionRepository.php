<?php

namespace App\Infrastructure\Repositories\Nutrition;

use App\Domain\Nutrition\NutritionRepositoryInterface;
use App\Models\NutritionDaily;

class EloquentNutritionRepository implements NutritionRepositoryInterface
{
    public function createOrUpdateDailyGoal(string $userUuid, string $date, int $tdee): NutritionDaily
    {
        $daily = NutritionDaily::firstOrCreate(
            ['user_id' => $userUuid, 'day' => $date],
            ['calories_goal' => $tdee]
        );
        
        $daily->update(['calories_goal' => $tdee]);
        
        return $daily;
    }
}
