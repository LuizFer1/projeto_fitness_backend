<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class QuestController extends Controller
{
    private const TYPES = ['basic', 'special', 'event'];
    private const PERIODICITIES = ['once', 'weekly', 'monthly', 'recurring'];
    private const CONDITION_TYPES = ['streak_days', 'workouts_period', 'water_days', 'meals_logged', 'weight_logged'];

    #[OA\Get(
        path: '/api/v1/admin/quests',
        summary: 'Listar quests (admin)',
        description: 'Retorna todas as quests cadastradas.',
        tags: ['Admin - Quests'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de quests'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
        ]
    )]
    public function index()
    {
        return response()->json(['data' => Quest::orderBy('slug')->get()]);
    }

    #[OA\Post(
        path: '/api/v1/admin/quests',
        summary: 'Criar quest (admin)',
        description: 'Cadastra uma nova quest/missão.',
        tags: ['Admin - Quests'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['slug', 'name', 'type', 'periodicity', 'condition_type', 'condition_value'],
                properties: [
                    new OA\Property(property: 'slug', type: 'string', example: 'treinos-semana-3'),
                    new OA\Property(property: 'name', type: 'string', example: 'Treinar 3x na Semana'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'icon', type: 'string'),
                    new OA\Property(property: 'type', type: 'string', enum: ['basic', 'special', 'event']),
                    new OA\Property(property: 'periodicity', type: 'string', enum: ['once', 'weekly', 'monthly', 'recurring']),
                    new OA\Property(property: 'condition_type', type: 'string', enum: ['streak_days', 'workouts_period', 'water_days', 'meals_logged', 'weight_logged']),
                    new OA\Property(property: 'condition_value', type: 'integer', example: 3),
                    new OA\Property(property: 'xp_reward', type: 'integer', example: 50),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Quest criada'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'slug'            => ['required', 'string', 'max:80', 'unique:quests,slug'],
            'name'            => ['required', 'string', 'max:120'],
            'description'     => ['nullable', 'string'],
            'icon'            => ['nullable', 'string', 'max:10'],
            'type'            => ['required', Rule::in(self::TYPES)],
            'periodicity'     => ['required', Rule::in(self::PERIODICITIES)],
            'condition_type'  => ['required', Rule::in(self::CONDITION_TYPES)],
            'condition_value' => ['required', 'integer', 'min:1'],
            'xp_reward'       => ['integer', 'min:0'],
            'is_active'       => ['boolean'],
        ]);

        $quest = Quest::create($data);

        return response()->json(['data' => $quest], 201);
    }

    #[OA\Put(
        path: '/api/v1/admin/quests/{id}',
        summary: 'Atualizar quest (admin)',
        description: 'Atualiza os dados de uma quest existente.',
        tags: ['Admin - Quests'],
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
                    new OA\Property(property: 'type', type: 'string'),
                    new OA\Property(property: 'periodicity', type: 'string'),
                    new OA\Property(property: 'condition_type', type: 'string'),
                    new OA\Property(property: 'condition_value', type: 'integer'),
                    new OA\Property(property: 'xp_reward', type: 'integer'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Quest atualizada'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $quest = Quest::findOrFail($id);

        $data = $request->validate([
            'slug'            => ['sometimes', 'string', 'max:80', Rule::unique('quests', 'slug')->ignore($quest->id)],
            'name'            => ['sometimes', 'string', 'max:120'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'icon'            => ['sometimes', 'nullable', 'string', 'max:10'],
            'type'            => ['sometimes', Rule::in(self::TYPES)],
            'periodicity'     => ['sometimes', Rule::in(self::PERIODICITIES)],
            'condition_type'  => ['sometimes', Rule::in(self::CONDITION_TYPES)],
            'condition_value' => ['sometimes', 'integer', 'min:1'],
            'xp_reward'       => ['sometimes', 'integer', 'min:0'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);

        $quest->update($data);

        return response()->json(['data' => $quest]);
    }

    #[OA\Delete(
        path: '/api/v1/admin/quests/{id}',
        summary: 'Remover quest (admin)',
        description: 'Remove uma quest do catálogo.',
        tags: ['Admin - Quests'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Quest removida'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Acesso negado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function destroy(string $id)
    {
        $quest = Quest::findOrFail($id);
        $quest->delete();

        return response()->json(null, 204);
    }
}
