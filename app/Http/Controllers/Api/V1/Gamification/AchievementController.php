<?php

namespace App\Http\Controllers\Api\V1\Gamification;

use App\Application\UseCases\Gamification\ListAchievementsUseCase;
use App\Http\Controllers\Controller;
use App\Http\Resources\Gamification\AchievementResource;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index(Request $request, ListAchievementsUseCase $useCase)
    {
        $achievements = $useCase->execute($request->user());

        return AchievementResource::collection($achievements);
    }
}
