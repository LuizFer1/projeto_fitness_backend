<?php

namespace App\Http\Controllers\Api\V1\PublicProfile;

use App\Application\UseCases\PublicProfile\GetPublicProfileUseCase;
use App\Http\Controllers\Controller;
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
}
