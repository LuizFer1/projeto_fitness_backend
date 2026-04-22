<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AiPlan;
use App\Services\GroqService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AiMealPlanController extends Controller
{
    private $groqService;

    public function __construct(GroqService $groqService)
    {
        $this->groqService = $groqService;
    }

    private const DEFAULT_DISCLAIMER = 'Este plano alimentar é sugestivo e não substitui orientação de um nutricionista profissional.';

    private const PROMPT_DEFAULTS = [
        'restrictions'     => 'none',
        'food_preferences' => 'no preference',
        'food_dislikes'    => 'none',
        'routine_schedule' => 'not specified',
        'weight_kg'        => 'not specified',
        'height_cm'        => 'not specified',
        'age'              => 'not specified',
        'activity_level'   => 'moderate',
    ];

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

    private function formatPlanForDetail(AiPlan $plan): array
    {
        $c = $plan->content_json ?? [];

        return [
            'id'           => $plan->id,
            'status'       => $plan->status,
            'created_at'   => $plan->created_at,
            'content_json' => [
                'plan_goal'   => $c['plan_goal'] ?? null,
                'description' => $c['description'] ?? null,
                'total_kcal'  => $c['total_kcal'] ?? null,
                'macros'      => $c['macros'] ?? null,
                'meals'       => $this->normalizeMeals($c['meals'] ?? []),
                'disclaimer'  => $c['disclaimer'] ?? self::DEFAULT_DISCLAIMER,
            ],
        ];
    }

    private function normalizeMeals(array $meals): array
    {
        return collect($meals)
            ->map(fn ($meal) => $this->normalizeMeal($meal))
            ->values()
            ->all();
    }

    private function normalizeMeal(array $meal): array
    {
        $meal['id'] = $meal['id'] ?? (string) Str::uuid();
        $meal['ingredients'] = collect($meal['ingredients'] ?? [])
            ->map(fn ($ing) => $ing + ['id' => (string) Str::uuid()])
            ->values()
            ->all();

        return $meal;
    }

    private function buildGeneratePrompt(array $validated): string
    {
        $v = array_merge(self::PROMPT_DEFAULTS, array_filter($validated, fn ($x) => $x !== null && $x !== ''));

        return "You are an expert Sports Nutritionist specialized in creating personalized meal plans.\n"
            . "Create a complete daily meal plan for the user based on the following data:\n"
            . "- Primary Goal: {$validated['goal']}\n"
            . "- Dietary Restrictions / Allergies: {$v['restrictions']}\n"
            . "- Food Preferences (likes): {$v['food_preferences']}\n"
            . "- Food Dislikes: {$v['food_dislikes']}\n"
            . "- Meals per Day: {$validated['meals_per_day']}\n"
            . "- Daily Routine / Schedule: {$v['routine_schedule']}\n"
            . "- Weight: {$v['weight_kg']} kg\n"
            . "- Height: {$v['height_cm']} cm\n"
            . "- Age: {$v['age']}\n"
            . "- Activity Level: {$v['activity_level']}\n\n"
            . "Based on this data, calculate an appropriate daily calorie target and macro distribution.\n"
            . "Then create {$validated['meals_per_day']} meals distributed throughout the day with suggested times.\n"
            . "For each meal, list the food items (ingredients) with their quantities and individual macro/calorie counts.\n\n"
            . "IMPORTANT LANGUAGE RULES:\n"
            . "- All meal names, ingredient names, and descriptions MUST be in Brazilian Portuguese.\n"
            . "- Do NOT use English names.\n\n"
            . "IMPORTANT DISCLAIMER: Always include a note that this plan is suggestive and does NOT replace professional nutritionist guidance.\n\n"
            . "You MUST return the response EXCLUSIVELY in a valid JSON object. The structure MUST be exactly this:\n"
            . $this->promptJsonSchema();
    }

    private function promptJsonSchema(): string
    {
        return json_encode([
            'plan_goal'   => 'string',
            'description' => 'Uma breve descrição do plano alimentar',
            'total_kcal'  => 'number',
            'macros'      => ['p' => 'number (protein_g)', 'c' => 'number (carbs_g)', 'f' => 'number (fat_g)'],
            'meals'       => [[
                'time'        => '08:00',
                'name'        => 'Café da Manhã Energético',
                'type'        => 'breakfast (breakfast|lunch|snack|dinner)',
                'ingredients' => [[
                    'name'   => 'Ovos mexidos',
                    'amount' => '2 unidades',
                    'kcal'   => 140,
                    'macros' => ['p' => 12, 'c' => 2, 'f' => 10],
                ]],
            ]],
            'disclaimer'  => self::DEFAULT_DISCLAIMER,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    // ─── endpoints ──────────────────────────────────────────────────

    /**
     * POST /v1/plans/generate-meal
     */
    #[OA\Post(
        path: '/api/v1/plans/generate-meal',
        summary: 'Gerar plano alimentar com IA',
        description: 'Gera um plano alimentar personalizado via IA (Groq) com base nos dados do usuário. Criado com status draft.',
        tags: ['AI Meal Plans'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['goal', 'meals_per_day'],
                properties: [
                    new OA\Property(property: 'goal', type: 'string', example: 'muscle_gain'),
                    new OA\Property(property: 'restrictions', type: 'string', example: 'lactose'),
                    new OA\Property(property: 'food_preferences', type: 'string', example: 'frango, arroz'),
                    new OA\Property(property: 'food_dislikes', type: 'string', example: 'peixe'),
                    new OA\Property(property: 'meals_per_day', type: 'integer', example: 5),
                    new OA\Property(property: 'routine_schedule', type: 'string', example: 'acorda 6h, treina 18h'),
                    new OA\Property(property: 'weight_kg', type: 'number', example: 78),
                    new OA\Property(property: 'height_cm', type: 'number', example: 178),
                    new OA\Property(property: 'age', type: 'integer', example: 28),
                    new OA\Property(property: 'activity_level', type: 'string', enum: ['sedentary', 'light', 'moderate', 'active', 'very_active'], example: 'moderate'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Plano alimentar gerado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação ou falha na IA'),
        ]
    )]
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
    #[OA\Get(
        path: '/api/v1/plans/meals',
        summary: 'Listar planos alimentares',
        description: 'Retorna todos os planos alimentares do usuário (versão resumida).',
        tags: ['AI Meal Plans'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de planos alimentares'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
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
    #[OA\Get(
        path: '/api/v1/plans/meals/{id}',
        summary: 'Detalhar plano alimentar',
        description: 'Retorna o conteúdo completo de um plano alimentar (refeições e ingredientes).',
        tags: ['AI Meal Plans'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do plano', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalhes do plano'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
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
    #[OA\Patch(
        path: '/api/v1/plans/meals/{id}/activate',
        summary: 'Ativar plano alimentar',
        description: 'Marca o plano como ativo e desativa os demais planos nutricionais do usuário (status replaced).',
        tags: ['AI Meal Plans'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do plano', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plano ativado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
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
    #[OA\Patch(
        path: '/api/v1/plans/meals/{id}/archive',
        summary: 'Arquivar plano alimentar',
        description: 'Arquiva o plano alimentar do usuário (status archived).',
        tags: ['AI Meal Plans'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do plano', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plano arquivado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
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
    #[OA\Post(
        path: '/api/v1/plans/meals/{id}/regenerate',
        summary: 'Regenerar plano alimentar com ajustes',
        description: 'Gera uma nova versão do plano alimentar incorporando os ajustes solicitados. Cria um novo registro com status draft.',
        tags: ['AI Meal Plans'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do plano original', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['adjustment_note'],
                properties: [
                    new OA\Property(property: 'adjustment_note', type: 'string', example: 'Trocar frango por carne vermelha nas refeições principais.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Plano regenerado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação ou falha na IA'),
        ]
    )]
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
