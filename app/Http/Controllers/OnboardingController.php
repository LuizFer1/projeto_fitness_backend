<?php

namespace App\Http\Controllers;

use App\Application\UseCases\Onboarding\GetOnboardingAction;
use App\Application\UseCases\Onboarding\SubmitOnboardingUseCase;
use App\DTOs\Onboarding\OnboardingDTO;
use App\Http\Requests\Onboarding\StoreOnboardingRequest;
use App\Http\Resources\OnboardingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private GetOnboardingAction $getOnboardingAction,
        private SubmitOnboardingUseCase $submitOnboardingUseCase,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/onboarding",
     *     summary="Get the current user's onboarding data",
     *     tags={"Onboarding"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Onboarding record"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request): OnboardingResource|JsonResponse
    {
        $onboarding = $this->getOnboardingAction->execute($request->user());

        if (!$onboarding) {
            return response()->json(['data' => null]);
        }

        return new OnboardingResource($onboarding);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/onboarding",
     *     summary="Submit or update onboarding data",
     *     tags={"Onboarding"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="gender", type="string", enum={"M","F","other","prefer_not_to_say"}),
     *             @OA\Property(property="age", type="integer", example=28),
     *             @OA\Property(property="height_cm", type="integer", example=178),
     *             @OA\Property(property="weight_kg", type="number", format="float", example=75.5),
     *             @OA\Property(property="body_fat_percent", type="number", format="float", example=18.2),
     *             @OA\Property(property="workouts_per_week", type="integer", example=4),
     *             @OA\Property(property="work_style", type="string", enum={"white_collar","blue_collar","sedentary","moderate","active"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Onboarding saved"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     */
    public function store(StoreOnboardingRequest $request): JsonResponse
    {
        $dto = OnboardingDTO::fromArray($request->validated());
        $onboarding = $this->submitOnboardingUseCase->execute($request->user()->uuid, $dto);

        return (new OnboardingResource($onboarding))
            ->response()
            ->setStatusCode(201);
    }
}
