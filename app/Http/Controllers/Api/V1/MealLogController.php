<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nutrition\AnalyzeMealImageRequest;
use App\Http\Requests\Nutrition\AnalyzeMealTextRequest;
use App\Http\Resources\MealLogResource;
use App\Services\Diet\MealAnalysisService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class MealLogController extends Controller
{
    public function __construct(private MealAnalysisService $analysis) {}

    #[OA\Post(
        path: '/api/v1/meals/analyze-text',
        summary: 'Analisa e salva uma refeição a partir de texto',
        description: 'O usuário envia o que comeu (em texto) e a IA estima macros, calorias e salva no histórico.',
        tags: ['Meals'],
        security: [['sanctum' => []]],
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
            new OA\Response(response: 422, description: 'Erro de validação ou falha de IA'),
        ]
    )]
    public function analyzeText(AnalyzeMealTextRequest $request): JsonResponse
    {
        try {
            $meal = $this->analysis->analyzeText($request->user(), $request->validated());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Falha na IA: '.$e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Refeição registrada via IA.',
            'log' => new MealLogResource($meal),
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/meals/analyze-image',
        summary: 'Analisa e salva uma refeição a partir de uma FOTO',
        description: 'O usuário envia a foto do prato em base64 e a IA usa visão computacional para estimar macros, calorias e itens.',
        tags: ['Meals'],
        security: [['sanctum' => []]],
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
            new OA\Response(response: 422, description: 'Erro de validação ou falha de IA'),
        ]
    )]
    public function analyzeImage(AnalyzeMealImageRequest $request): JsonResponse
    {
        try {
            $meal = $this->analysis->analyzeImage($request->user(), $request->validated());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Falha na IA Vision: '.$e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Refeição registrada por imagem via IA.',
            'log' => new MealLogResource($meal),
        ], 201);
    }
}
