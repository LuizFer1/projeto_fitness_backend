<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Food;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

class FoodController extends Controller
{
    #[OA\Get(
        path: '/api/v1/foods/lookup',
        summary: 'Buscar alimento por código de barras',
        description: 'Busca no banco local primeiro; se não encontrar, consulta a OpenFoodFacts e persiste o resultado.',
        tags: ['Foods'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'barcode', in: 'query', required: true, description: 'Código EAN/barcode do produto', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Alimento encontrado'),
            new OA\Response(response: 404, description: 'Produto não encontrado'),
            new OA\Response(response: 422, description: 'Parâmetro barcode ausente'),
        ]
    )]
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['barcode' => ['required', 'string', 'max:30']]);

        $barcode = trim($request->query('barcode'));

        // Local lookup first
        $food = Food::where('barcode_ean', $barcode)->where('is_active', true)->first();

        if ($food) {
            return response()->json(['data' => $this->format($food), 'source' => 'local']);
        }

        // OpenFoodFacts fallback
        $food = $this->fetchFromOpenFoodFacts($barcode);

        if (! $food) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        return response()->json(['data' => $this->format($food), 'source' => 'openfoodfacts']);
    }

    private function fetchFromOpenFoodFacts(string $barcode): ?Food
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'EvoFit/1.0 (contact@evofit.app)'])
                ->get("https://world.openfoodfacts.org/api/v0/product/{$barcode}.json");

            if (! $response->ok()) {
                return null;
            }

            $data = $response->json();
            $status = $data['status'] ?? 0;
            $product = $data['product'] ?? null;

            if ($status !== 1 || ! $product) {
                return null;
            }

            $nutriments = $product['nutriments'] ?? [];

            return Food::create([
                'name' => $product['product_name'] ?? $product['product_name_en'] ?? 'Produto sem nome',
                'category' => $product['food_groups'] ?? null,
                'calories_100g' => $nutriments['energy-kcal_100g'] ?? $nutriments['energy_100g'] ?? 0,
                'protein_g' => $nutriments['proteins_100g'] ?? 0,
                'carbs_g' => $nutriments['carbohydrates_100g'] ?? 0,
                'fat_g' => $nutriments['fat_100g'] ?? 0,
                'fiber_g' => $nutriments['fiber_100g'] ?? null,
                'sodium_mg' => isset($nutriments['sodium_100g']) ? $nutriments['sodium_100g'] * 1000 : null,
                'standard_portion_g' => 100,
                'is_active' => true,
                'barcode_ean' => $barcode,
                'source' => 'openfoodfacts',
                'external_id' => (string) ($product['code'] ?? $barcode),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function format(Food $food): array
    {
        return [
            'id' => $food->id,
            'name' => $food->name,
            'category' => $food->category,
            'calories_100g' => (float) $food->calories_100g,
            'protein_g' => (float) $food->protein_g,
            'carbs_g' => (float) $food->carbs_g,
            'fat_g' => (float) $food->fat_g,
            'fiber_g' => isset($food->fiber_g) ? (float) $food->fiber_g : null,
            'standard_portion_g' => (float) $food->standard_portion_g,
            'barcode_ean' => $food->barcode_ean,
            'source' => $food->source ?? 'manual',
        ];
    }
}
