<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DietAdjustment;
use App\Services\Diet\DietEngineService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NutritionController extends Controller
{
    public function __construct(private DietEngineService $dietEngine) {}

    #[OA\Get(
        path: '/api/v1/nutrition/today',
        summary: 'Resumo nutricional de hoje',
        description: 'Retorna metas, consumido, delta calórico e ajustes de dieta pendentes para hoje.',
        tags: ['Nutrition'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Resumo nutricional do dia')]
    )]
    public function today(Request $request): JsonResponse
    {
        $user  = $request->user();
        $daily = $this->dietEngine->getTodaySummary($user);
        $today = Carbon::now($user->timezone ?? 'UTC')->toDateString();

        $pendingAdjustments = DietAdjustment::where('user_id', $user->id)
            ->where('target_date', $today)
            ->whereNull('applied_at')
            ->get(['delta_kcal', 'delta_protein_g', 'delta_carbs_g', 'delta_fat_g', 'mode']);

        $compensationKcal = $pendingAdjustments->sum('delta_kcal');

        return response()->json([
            'date'                 => $today,
            'goals' => [
                'calories'  => $daily->calories_goal,
                'protein_g' => $daily->protein_goal_g,
                'carbs_g'   => $daily->carbs_goal_g,
                'fat_g'     => $daily->fat_goal_g,
            ],
            'consumed' => [
                'calories'  => $daily->calories_consumed,
                'protein_g' => $daily->protein_consumed_g,
                'carbs_g'   => $daily->carbs_consumed_g,
                'fat_g'     => $daily->fat_consumed_g,
            ],
            'delta_kcal'          => $daily->delta_kcal,
            'adjustment_ratio'    => (float) $daily->adjustment_ratio,
            'dilution_active'     => $daily->dilution_active,
            'compensation_kcal'   => $compensationKcal,
            'remaining_calories'  => max(0, ($daily->calories_goal - $daily->calories_consumed) - $compensationKcal),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/diet-adjustments',
        summary: 'Listar ajustes de dieta do dia',
        tags: ['Nutrition'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Ajustes de dieta para a data informada')]
    )]
    public function adjustments(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->query('date', Carbon::now($user->timezone ?? 'UTC')->toDateString());

        $adjustments = DietAdjustment::where('user_id', $user->id)
            ->where('target_date', $date)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'date'        => $date,
            'adjustments' => $adjustments,
        ]);
    }
}
