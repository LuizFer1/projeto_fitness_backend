<?php

namespace App\Http\Controllers\Api\V1\PublicProfile;

use App\Application\UseCases\PublicProfile\GetPublicAchievementsUseCase;
use App\Application\UseCases\PublicProfile\GetPublicGoalsUseCase;
use App\Application\UseCases\PublicProfile\GetPublicProfileUseCase;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProfile\PublicAchievementResource;
use App\Http\Resources\PublicProfile\PublicGoalResource;
use App\Http\Resources\PublicProfile\PublicProfileResource;
use App\Models\User;
use App\Repositories\FriendshipRepository;
use Illuminate\Http\Request;

class PublicProfileController extends Controller
{
    public function show(
        Request $request,
        User $username,
        GetPublicProfileUseCase $useCase,
        FriendshipRepository $friendshipRepo,
    ) {
        $authUser = $request->user();
        $target = $useCase->execute($username, $authUser);

        if (! $target) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Usuário não encontrado',
                    'details' => (object) [],
                ],
            ], 404);
        }

        $status = $friendshipRepo->statusBetween($authUser, $target);

        return (new PublicProfileResource($target))
            ->withFriendshipStatus($status);
    }

    public function achievements(
        Request $request,
        User $username,
        GetPublicAchievementsUseCase $useCase,
    ) {
        $result = $useCase->execute($username, $request->user());

        if ($result === null) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Usuário não encontrado',
                    'details' => (object) [],
                ],
            ], 404);
        }

        return PublicAchievementResource::collection($result);
    }

    public function goals(
        Request $request,
        User $username,
        GetPublicGoalsUseCase $useCase,
    ) {
        $result = $useCase->execute($username, $request->user());

        if ($result === null) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Usuário não encontrado',
                    'details' => (object) [],
                ],
            ], 404);
        }

        return PublicGoalResource::collection($result);
    }
}
