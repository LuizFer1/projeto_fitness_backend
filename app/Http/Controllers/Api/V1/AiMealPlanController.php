<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AiPlan;
use App\Services\GroqService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiMealPlanController extends Controller
{
    private $groqService;

    public function __construct(GroqService $groqService)
    {
        $this->groqService = $groqService;
    }

    // ─── helpers ────────────────────────────────────────────────────

    /**
     * Format a plan for the LIST endpoint (lightweight).
     */
    private function formatPlanForList(AiPlan $plan): array
    {
        $c = $plan->content_json ?? [];

        return [
            'id'           => $plan->id,
            'status'       => $plan->status,
            'goal'         => $c['plan_goal'] ?? null,
            'total_kcal'   => $c['total_kcal'] ?? null,
            'locked'       => false,
            'image'        => null,
            'content_json' => [
                'plan_goal'  => $c['plan_goal'] ?? null,
                'total_kcal' => $c['total_kcal'] ?? null,
                'macros'     => $c['macros'] ?? null,
            ],
        ];
    }

    /**
     * Format a plan for the DETAIL endpoint (full meals + ingredients).
     */
    private function formatPlanForDetail(AiPlan $plan): array
    {
        $c = $plan->content_json ?? [];

        // Ensure every meal and ingredient has an id
        $meals = collect($c['meals'] ?? [])->map(function ($meal) {
            $meal['id'] = $meal['id'] ?? (string) Str::uuid();

            $meal['ingredients'] = collect($meal['ingredients'] ?? [])->map(function ($ing) {
                $ing['id'] = $ing['id'] ?? (string) Str::uuid();
                return $ing;
            })->values()->all();

            return $meal;
        })->values()->all();

        return [
            'id'           => $plan->id,
            'status'       => $plan->status,
            'created_at'   => $plan->created_at,
            'content_json' => [
                'plan_goal'    => $c['plan_goal'] ?? null,
                'description'  => $c['description'] ?? null,
                'total_kcal'   => $c['total_kcal'] ?? null,
                'macros'       => $c['macros'] ?? null,
                'meals'        => $meals,
                'disclaimer'   => $c['disclaimer'] ?? 'Este plano alimentar é sugestivo e não substitui orientação de um nutricionista profissional.',
            ],
        ];
    }

    // ─── prompt builder ─────────────────────────────────────────────

    private function buildGeneratePrompt(array $validated): string
    {
        return "You are an expert Sports Nutritionist specialized in creating personalized meal plans.\n"
            . "Create a complete daily meal plan for the user based on the following data:\n"
            . "- Primary Goal: {$validated['goal']}\n"
            . "- Dietary Restrictions / Allergies: " . ($validated['restrictions'] ?? 'none') . "\n"
            . "- Food Preferences (likes): " . ($validated['food_preferences'] ?? 'no preference') . "\n"
            . "- Food Dislikes: " . ($validated['food_dislikes'] ?? 'none') . "\n"
            . "- Meals per Day: {$validated['meals_per_day']}\n"
            . "- Daily Routine / Schedule: " . ($validated['routine_schedule'] ?? 'not specified') . "\n"
            . "- Weight: " . ($validated['weight_kg'] ?? 'not specified') . " kg\n"
            . "- Height: " . ($validated['height_cm'] ?? 'not specified') . " cm\n"
            . "- Age: " . ($validated['age'] ?? 'not specified') . "\n"
            . "- Activity Level: " . ($validated['activity_level'] ?? 'moderate') . "\n\n"
            . "Based on this data, calculate an appropriate daily calorie target and macro distribution.\n"
            . "Then create {$validated['meals_per_day']} meals distributed throughout the day with suggested times.\n"
            . "For each meal, list the food items (ingredients) with their quantities and individual macro/calorie counts.\n\n"
            . "IMPORTANT LANGUAGE RULES:\n"
            . "- All meal names, ingredient names, and descriptions MUST be in Brazilian Portuguese.\n"
            . "- Do NOT use English names.\n\n"
            . "IMPORTANT DISCLAIMER: Always include a note that this plan is suggestive and does NOT replace professional nutritionist guidance.\n\n"
            . "You MUST return the response EXCLUSIVELY in a valid JSON object. The structure MUST be exactly this:\n"
            . json_encode([
                'plan_goal'  => 'string',
                'description' => 'Uma breve descrição do plano alimentar',
                'total_kcal' => 'number',
                'macros'     => ['p' => 'number (protein_g)', 'c' => 'number (carbs_g)', 'f' => 'number (fat_g)'],
                'meals'      => [
                    [
                        'time'        => '08:00',
                        'name'        => 'Café da Manhã Energético',
                        'type'        => 'breakfast (breakfast|lunch|snack|dinner)',
                        'ingredients' => [
                            [
                                'name'   => 'Ovos mexidos',
                                'amount' => '2 unidades',
                                'kcal'   => 140,
                                'macros' => ['p' => 12, 'c' => 2, 'f' => 10],
                            ],
                        ],
                    ],
                ],
                'disclaimer' => 'Este plano alimentar é sugestivo e não substitui orientação de um nutricionista profissional.',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    // ─── endpoints ──────────────────────────────────────────────────

    /**
     * POST /v1/plans/generate-meal
     */
    public function generateMealPlan(Request $request)
    {
        $validated = $request->validate([
            'goal'             => 'required|string|max:100',
            'restrictions'     => 'nullable|string|max:500',
            'food_preferences' => 'nullable|string|max:500',
            'food_dislikes'    => 'nullable|string|max:500',
            'meals_per_day'    => 'required|integer|min:2|max:8',
            'routine_schedule' => 'nullable|string|max:500',
            'weight_kg'        => 'nullable|numeric|min:30|max:300',
            'height_cm'        => 'nullable|numeric|min:100|max:250',
            'age'              => 'nullable|integer|min:10|max:100',
            'activity_level'   => 'nullable|string|in:sedentary,light,moderate,active,very_active',
        ]);

        $user  = $request->user() ?? \App\Models\User::first();
        $prompt = $this->buildGeneratePrompt($validated);

        try {
            $aiResponse = $this->groqService->generateTextResponse(null, $prompt);

            DB::transaction(function () use ($user, $validated, $aiResponse, $prompt) {
                AiPlan::create([
                    'user_id'           => $user->id,
                    'type'              => 'nutritional',
                    'version'           => 1,
                    'status'            => 'draft',
                    'content_json'      => $aiResponse,
                    'generation_reason' => "Goal: {$validated['goal']}, Meals/day: {$validated['meals_per_day']}, Restrictions: " . ($validated['restrictions'] ?? 'none'),
                    'context_prompt'    => $prompt,
                    'valid_from'        => Carbon::today(),
                    'valid_until'       => Carbon::today()->addWeeks(4),
                ]);
            });

            return response()->json([
                'message' => 'Meal plan generated successfully!',
            ], 201);

        } catch (\Throwable $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status > 599) {
                $status = 422;
            }
            return response()->json(['error' => 'Failed to generate meal plan: ' . $e->getMessage()], $status);
        }
    }

    /**
     * GET /v1/plans/meals
     */
    public function index(Request $request)
    {
        $user = $request->user() ?? \App\Models\User::first();

        $plans = AiPlan::where('user_id', $user->id)
            ->where('type', 'nutritional')
            ->orderByDesc('created_at')
            ->get();

        $data = $plans->map(fn (AiPlan $p) => $this->formatPlanForList($p))->values();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /v1/plans/meals/{id}
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user() ?? \App\Models\User::first();

        $plan = AiPlan::where('user_id', $user->id)
            ->where('id', $id)
            ->where('type', 'nutritional')
            ->firstOrFail();

        return response()->json(['data' => $this->formatPlanForDetail($plan)]);
    }

    /**
     * PATCH /v1/plans/meals/{id}/activate
     */
    public function activate(Request $request, string $id)
    {
        $user = $request->user() ?? \App\Models\User::first();

        AiPlan::where('user_id', $user->id)
            ->where('type', 'nutritional')
            ->where('status', 'active')
            ->update(['status' => 'replaced']);

        $plan = AiPlan::where('user_id', $user->id)
            ->where('id', $id)
            ->where('type', 'nutritional')
            ->firstOrFail();

        $plan->update(['status' => 'active']);

        return response()->json(['message' => 'Meal plan activated!', 'data' => $this->formatPlanForList($plan->fresh())]);
    }

    /**
     * PATCH /v1/plans/meals/{id}/archive
     */
    public function archive(Request $request, string $id)
    {
        $user = $request->user() ?? \App\Models\User::first();

        $plan = AiPlan::where('user_id', $user->id)
            ->where('id', $id)
            ->where('type', 'nutritional')
            ->firstOrFail();

        $plan->update(['status' => 'archived']);

        return response()->json(['message' => 'Meal plan archived.', 'data' => $this->formatPlanForList($plan->fresh())]);
    }

    /**
     * POST /v1/plans/meals/{id}/regenerate
     */
    public function regenerate(Request $request, string $id)
    {
        $validated = $request->validate([
            'adjustment_note' => 'required|string|max:1000',
        ]);

        $user = $request->user() ?? \App\Models\User::first();

        $original = AiPlan::where('user_id', $user->id)
            ->where('id', $id)
            ->where('type', 'nutritional')
            ->firstOrFail();

        $originalContent = $original->content_json;

        $prompt = "You are an expert Sports Nutritionist. The user previously received the following meal plan:\n"
            . json_encode($originalContent, JSON_UNESCAPED_UNICODE) . "\n\n"
            . "The user is requesting the following adjustments:\n"
            . $validated['adjustment_note'] . "\n\n"
            . "Generate a NEW improved meal plan incorporating these changes.\n"
            . "You MUST keep the EXACT same JSON structure as the original plan (plan_goal, description, total_kcal, macros with p/c/f, meals with ingredients, disclaimer).\n"
            . "All text MUST be in Brazilian Portuguese.\n"
            . "IMPORTANT: Include a disclaimer that this plan is suggestive and does NOT replace professional nutritionist guidance.\n\n"
            . "You MUST return the response EXCLUSIVELY in a valid JSON object, without markdown formatting.";

        try {
            $aiResponse = $this->groqService->generateTextResponse(null, $prompt);

            AiPlan::create([
                'user_id'           => $user->id,
                'type'              => 'nutritional',
                'version'           => $original->version + 1,
                'status'            => 'draft',
                'content_json'      => $aiResponse,
                'generation_reason' => "Regenerated from plan {$original->id}. Adjustment: {$validated['adjustment_note']}",
                'context_prompt'    => $prompt,
                'valid_from'        => Carbon::today(),
                'valid_until'       => Carbon::today()->addWeeks(4),
            ]);

            return response()->json([
                'message' => 'Meal plan regenerated with adjustments!',
            ], 200);

        } catch (\Throwable $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status > 599) {
                $status = 422;
            }
            return response()->json(['error' => 'Failed to regenerate meal plan: ' . $e->getMessage()], $status);
        }
    }
}
