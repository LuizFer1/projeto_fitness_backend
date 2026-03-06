<?php

namespace App\Http\Controllers;

use App\Models\NutritionDaily;
use App\Models\UserOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $onboarding = $request->user()->onboarding;

        return response()->json($onboarding);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->UserHasOnboarding($request)) {
            return response()->json([
                'message' => 'Onboarding already exists for this user.',
            ], 400);
        }

        $data = $request->validate([
            'gender'            => 'nullable|string|in:M,F',
            'age'               => 'nullable|integer|min:10|max:120',
            'height_cm'         => 'nullable|integer|min:100|max:250',
            'weight_kg'         => 'nullable|numeric|min:30|max:300',
            'body_fat_percent'  => 'nullable|numeric|min:3|max:60',
            'workouts_per_week' => 'nullable|integer|min:0|max:7',
            'work_style'        => 'nullable|string|in:white_collar,blue_collar,sedentary,moderate,active',
        ]);

        $userUuid = $request->user()->uuid;



        $bmr = null;
        if (!empty($data['gender']) && !empty($data['age']) && !empty($data['weight_kg']) && !empty($data['height_cm'])) {
            $w = (float) $data['weight_kg'];
            $h = (int) $data['height_cm'];
            $a = (int) $data['age'];
            
            if ($data['gender'] === 'M') {
                $bmr = (10 * $w) + (6.25 * $h) - (5 * $a) + 5;
            } else {
                $bmr = (10 * $w) + (6.25 * $h) - (5 * $a) - 161;
            }
        }

        $tdee = null;
        if ($bmr) {
            $activityMultiplier = 1.2;
            
            $workouts = (int) ($data['workouts_per_week'] ?? 0);
            if ($workouts >= 6) {
                $activityMultiplier = 1.725;
            } elseif ($workouts >= 3) {
                $activityMultiplier = 1.55;
            } elseif ($workouts >= 1) {
                $activityMultiplier = 1.375;
            }

            if (($data['work_style'] ?? '') === 'active' || ($data['work_style'] ?? '') === 'blue_collar') {
                $activityMultiplier += 0.15;
            }

            $tdee = (int) round($bmr * $activityMultiplier);
        }

        $onboarding = DB::transaction(function () use ($userUuid, $data, $bmr, $tdee) {
            $onb = UserOnboarding::updateOrCreate(
                ['user_uuid' => $userUuid],
                array_merge($data, [
                    'completed' => true,
                    'bmr'       => $bmr ? (int) round($bmr) : null,
                ])
            );

            if ($tdee) {
                NutritionDaily::firstOrCreate(
                    ['user_uuid' => $userUuid, 'day' => now()->toDateString()],
                    ['calories_goal' => $tdee]
                )->update(['calories_goal' => $tdee]);
            }

            return $onb;
        });

        return response()->json($onboarding, 201);
    }

    private function UserHasOnboarding(Request $request): bool
    {
        return UserOnboarding::where('user_uuid', $request->user()->uuid)->exists();
    }
}
