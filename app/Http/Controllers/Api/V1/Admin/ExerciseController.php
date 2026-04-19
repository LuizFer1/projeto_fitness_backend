<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ExerciseController extends Controller
{
    private const CATEGORIES = ['strength', 'cardio', 'mobility', 'hiit', 'stretching', 'functional'];
    private const DIFFICULTIES = ['beginner', 'intermediate', 'advanced'];

    #[OA\Get(
        path: '/api/v1/admin/exercises',
        summary: 'Listar exercícios (admin)',
        description: 'Retorna a lista paginada de exercícios do catálogo, com filtro opcional por nome.',
        tags: ['Admin - Exercises'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Filtro por nome', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de exercícios'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
        ]
    )]
    public function index(Request $request)
    {
        $q = Exercise::query();
        if ($request->filled('search')) {
            $q->where('name', 'like', '%'.$request->query('search').'%');
        }
        return response()->json(['data' => $q->orderBy('name')->paginate(50)]);
    }

    #[OA\Post(
        path: '/api/v1/admin/exercises',
        summary: 'Criar exercício (admin)',
        description: 'Cadastra um novo exercício no catálogo.',
        tags: ['Admin - Exercises'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Agachamento Livre'),
                    new OA\Property(property: 'muscle_group', type: 'string', example: 'pernas'),
                    new OA\Property(property: 'category', type: 'string', enum: ['strength', 'cardio', 'mobility', 'hiit', 'stretching', 'functional']),
                    new OA\Property(property: 'difficulty', type: 'string', enum: ['beginner', 'intermediate', 'advanced']),
                    new OA\Property(property: 'equipment', type: 'string', example: 'barra'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'calories_per_min', type: 'number', example: 8.5),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Exercício criado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $this->validateExercise($request);
        $exercise = Exercise::create($data);

        return response()->json(['data' => $exercise], 201);
    }

    #[OA\Put(
        path: '/api/v1/admin/exercises/{id}',
        summary: 'Atualizar exercício (admin)',
        description: 'Atualiza os dados de um exercício existente.',
        tags: ['Admin - Exercises'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'muscle_group', type: 'string'),
                    new OA\Property(property: 'category', type: 'string'),
                    new OA\Property(property: 'difficulty', type: 'string'),
                    new OA\Property(property: 'equipment', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'calories_per_min', type: 'number'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Exercício atualizado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $exercise = Exercise::findOrFail($id);
        $data = $this->validateExercise($request, true);

        $exercise->update($data);

        return response()->json(['data' => $exercise]);
    }

    #[OA\Delete(
        path: '/api/v1/admin/exercises/{id}',
        summary: 'Remover exercício (admin)',
        description: 'Remove um exercício do catálogo.',
        tags: ['Admin - Exercises'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Exercício removido'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function destroy(string $id)
    {
        $exercise = Exercise::findOrFail($id);
        $exercise->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/v1/admin/exercises/bulk',
        summary: 'Importar exercícios em lote (admin)',
        description: 'Cria múltiplos exercícios em uma única requisição.',
        tags: ['Admin - Exercises'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['exercises'],
                properties: [
                    new OA\Property(
                        property: 'exercises',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['name'],
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'muscle_group', type: 'string'),
                                new OA\Property(property: 'category', type: 'string'),
                                new OA\Property(property: 'difficulty', type: 'string'),
                                new OA\Property(property: 'equipment', type: 'string'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'calories_per_min', type: 'number'),
                                new OA\Property(property: 'is_active', type: 'boolean'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Exercícios criados em lote'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function bulkImport(Request $request)
    {
        $data = $request->validate([
            'exercises'                  => ['required', 'array', 'min:1'],
            'exercises.*.name'           => ['required', 'string', 'max:150'],
            'exercises.*.muscle_group'   => ['nullable', 'string', 'max:80'],
            'exercises.*.category'       => ['nullable', Rule::in(self::CATEGORIES)],
            'exercises.*.difficulty'     => ['nullable', Rule::in(self::DIFFICULTIES)],
            'exercises.*.equipment'      => ['nullable', 'string', 'max:100'],
            'exercises.*.description'    => ['nullable', 'string'],
            'exercises.*.calories_per_min' => ['nullable', 'numeric'],
            'exercises.*.is_active'      => ['nullable', 'boolean'],
        ]);

        $created = collect($data['exercises'])->map(fn ($e) => Exercise::create($e));

        return response()->json(['data' => $created, 'count' => $created->count()], 201);
    }

    private function validateExercise(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'name'             => [$sometimes, 'string', 'max:150'],
            'muscle_group'     => ['nullable', 'string', 'max:80'],
            'category'         => ['nullable', Rule::in(self::CATEGORIES)],
            'difficulty'       => ['nullable', Rule::in(self::DIFFICULTIES)],
            'equipment'        => ['nullable', 'string', 'max:100'],
            'description'      => ['nullable', 'string'],
            'calories_per_min' => ['nullable', 'numeric'],
            'is_active'        => ['nullable', 'boolean'],
        ]);
    }
}
