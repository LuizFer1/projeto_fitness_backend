<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiPlan;
use App\Models\PlanWorkoutExercise;
use App\Models\PlanWorkoutExerciseComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class WorkoutPlanCommentController extends Controller
{
    #[OA\Get(
        path: '/api/v1/plans/{plan_id}/comments',
        summary: 'List per-exercise comments for an AI workout plan',
        description: 'Returns every comment attached to any exercise of the given plan. Only the plan owner can read comments.',
        tags: ['AI Plans'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'plan_id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Comments for the plan'),
            new OA\Response(response: 403, description: 'Plan does not belong to the authenticated user'),
            new OA\Response(response: 404, description: 'Plan not found'),
        ]
    )]
    public function index(Request $request, string $planId): JsonResponse
    {
        $user = $request->user() ?? User::first();
        $plan = $this->resolveOwnedPlan($user, $planId);

        $exerciseIds = PlanWorkoutExercise::query()
            ->whereIn('plan_workout_id', $plan->planWorkouts()->select('id'))
            ->pluck('id');

        $comments = PlanWorkoutExerciseComment::query()
            ->whereIn('plan_workout_exercise_id', $exerciseIds)
            ->with(['planWorkoutExercise.exercise:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (PlanWorkoutExerciseComment $c) {
                return [
                    'id' => $c->id,
                    'plan_workout_exercise_id' => $c->plan_workout_exercise_id,
                    'type' => $c->type,
                    'text' => $c->text,
                    'created_at' => $c->created_at,
                    'exercise' => [
                        'id' => $c->planWorkoutExercise?->exercise?->id,
                        'name' => $c->planWorkoutExercise?->exercise?->name,
                    ],
                ];
            });

        return response()->json(['comments' => $comments]);
    }

    #[OA\Post(
        path: '/api/v1/plans/{plan_id}/exercises/{plan_workout_exercise_id}/comments',
        summary: 'Create a per-exercise comment on an AI workout plan',
        description: 'Persists a comment on a specific exercise of an AI workout plan. Only the plan owner can create comments.',
        tags: ['AI Plans'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'plan_id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'plan_workout_exercise_id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['type'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['pain', 'broken', 'heavy', 'custom']),
                    new OA\Property(property: 'text', type: 'string', nullable: true, example: 'Senti dor no ombro direito'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Comment created'),
            new OA\Response(response: 403, description: 'Plan does not belong to the authenticated user'),
            new OA\Response(response: 404, description: 'Plan or exercise not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request, string $planId, string $exerciseId): JsonResponse
    {
        $user = $request->user() ?? User::first();
        $plan = $this->resolveOwnedPlan($user, $planId);

        $validated = $request->validate([
            'type' => 'required|string|in:pain,broken,heavy,custom',
            'text' => 'nullable|string|max:1000',
        ]);

        $exercise = PlanWorkoutExercise::query()
            ->where('id', $exerciseId)
            ->whereIn('plan_workout_id', $plan->planWorkouts()->select('id'))
            ->first();

        if (! $exercise) {
            return response()->json(['error' => 'Exercise not found in this plan.'], 404);
        }

        $comment = PlanWorkoutExerciseComment::create([
            'user_id' => $user->id,
            'plan_workout_exercise_id' => $exercise->id,
            'type' => $validated['type'],
            'text' => $validated['text'] ?? null,
        ]);

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'plan_workout_exercise_id' => $comment->plan_workout_exercise_id,
                'type' => $comment->type,
                'text' => $comment->text,
                'created_at' => $comment->created_at,
            ],
        ], 201);
    }

    #[OA\Delete(
        path: '/api/v1/plans/{plan_id}/comments/{comment_id}',
        summary: 'Delete a per-exercise comment from an AI workout plan',
        description: 'Soft-deletes a comment. Only the plan owner can delete.',
        tags: ['AI Plans'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'plan_id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'comment_id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Comment deleted'),
            new OA\Response(response: 403, description: 'Plan does not belong to the authenticated user'),
            new OA\Response(response: 404, description: 'Plan or comment not found'),
        ]
    )]
    public function destroy(Request $request, string $planId, string $commentId): JsonResponse
    {
        $user = $request->user() ?? User::first();
        $plan = $this->resolveOwnedPlan($user, $planId);

        $comment = PlanWorkoutExerciseComment::query()
            ->where('id', $commentId)
            ->whereIn('plan_workout_exercise_id', PlanWorkoutExercise::query()
                ->whereIn('plan_workout_id', $plan->planWorkouts()->select('id'))
                ->select('id')
            )
            ->first();

        if (! $comment) {
            return response()->json(['error' => 'Comment not found in this plan.'], 404);
        }

        $comment->delete();

        return response()->json(null, 204);
    }

    /**
     * Loads the AI plan and ensures it belongs to the authenticated user.
     * Returns 404 when the plan does not exist, 403 when it belongs to someone else.
     */
    private function resolveOwnedPlan($user, string $planId): AiPlan
    {
        $plan = AiPlan::find($planId);

        if (! $plan) {
            abort(response()->json(['error' => 'Plan not found.'], 404));
        }

        if ($plan->user_id !== $user->id) {
            abort(response()->json(['error' => 'Forbidden.'], 403));
        }

        return $plan;
    }
}
