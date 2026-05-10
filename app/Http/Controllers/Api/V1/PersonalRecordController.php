<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExercisePersonalRecord;
use App\Services\Workout\ProgressiveOverloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PersonalRecordController extends Controller
{
    public function __construct(private ProgressiveOverloadService $overload) {}

    #[OA\Get(
        path: '/api/v1/workouts/personal-records',
        summary: 'Listar recordes pessoais (1RM por exercício)',
        tags: ['Workouts'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Lista de PRs')]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $prs = ExercisePersonalRecord::with('exercise:id,name,muscle_group,category')
            ->where('user_id', $user->id)
            ->orderByDesc('achieved_at')
            ->get()
            ->groupBy('exercise_id')
            ->map(fn ($records) => $records->first());

        return response()->json(['personal_records' => $prs->values()]);
    }

    #[OA\Get(
        path: '/api/v1/workouts/exercises/{exercise_id}/history',
        summary: 'Histórico de cargas de um exercício',
        tags: ['Workouts'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'exercise_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Evolução de 1RM')]
    )]
    public function history(Request $request, string $exerciseId): JsonResponse
    {
        $user = $request->user();
        $history = $this->overload->getHistory($user, $exerciseId);

        return response()->json(['history' => $history]);
    }

    #[OA\Get(
        path: '/api/v1/workouts/exercises/{exercise_id}/suggest-load',
        summary: 'Sugestão de carga (sobrecarga progressiva)',
        tags: ['Workouts'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'exercise_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Sugestão de carga para a próxima série')]
    )]
    public function suggestLoad(Request $request, string $exerciseId): JsonResponse
    {
        $user = $request->user();
        $suggestion = $this->overload->suggestNextLoad($user, $exerciseId);

        return response()->json($suggestion);
    }
}
