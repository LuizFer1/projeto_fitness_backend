<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ExerciseCatalogController extends Controller
{
    #[OA\Get(
        path: '/api/v1/exercises',
        summary: 'Catálogo paginado de exercícios',
        description: 'Filtros opcionais por grupo muscular, categoria e termo de busca no nome.',
        tags: ['Workouts'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'muscle_group', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de exercícios'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'muscle_group' => ['nullable', 'string', 'max:60'],
            'category' => ['nullable', 'string', 'max:60'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $query = Exercise::where('is_active', true);

        if ($request->filled('muscle_group')) {
            $query->where('muscle_group', $request->query('muscle_group'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->query('search').'%');
        }

        return response()->json([
            'data' => $query->orderBy('name')->paginate(50),
        ]);
    }
}
