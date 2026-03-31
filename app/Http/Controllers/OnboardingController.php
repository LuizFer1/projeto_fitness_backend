<?php

namespace App\Http\Controllers;

use App\Application\UseCases\Onboarding\SubmitOnboardingUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    private SubmitOnboardingUseCase $submitOnboardingUseCase;

    public function __construct(SubmitOnboardingUseCase $submitOnboardingUseCase)
    {
        $this->submitOnboardingUseCase = $submitOnboardingUseCase;
    }

    public function show(Request $request): JsonResponse
    {
        $onboarding = $request->user()->onboarding;

        return response()->json($onboarding);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gender'            => 'nullable|string|in:M,F,other,prefer_not_to_say',
            'age'               => 'nullable|integer|min:10|max:120',
            'height_cm'         => 'nullable|integer|min:100|max:250',
            'weight_kg'         => 'nullable|numeric|min:30|max:300',
            'body_fat_percent'  => 'nullable|numeric|min:3|max:60',
            'workouts_per_week' => 'nullable|integer|min:0|max:7',
            'work_style'        => 'nullable|string|in:white_collar,blue_collar,sedentary,moderate,active',
        ]);

        try {
            $onboarding = $this->submitOnboardingUseCase->execute($request->user()->uuid, $data);
            return response()->json($onboarding, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
            ], 400);
        }
    }
}
