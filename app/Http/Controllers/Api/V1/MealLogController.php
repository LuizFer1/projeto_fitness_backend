<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MealLog;
use App\Services\GroqService;
use App\Services\GamificationService;
use OpenApi\Attributes as OA;

class MealLogController extends Controller
{
    private $groqService;
    private $gamificationService;

    public function __construct(GroqService $groqService, GamificationService $gamificationService)
    {
        $this->groqService = $groqService;
        $this->gamificationService = $gamificationService;
    }

    #[OA\Post(
        path: '/api/v1/meals/analyze-text',
        summary: 'Analisa e salva uma refeição a partir de texto',
        description: 'O usuário envia o que comeu (em texto) e a IA estima macros, calorias e salva no histórico.',
        tags: ['Meals'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-03-15'),
                    new OA\Property(property: 'meal_type', type: 'string', example: 'lunch'),
                    new OA\Property(property: 'text_description', type: 'string', example: 'Comi 150g de frango, 200g de arroz e salada de alface.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Refeição salva com sucesso'),
        ]
    )]
    public function analyzeText(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'meal_type' => 'required|string|in:breakfast,snack,lunch,dinner,pre_workout,post_workout',
            'text_description' => 'required|string',
        ]);

        $user = $request->user() ?? \App\Models\User::first();
        $prompt = $this->buildTextPrompt($validated['text_description']);

        try {
            $aiResponse = $this->groqService->generateTextResponse(null, $prompt);
            $mealLog = $this->persistMealFromText($user, $validated, $aiResponse);
            $this->gamificationService->grantMealLoggedXp($user);

            return response()->json(['message' => 'Refeição registrada via IA.', 'log' => $mealLog], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Falha na IA: ' . $e->getMessage()], 422);
        }
    }

    private function buildTextPrompt(string $description): string
    {
        return "Você é um Nutricionista especialista. O usuário inseriu a seguinte refeição/texto:\n"
            . "{$description}\n\n"
            . "Calcule o valor nutricional total dessa refeição.\n"
            . "Você DEVE retornar a resposta EXCLUSIVAMENTE em um formato JSON válido, sem marcações markdown. Estrutura exigida:\n"
            . "{\"calorias_totais\": 520, \"macros_totais\": {\"proteinas_g\": 35.5, \"carboidratos_g\": 45.0, \"gorduras_g\": 20.2}, \"feedback_breve\": \"texto\", \"itens_detalhados\": [{\"nome\": \"Frango\", \"quantidadeDada\": \"100g\", \"calorias\": 165}]}";
    }

    private function persistMealFromText($user, array $validated, array $aiResponse): MealLog
    {
        $macros = $aiResponse['macros_totais'] ?? [];

        return MealLog::create([
            'user_id'           => $user->id,
            'date'              => $validated['date'],
            'meal_type'         => $validated['meal_type'],
            'calories_consumed' => $aiResponse['calorias_totais'] ?? 0,
            'protein_g'         => $macros['proteinas_g'] ?? 0,
            'carbs_g'           => $macros['carboidratos_g'] ?? 0,
            'fat_g'             => $macros['gorduras_g'] ?? 0,
            'user_note'         => $validated['text_description'],
            'ai_feedback'       => $aiResponse['feedback_breve'] ?? null,
            'items_json'        => $aiResponse['itens_detalhados'] ?? [],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/meals/analyze-image',
        summary: 'Analisa e salva uma refeição a partir de uma FOTO',
        description: 'O usuário envia a foto do prato em base64 e a IA usa visão computacional para estimar macros, calorias e itens.',
        tags: ['Meals'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-03-15'),
                    new OA\Property(property: 'meal_type', type: 'string', example: 'dinner'),
                    new OA\Property(property: 'image_base64', type: 'string', example: 'base64_string_here'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Refeição computada e salva via foto'),
        ]
    )]
    public function analyzeImage(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'meal_type' => 'required|string|in:breakfast,snack,lunch,dinner,pre_workout,post_workout',
            'image_base64' => 'required|string',
        ]);

        $user = $request->user() ?? \App\Models\User::first();
        $base64 = preg_replace('#^data:image/[^;]+;base64,#', '', $validated['image_base64']);
        $prompt = $this->buildImagePrompt();

        try {
            $aiResponse = $this->groqService->generateVisionResponse(null, $prompt, $base64);
            $mealLog = $this->persistMealFromImage($user, $validated, $aiResponse);
            $this->gamificationService->grantMealLoggedXp($user);

            return response()->json(['message' => 'Refeição registrada por imagem via IA.', 'log' => $mealLog], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Falha na IA Vision: ' . $e->getMessage()], 422);
        }
    }

    private function buildImagePrompt(): string
    {
        return "Você é um Nutricionista especialista com visão computacional. Analise a imagem desta refeição rigorosamente.\n"
            . "Identifique os alimentos, estime sua porção, calorias e macronutrientes totais.\n"
            . "Você DEVE retornar a resposta EXCLUSIVAMENTE em formato JSON, sem marcações markdown. Estrutura exigida:\n"
            . "{\"calorias_totais_estimadas\": 600, \"macros_estimados\": {\"proteinas_g\": 40, \"carboidratos_g\": 60, \"gorduras_g\": 25}, \"feedback_breve\": \"Analise visual curta\", \"itens_identificados\": [{\"nome_estimado\": \"Arroz\", \"quantidade_estimada_gramas\": 150, \"calorias_estimadas\": 170}]}";
    }

    private function persistMealFromImage($user, array $validated, array $aiResponse): MealLog
    {
        $macros = $aiResponse['macros_estimados'] ?? [];

        return MealLog::create([
            'user_id'           => $user->id,
            'date'              => $validated['date'],
            'meal_type'         => $validated['meal_type'],
            'calories_consumed' => $aiResponse['calorias_totais_estimadas'] ?? 0,
            'protein_g'         => $macros['proteinas_g'] ?? 0,
            'carbs_g'           => $macros['carboidratos_g'] ?? 0,
            'fat_g'             => $macros['gorduras_g'] ?? 0,
            'user_note'         => 'Identificacao via foto',
            'ai_feedback'       => $aiResponse['feedback_breve'] ?? null,
            'items_json'        => $aiResponse['itens_identificados'] ?? [],
        ]);
    }
}
