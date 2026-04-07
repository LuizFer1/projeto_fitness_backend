<?php

namespace App\Http\Controllers;

use App\Application\UseCases\Onboarding\SubmitOnboardingUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class OnboardingController extends Controller
{
    private SubmitOnboardingUseCase $submitOnboardingUseCase;

    public function __construct(SubmitOnboardingUseCase $submitOnboardingUseCase)
    {
        $this->submitOnboardingUseCase = $submitOnboardingUseCase;
    }

    #[OA\Get(
        path: '/api/onboarding',
        summary: 'Obter dados de onboarding',
        description: 'Retorna os dados de onboarding do usuário autenticado.',
        tags: ['Onboarding'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dados de onboarding'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $onboarding = $request->user()->onboarding;

        return response()->json($onboarding);
    }

    #[OA\Post(
        path: '/api/onboarding',
        summary: 'Enviar dados de onboarding',
        description: 'Salva as informações de onboarding do usuário.',
        tags: ['Onboarding'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'gender', type: 'string', enum: ['M', 'F', 'other', 'prefer_not_to_say'], example: 'M'),
                    new OA\Property(property: 'age', type: 'integer', minimum: 10, maximum: 120, example: 25),
                    new OA\Property(property: 'height_cm', type: 'integer', minimum: 100, maximum: 250, example: 175),
                    new OA\Property(property: 'weight_kg', type: 'number', minimum: 30, maximum: 300, example: 75.5),
                    new OA\Property(property: 'body_fat_percent', type: 'number', minimum: 3, maximum: 60, example: 15.0),
                    new OA\Property(property: 'workouts_per_week', type: 'integer', minimum: 0, maximum: 7, example: 4),
                    new OA\Property(property: 'work_style', type: 'string', enum: ['white_collar', 'blue_collar', 'sedentary', 'moderate', 'active'], example: 'white_collar'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Onboarding salvo com sucesso'),
            new OA\Response(response: 400, description: 'Erro de validação'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
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
