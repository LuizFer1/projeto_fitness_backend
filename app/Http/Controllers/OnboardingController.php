<?php

namespace App\Http\Controllers;

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
        if ($this->userHasOnboarding($request)) {
            return response()->json([
                'message' => 'Onboarding already exists for this user.',
            ], 400);
        }

        $data = $request->validate([
            'gender'            => 'nullable|string|in:M,F,other,prefer_not_to_say',
            'age'               => 'nullable|integer|min:10|max:120',
            'height_cm'         => 'nullable|integer|min:100|max:250',
            'weight_kg'         => 'nullable|numeric|min:30|max:300',
            'body_fat_percent'  => 'nullable|numeric|min:3|max:60',
            'workouts_per_week' => 'nullable|integer|min:0|max:7',
            'work_style'        => 'nullable|string|in:white_collar,blue_collar,sedentary,moderate,active',
        ]);

        $userId = $request->user()->id;

        // Map front-end strings to database enums
        $mappedGender = 'prefer_not_to_say';
        if (($data['gender'] ?? '') === 'M') $mappedGender = 'male';
        elseif (($data['gender'] ?? '') === 'F') $mappedGender = 'female';
        elseif (isset($data['gender'])) $mappedGender = $data['gender'];

        $mappedWorkStyle = 'sedentary';
        if (($data['work_style'] ?? '') === 'white_collar') $mappedWorkStyle = 'light';
        elseif (($data['work_style'] ?? '') === 'blue_collar') $mappedWorkStyle = 'active';
        elseif (isset($data['work_style'])) $mappedWorkStyle = $data['work_style'];

        $bmr = null;
        if (!empty($data['gender']) && !empty($data['age']) && !empty($data['weight_kg']) && !empty($data['height_cm'])) {
            $w = (float) $data['weight_kg'];
            $h = (int) $data['height_cm'];
            $a = (int) $data['age'];
            
            if ($mappedGender === 'male') {
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

            if ($mappedWorkStyle === 'active' || $mappedWorkStyle === 'very_active') {
                $activityMultiplier += 0.15;
            }

            $tdee = (int) round($bmr * $activityMultiplier);
        }

        $onboarding = DB::transaction(function () use ($userId, $data, $bmr, $tdee, $mappedGender, $mappedWorkStyle) {
            $onb = UserOnboarding::updateOrCreate(
                ['user_id' => $userId],
                [
                    'gender' => $mappedGender,
                    'age' => $data['age'] ?? 0,
                    'height_cm' => $data['height_cm'] ?? 0,
                    'weight_kg' => $data['weight_kg'] ?? 0,
                    'body_fat_pct' => $data['body_fat_percent'] ?? null,
                    'exercise_frequency' => $data['workouts_per_week'] ?? 0,
                    'work_style' => $mappedWorkStyle,
                    'bmr' => $bmr ? round($bmr, 2) : null,
                ]
            );

            // Omit NutritionDaily update for now since the model doesn't exist yet

            return $onb;
        });

        return response()->json($onboarding, 201);
    }

    private function userHasOnboarding(Request $request): bool
    {
        return UserOnboarding::where('user_id', $request->user()->id)->exists();
    }
}
