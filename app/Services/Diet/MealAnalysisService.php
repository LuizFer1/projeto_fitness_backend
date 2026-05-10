<?php

namespace App\Services\Diet;

use App\Application\UseCases\Nutrition\RedistributeMacrosUseCase;
use App\Models\MealLog;
use App\Models\User;
use App\Services\GamificationService;
use App\Services\GroqService;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for meal analysis (text or image) → persistence → side effects.
 *
 * Centralises:
 *   • prompt building (so prompt tweaks happen in one place)
 *   • persistence of MealLog rows
 *   • dispatching gamification XP, diet engine recalculation and macro redistribution
 *
 * Called by MealLogController. Side effects run inside a single DB transaction so a
 * failure halfway through never leaves the user with XP but no meal log.
 */
class MealAnalysisService
{
    public function __construct(
        private GroqService $groq,
        private GamificationService $gamification,
        private DietEngineService $dietEngine,
        private RedistributeMacrosUseCase $redistributeMacros,
    ) {}

    public function analyzeText(User $user, array $validated): MealLog
    {
        $prompt = $this->buildTextPrompt($validated['text_description']);
        $response = $this->groq->generateTextResponse(null, $prompt);

        $macros = $response['macros_totais'] ?? [];

        return $this->persistAndReact($user, [
            'date' => $validated['date'],
            'meal_type' => $validated['meal_type'],
            'calories_consumed' => $response['calorias_totais'] ?? 0,
            'protein_g' => $macros['proteinas_g'] ?? 0,
            'carbs_g' => $macros['carboidratos_g'] ?? 0,
            'fat_g' => $macros['gorduras_g'] ?? 0,
            'user_note' => $validated['text_description'],
            'ai_feedback' => $response['feedback_breve'] ?? null,
            'items_json' => $response['itens_detalhados'] ?? [],
        ]);
    }

    public function analyzeImage(User $user, array $validated): MealLog
    {
        $base64 = preg_replace('#^data:image/[^;]+;base64,#', '', $validated['image_base64']);
        $prompt = $this->buildImagePrompt();
        $response = $this->groq->generateVisionResponse(null, $prompt, $base64);

        $macros = $response['macros_estimados'] ?? [];

        return $this->persistAndReact($user, [
            'date' => $validated['date'],
            'meal_type' => $validated['meal_type'],
            'calories_consumed' => $response['calorias_totais_estimadas'] ?? 0,
            'protein_g' => $macros['proteinas_g'] ?? 0,
            'carbs_g' => $macros['carboidratos_g'] ?? 0,
            'fat_g' => $macros['gorduras_g'] ?? 0,
            'user_note' => 'Identificacao via foto',
            'ai_feedback' => $response['feedback_breve'] ?? null,
            'items_json' => $response['itens_identificados'] ?? [],
        ]);
    }

    private function persistAndReact(User $user, array $attributes): MealLog
    {
        return DB::transaction(function () use ($user, $attributes) {
            $meal = MealLog::create($attributes + ['user_id' => $user->id]);

            $this->gamification->grantMealLoggedXp($user);
            $this->dietEngine->recalculateAfterMeal($user, $meal);
            $this->redistributeMacros->execute($user, $meal);

            return $meal;
        });
    }

    private function buildTextPrompt(string $description): string
    {
        return "Você é um Nutricionista especialista. O usuário inseriu a seguinte refeição/texto:\n"
            ."{$description}\n\n"
            ."Calcule o valor nutricional total dessa refeição.\n"
            ."Você DEVE retornar a resposta EXCLUSIVAMENTE em um formato JSON válido, sem marcações markdown. Estrutura exigida:\n"
            .'{"calorias_totais": 520, "macros_totais": {"proteinas_g": 35.5, "carboidratos_g": 45.0, "gorduras_g": 20.2}, "feedback_breve": "texto", "itens_detalhados": [{"nome": "Frango", "quantidadeDada": "100g", "calorias": 165}]}';
    }

    private function buildImagePrompt(): string
    {
        return "Você é um Nutricionista especialista com visão computacional. Analise a imagem desta refeição rigorosamente.\n"
            ."Identifique os alimentos, estime sua porção, calorias e macronutrientes totais.\n"
            ."Você DEVE retornar a resposta EXCLUSIVAMENTE em formato JSON, sem marcações markdown. Estrutura exigida:\n"
            .'{"calorias_totais_estimadas": 600, "macros_estimados": {"proteinas_g": 40, "carboidratos_g": 60, "gorduras_g": 25}, "feedback_breve": "Analise visual curta", "itens_identificados": [{"nome_estimado": "Arroz", "quantidade_estimada_gramas": 150, "calorias_estimadas": 170}]}';
    }
}
