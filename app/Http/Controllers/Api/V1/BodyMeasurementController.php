<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BodyMeasurement;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BodyMeasurementController extends Controller
{
    public function __construct(private GamificationService $gamification)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->query('limit', 30), 365);

        $rows = BodyMeasurement::where('user_id', $user->id)
            ->orderByDesc('date')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'                   => 'nullable|date',
            'weight_kg'              => 'required|numeric|min:30|max:300',
            'body_fat_pct'           => 'nullable|numeric|min:3|max:60',
            'muscle_mass_kg'         => 'nullable|numeric|min:10|max:200',
            'waist_circumference_cm' => 'nullable|numeric|min:30|max:250',
            'hip_circumference_cm'   => 'nullable|numeric|min:30|max:250',
            'arm_circumference_cm'   => 'nullable|numeric|min:10|max:100',
        ]);

        $user = $request->user();
        $date = $data['date'] ?? now()->toDateString();

        $bmi = null;
        $heightCm = optional($user->onboarding)->height_cm;
        if ($heightCm) {
            $heightM = $heightCm / 100;
            $bmi = round($data['weight_kg'] / ($heightM * $heightM), 2);
        }

        $measurement = BodyMeasurement::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            array_merge($data, ['bmi' => $bmi, 'date' => $date])
        );

        $xp = $this->gamification->grantWeightLoggedXp($user);

        return response()->json([
            'measurement' => $measurement,
            'xp_gained'   => $xp?->xp_gained ?? 0,
        ], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $measurement = BodyMeasurement::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $measurement->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
