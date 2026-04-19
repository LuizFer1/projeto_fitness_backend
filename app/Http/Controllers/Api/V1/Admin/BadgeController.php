<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class BadgeController extends Controller
{
    private const CATEGORIES = ['consistency', 'workout', 'water', 'nutrition', 'hardcore', 'special'];
    private const CONDITION_TYPES = ['streak_days', 'total_workouts', 'water_days', 'active_days', 'hardcore_weeks'];

    #[OA\Get(
        path: '/api/v1/admin/badges',
        summary: 'Listar badges (admin)',
        description: 'Retorna todas as conquistas/badges cadastradas.',
        tags: ['Admin - Badges'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de badges'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
        ]
    )]
    public function index()
    {
        return response()->json(['data' => Achievement::orderBy('slug')->get()]);
    }

    #[OA\Post(
        path: '/api/v1/admin/badges',
        summary: 'Criar badge (admin)',
        description: 'Cadastra uma nova conquista/badge.',
        tags: ['Admin - Badges'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['slug', 'name', 'category', 'condition_type', 'condition_value'],
                properties: [
                    new OA\Property(property: 'slug', type: 'string', example: 'streak-7-dias'),
                    new OA\Property(property: 'name', type: 'string', example: 'Sequência de 7 Dias'),
                    new OA\Property(property: 'description', type: 'string', example: 'Treinou por 7 dias consecutivos'),
                    new OA\Property(property: 'icon', type: 'string', example: 'fire'),
                    new OA\Property(property: 'category', type: 'string', enum: ['consistency', 'workout', 'water', 'nutrition', 'hardcore', 'special']),
                    new OA\Property(property: 'xp_reward', type: 'integer', example: 100),
                    new OA\Property(property: 'condition_type', type: 'string', enum: ['streak_days', 'total_workouts', 'water_days', 'active_days', 'hardcore_weeks']),
                    new OA\Property(property: 'condition_value', type: 'integer', example: 7),
                    new OA\Property(property: 'is_hidden', type: 'boolean', example: false),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Badge criado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'slug'            => ['required', 'string', 'max:80', 'unique:achievements,slug'],
            'name'            => ['required', 'string', 'max:120'],
            'description'     => ['nullable', 'string'],
            'icon'            => ['nullable', 'string', 'max:10'],
            'category'        => ['required', Rule::in(self::CATEGORIES)],
            'xp_reward'       => ['integer', 'min:0'],
            'condition_type'  => ['required', Rule::in(self::CONDITION_TYPES)],
            'condition_value' => ['required', 'integer', 'min:1'],
            'is_hidden'       => ['boolean'],
            'is_active'       => ['boolean'],
        ]);

        $badge = Achievement::create($data);

        return response()->json(['data' => $badge], 201);
    }

    #[OA\Put(
        path: '/api/v1/admin/badges/{id}',
        summary: 'Atualizar badge (admin)',
        description: 'Atualiza os dados de um badge existente.',
        tags: ['Admin - Badges'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'icon', type: 'string'),
                    new OA\Property(property: 'category', type: 'string'),
                    new OA\Property(property: 'xp_reward', type: 'integer'),
                    new OA\Property(property: 'condition_type', type: 'string'),
                    new OA\Property(property: 'condition_value', type: 'integer'),
                    new OA\Property(property: 'is_hidden', type: 'boolean'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Badge atualizado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $badge = Achievement::findOrFail($id);

        $data = $request->validate([
            'slug'            => ['sometimes', 'string', 'max:80', Rule::unique('achievements', 'slug')->ignore($badge->id)],
            'name'            => ['sometimes', 'string', 'max:120'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'icon'            => ['sometimes', 'nullable', 'string', 'max:10'],
            'category'        => ['sometimes', Rule::in(self::CATEGORIES)],
            'xp_reward'       => ['sometimes', 'integer', 'min:0'],
            'condition_type'  => ['sometimes', Rule::in(self::CONDITION_TYPES)],
            'condition_value' => ['sometimes', 'integer', 'min:1'],
            'is_hidden'       => ['sometimes', 'boolean'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);

        $badge->update($data);

        return response()->json(['data' => $badge]);
    }

    #[OA\Delete(
        path: '/api/v1/admin/badges/{id}',
        summary: 'Remover badge (admin)',
        description: 'Remove um badge do catálogo.',
        tags: ['Admin - Badges'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Badge removido'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function destroy(string $id)
    {
        $badge = Achievement::findOrFail($id);
        $badge->delete();

        return response()->json(null, 204);
    }
}
