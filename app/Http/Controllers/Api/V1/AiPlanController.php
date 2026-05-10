<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiPlan;
use App\Models\Exercise;
use App\Models\PlanMeal;
use App\Models\PlanWorkout;
use App\Models\PlanWorkoutExercise;
use App\Models\User;
use App\Services\GroqService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AiPlanController extends Controller
{
    private $groqService;

    public function __construct(GroqService $groqService)
    {
        $this->groqService = $groqService;
    }

    #[OA\Post(
        path: '/api/v1/plans/generate-workout',
        summary: 'Generate a personalized AI workout plan',
        description: 'Sends user preferences to Groq AI and receives a structured workout plan that is saved to the database.',
        tags: ['AI Plans'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'goal', type: 'string', example: 'hypertrophy'),
                    new OA\Property(property: 'muscles', type: 'string', example: 'chest, triceps, shoulders'),
                    new OA\Property(property: 'level', type: 'string', example: 'intermediate'),
                    new OA\Property(property: 'days_per_week', type: 'integer', example: 4),
                    new OA\Property(property: 'workout_time_minutes', type: 'integer', example: 60),
                    new OA\Property(property: 'limitations', type: 'string', example: 'shoulder injury on right side'),
                    new OA\Property(property: 'location', type: 'string', example: 'home'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Workout plan generated and saved successfully'),
            new OA\Response(response: 422, description: 'Validation error or AI processing failure'),
        ]
    )]
    public function generateWorkout(Request $request)
    {
        $validated = $request->validate([
            'goal' => 'required|string|max:100',
            'muscles' => 'nullable|string|max:255',
            'level' => 'required|string|in:beginner,intermediate,advanced',
            'days_per_week' => 'required|integer|min:1|max:7',
            'workout_time_minutes' => 'required|integer|min:15|max:180',
            'limitations' => 'nullable|string|max:500',
            'location' => 'required|string|in:home,gym',
        ]);

        $user = $request->user() ?? User::first();
        $prompt = $this->buildWorkoutPrompt($validated);

        try {
            $aiResponse = $this->groqService->generateTextResponse(null, $prompt);
            $plan = DB::transaction(fn () => $this->persistWorkoutPlan($user, $validated, $aiResponse, $prompt));

            return response()->json([
                'message' => 'Workout plan generated successfully!',
                'plan' => $plan,
            ], 201);
        } catch (\Throwable $e) {
            return $this->failureResponse($e, 'Failed to generate workout plan');
        }
    }

    #[OA\Post(
        path: '/api/v1/plans/{plan_id}/refine',
        summary: 'Refine an AI workout plan using the user comments',
        description: 'Re-asks Groq with the existing sheet structure plus the user comments grouped by exercise (and an optional free-text note). Creates a new plan version (version+1, status=active) and marks the previous one as replaced.',
        tags: ['AI Plans'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'plan_id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'note', type: 'string', nullable: true, example: 'Quero focar mais em peito esta semana.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Plan refined; new active version created.'),
            new OA\Response(response: 403, description: 'Plan does not belong to the authenticated user'),
            new OA\Response(response: 404, description: 'Plan not found'),
            new OA\Response(response: 502, description: 'AI service unavailable'),
        ]
    )]
    public function refineWorkout(Request $request, string $id)
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $user = $request->user() ?? User::first();

        $plan = AiPlan::where('user_id', $user->id)
            ->where('id', $id)
            ->where('type', 'workout')
            ->with(['planWorkouts.exercises.exercise'])
            ->firstOrFail();

        // Collect user comments for the exercises in this plan.
        $exerciseIds = $plan->planWorkouts
            ->flatMap(fn ($w) => $w->exercises->pluck('id'))
            ->all();

        $comments = \App\Models\PlanWorkoutExerciseComment::query()
            ->whereIn('plan_workout_exercise_id', $exerciseIds)
            ->where('user_id', $user->id)
            ->get();

        $prompt = $this->buildRefinePrompt($plan, $comments, $validated['note'] ?? null);

        try {
            $aiResponse = $this->groqService->generateTextResponse(null, $prompt);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'A IA está indisponível no momento. Tente novamente em instantes.',
                'detail' => $e->getMessage(),
            ], 502);
        }

        try {
            $newPlan = DB::transaction(function () use ($plan, $user, $aiResponse, $prompt) {
                $aiPlan = AiPlan::create([
                    'user_id' => $user->id,
                    'type' => $plan->type,
                    'version' => $plan->version + 1,
                    'status' => 'active',
                    'content_json' => $aiResponse,
                    'generation_reason' => 'Refined from plan '.$plan->id.' (v'.$plan->version.')',
                    'context_prompt' => $prompt,
                    'valid_from' => Carbon::today(),
                    'valid_until' => Carbon::today()->addWeeks(8),
                ]);

                foreach ($aiResponse['workouts'] ?? [] as $workoutData) {
                    $this->persistWorkoutDay($aiPlan, $workoutData);
                }

                // Mark the source plan as replaced.
                $plan->update(['status' => 'replaced']);

                return $aiPlan->load('planWorkouts.exercises.exercise');
            });
        } catch (\Throwable $e) {
            return $this->failureResponse($e, 'Failed to persist refined plan');
        }

        return response()->json([
            'message' => 'Plan refined successfully!',
            'plan' => $newPlan,
        ], 201);
    }

    /**
     * Build a Groq prompt that asks for a refined plan, given the current sheet
     * structure (as JSON) and the user's comments grouped by exercise, plus an
     * optional free-text note.
     */
    private function buildRefinePrompt(AiPlan $plan, $comments, ?string $note): string
    {
        $current = [
            'plan_name' => $plan->content_json['plan_name'] ?? null,
            'plan_goal' => $plan->content_json['plan_goal'] ?? null,
            'days_per_week' => $plan->content_json['days_per_week'] ?? null,
            'workouts' => $plan->planWorkouts->map(function ($w) {
                return [
                    'day_of_week' => $w->day_of_week,
                    'workout_name' => $w->workout_name,
                    'workout_observations' => $w->ai_observations,
                    'exercises' => $w->exercises->map(function ($ex) {
                        return [
                            'plan_workout_exercise_id' => $ex->id,
                            'exercise_name' => $ex->exercise?->name,
                            'sets' => $ex->rec_sets,
                            'repetitions' => $ex->rec_reps,
                            'rest_seconds' => $ex->rest_sec,
                            'suggested_weight_kg' => $ex->rec_weight_kg,
                            'ai_observations' => $ex->ai_notes,
                        ];
                    })->values(),
                ];
            })->values(),
        ];

        $byExercise = [];
        foreach ($comments as $c) {
            $exId = $c->plan_workout_exercise_id;
            $exName = null;
            foreach ($plan->planWorkouts as $w) {
                foreach ($w->exercises as $ex) {
                    if ($ex->id === $exId) {
                        $exName = $ex->exercise?->name;
                        break 2;
                    }
                }
            }
            $byExercise[$exId] ??= ['exercise_name' => $exName, 'comments' => []];
            $byExercise[$exId]['comments'][] = [
                'type' => $c->type,
                'type_label' => $this->humanCommentType($c->type),
                'text' => $c->text,
            ];
        }

        $commentsBlock = empty($byExercise)
            ? 'O usuário não registrou comentários específicos por exercício.'
            : json_encode(array_values($byExercise), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $noteBlock = $note ? trim($note) : 'Nenhum.';

        $sheetJson = json_encode($current, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return "Você é um Personal Trainer especialista em ajustar protocolos de treino.\n"
            ."O usuário já tem o seguinte plano de treino atual (em JSON):\n\n"
            ."<PLANO_ATUAL>\n{$sheetJson}\n</PLANO_ATUAL>\n\n"
            ."O usuário registrou os seguintes comentários por exercício. Cada comentário tem um `type` que indica a intenção:\n"
            ."- pain: o exercício causou dor/desconforto, deve ser substituído ou removido por uma alternativa segura.\n"
            ."- broken: o equipamento necessário não está disponível; substitua por um exercício que use outro equipamento ou peso corporal.\n"
            ."- heavy: a carga sugerida ficou pesada demais; reduza carga e/ou troque por uma variação mais acessível, mantendo o mesmo grupo muscular.\n"
            ."- custom: texto livre — leia o `text` e ajuste de acordo.\n\n"
            ."<COMENTARIOS_DO_USUARIO>\n{$commentsBlock}\n</COMENTARIOS_DO_USUARIO>\n\n"
            ."Observação livre adicional do usuário: {$noteBlock}\n\n"
            ."REGRAS:\n"
            ."- Mantenha a mesma estrutura geral (mesmo número de dias quando possível, mesmos grupos musculares por dia).\n"
            ."- Aplique os ajustes pedidos pelos comentários e pela observação livre.\n"
            ."- Mantenha consistência com o nível e os objetivos originais do plano.\n"
            ."- Todos os textos (nomes de treino, exercícios, observações) DEVEM estar em Português do Brasil.\n"
            ."- Não inclua exercícios em inglês.\n\n"
            ."Retorne EXCLUSIVAMENTE um JSON válido sem markdown, com a seguinte estrutura exata:\n"
            ."{\"plan_name\": \"string\", \"plan_goal\": \"string\", \"days_per_week\": number, \"workouts\": [{\"day_of_week\": number, \"workout_name\": \"string\", \"workout_observations\": \"string\", \"exercises\": [{\"exercise_name\": \"string\", \"sets\": number, \"repetitions\": number, \"rest_seconds\": number, \"ai_observations\": \"string\", \"suggested_weight_kg\": number}]}]}\n";
    }

    private function humanCommentType(string $type): string
    {
        return match ($type) {
            'pain' => 'Dor / lesão',
            'broken' => 'Equipamento indisponível',
            'heavy' => 'Carga muito pesada',
            'custom' => 'Comentário livre',
            default => $type,
        };
    }

    private function buildWorkoutPrompt(array $v): string
    {
        $locationRule = $v['location'] === 'home'
            ? 'Home training: consider NO equipment (bodyweight only). Do not include machines, barbells, dumbbells, or cables.'
            : 'Gym training: you may include standard gym equipment and machines when appropriate.';

        $muscles = $v['muscles'] ?? 'balanced / full body';
        $limitations = $v['limitations'] ?? 'none';

        return "You are an experienced Master Personal Trainer and an Expert in Workout Protocol Creation.\n"
            ."Create a complete workout plan for the user based on the following data:\n"
            ."- Primary Goal: {$v['goal']}\n"
            ."- Focus Muscles: {$muscles}\n"
            ."- Experience Level: {$v['level']}\n"
            ."- Training Days per Week: {$v['days_per_week']} days\n"
            ."- Available Time per Workout: {$v['workout_time_minutes']} minutes\n"
            ."- Physical Limitations / Injuries: {$limitations}\n"
            ."- Training Location: {$v['location']} (home or gym)\n\n"
            ."Given the available time is {$v['workout_time_minutes']} minutes, choose the number of exercises, sets, and repetitions wisely to ensure an effective workout within this time limit. "
            ."Consider hypertrophy and appropriate progression for a {$v['level']} level. Adapt exercise selection to the training location.\n\n"
            ."LOCATION CONSTRAINT:\n"
            ."- {$locationRule}\n\n"
            ."IMPORTANT LANGUAGE RULES:\n"
            ."- All exercise names in \"exercise_name\" MUST be in Brazilian Portuguese.\n"
            ."- All workout names and observations should also be written in Brazilian Portuguese.\n"
            ."- Do NOT use English names for exercises.\n\n"
            ."You MUST return the response EXCLUSIVELY in a valid JSON format, without markdown formatting. The structure MUST be exactly this:\n"
            ."{\"plan_name\": \"string\", \"plan_goal\": \"string\", \"days_per_week\": number, \"workouts\": [{\"day_of_week\": number, \"workout_name\": \"string\", \"workout_observations\": \"string\", \"exercises\": [{\"exercise_name\": \"string\", \"sets\": number, \"repetitions\": number, \"rest_seconds\": number, \"ai_observations\": \"string\", \"suggested_weight_kg\": number}]}]}\n\n"
            .'Note for "day_of_week": 0=Sunday, 1=Monday, 2=Tuesday, etc. If the plan is ABC (sequential, no fixed days), you can number them from 1 to N.';
    }

    private function persistWorkoutPlan($user, array $validated, array $aiResponse, string $prompt): AiPlan
    {
        $aiPlan = AiPlan::create([
            'user_id' => $user->id,
            'type' => 'workout',
            'version' => 1,
            'status' => 'draft',
            'content_json' => $aiResponse,
            'generation_reason' => "Goal: {$validated['goal']}, Level: {$validated['level']}, Days: {$validated['days_per_week']}",
            'context_prompt' => $prompt,
            'valid_from' => Carbon::today(),
            'valid_until' => Carbon::today()->addWeeks(8),
        ]);

        foreach ($aiResponse['workouts'] ?? [] as $workoutData) {
            $this->persistWorkoutDay($aiPlan, $workoutData);
        }

        return $aiPlan->load('planWorkouts.exercises');
    }

    private function persistWorkoutDay(AiPlan $aiPlan, array $workoutData): void
    {
        $planWorkout = PlanWorkout::create([
            'ai_plan_id' => $aiPlan->id,
            'day_of_week' => $workoutData['day_of_week'] ?? 0,
            'workout_name' => $workoutData['workout_name'] ?? null,
            'ai_observations' => $workoutData['workout_observations'] ?? null,
        ]);

        foreach ($workoutData['exercises'] ?? [] as $order => $exerciseData) {
            $name = trim((string) ($exerciseData['exercise_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $exercise = $this->resolveCatalogExercise($name);

            PlanWorkoutExercise::create([
                'plan_workout_id' => $planWorkout->id,
                'exercise_id' => $exercise->id,
                'order' => $order + 1,
                'rec_sets' => $exerciseData['sets'] ?? null,
                'rec_reps' => $exerciseData['repetitions'] ?? null,
                'rec_weight_kg' => $exerciseData['suggested_weight_kg'] ?? null,
                'rest_sec' => $exerciseData['rest_seconds'] ?? null,
                'ai_notes' => $exerciseData['ai_observations'] ?? null,
            ]);
        }
    }

    private function resolveCatalogExercise(string $name): Exercise
    {
        $exercise = Exercise::where('name', 'LIKE', '%'.$name.'%')->first();

        return $exercise ?? Exercise::create([
            'id' => (string) Str::uuid(),
            'name' => Str::limit($name, 150, ''),
            'category' => 'strength',
            'difficulty' => 'beginner',
            'is_active' => true,
        ]);
    }

    private function failureResponse(\Throwable $e, string $message)
    {
        $status = (int) $e->getCode();
        if ($status < 400 || $status > 599) {
            $status = 422;
        }

        return response()->json(['error' => $message.': '.$e->getMessage()], $status);
    }

    #[OA\Get(
        path: '/api/v1/plans',
        summary: 'List all plans for the authenticated user',
        tags: ['AI Plans'],
        responses: [
            new OA\Response(response: 200, description: 'List of plans'),
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user() ?? User::first();

        $query = AiPlan::where('user_id', $user->id);
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        $plans = $query
            ->orderByDesc('created_at')
            ->with(['planWorkouts.exercises', 'planMeals'])
            ->get();

        return response()->json(['plans' => $plans]);
    }

    #[OA\Get(
        path: '/api/v1/plans/{id}',
        summary: 'Get a specific plan by ID',
        tags: ['AI Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plan details'),
            new OA\Response(response: 404, description: 'Plan not found'),
        ]
    )]
    public function show(Request $request, string $id)
    {
        $user = $request->user() ?? User::first();

        $plan = AiPlan::where('user_id', $user->id)
            ->where('id', $id)
            ->with(['planWorkouts.exercises', 'planMeals'])
            ->firstOrFail();

        return response()->json(['plan' => $plan]);
    }

    #[OA\Patch(
        path: '/api/v1/plans/{id}/activate',
        summary: 'Accept and activate a draft plan',
        tags: ['AI Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plan activated'),
            new OA\Response(response: 404, description: 'Plan not found'),
        ]
    )]
    public function activate(Request $request, string $id)
    {
        $user = $request->user() ?? User::first();

        $plan = AiPlan::where('user_id', $user->id)->where('id', $id)->firstOrFail();

        // Archive existing active plan of the same type.
        AiPlan::where('user_id', $user->id)
            ->where('type', $plan->type)
            ->where('status', 'active')
            ->where('id', '!=', $plan->id)
            ->update(['status' => 'replaced']);

        $plan->update(['status' => 'active']);

        return response()->json(['message' => 'Plan activated!', 'plan' => $plan]);
    }

    #[OA\Patch(
        path: '/api/v1/plans/{id}/archive',
        summary: 'Archive a plan',
        tags: ['AI Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plan archived'),
            new OA\Response(response: 404, description: 'Plan not found'),
        ]
    )]
    public function archive(Request $request, string $id)
    {
        $user = $request->user() ?? User::first();

        $plan = AiPlan::where('user_id', $user->id)->where('id', $id)->firstOrFail();
        $plan->update(['status' => 'archived']);

        return response()->json(['message' => 'Plan archived.', 'plan' => $plan]);
    }

    #[OA\Post(
        path: '/api/v1/plans/{id}/duplicate',
        summary: 'Duplicate an existing plan as a new draft',
        tags: ['AI Plans'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Plan duplicated'),
            new OA\Response(response: 404, description: 'Plan not found'),
        ]
    )]
    public function duplicate(Request $request, string $id)
    {
        $user = $request->user() ?? User::first();

        $original = AiPlan::where('user_id', $user->id)
            ->where('id', $id)
            ->with(['planWorkouts.exercises', 'planMeals'])
            ->firstOrFail();

        $newPlan = DB::transaction(function () use ($original, $user) {
            $clone = AiPlan::create([
                'user_id' => $user->id,
                'type' => $original->type,
                'version' => $original->version + 1,
                'status' => 'draft',
                'content_json' => $original->content_json,
                'generation_reason' => 'Duplicated from plan '.$original->id,
                'context_prompt' => $original->context_prompt,
                'valid_from' => Carbon::today(),
                'valid_until' => Carbon::today()->addWeeks(8),
            ]);

            if ($original->type === 'workout') {
                foreach ($original->planWorkouts as $workout) {
                    $newWorkout = PlanWorkout::create([
                        'ai_plan_id' => $clone->id,
                        'day_of_week' => $workout->day_of_week,
                        'workout_name' => $workout->workout_name,
                        'ai_observations' => $workout->ai_observations,
                    ]);

                    foreach ($workout->exercises as $exercise) {
                        PlanWorkoutExercise::create([
                            'plan_workout_id' => $newWorkout->id,
                            'exercise_id' => $exercise->exercise_id,
                            'order' => $exercise->order,
                            'rec_sets' => $exercise->rec_sets,
                            'rec_reps' => $exercise->rec_reps,
                            'rec_weight_kg' => $exercise->rec_weight_kg,
                            'rest_sec' => $exercise->rest_sec,
                            'ai_notes' => $exercise->ai_notes,
                        ]);
                    }
                }
            }

            if ($original->type === 'nutritional') {
                foreach ($original->planMeals as $planMeal) {
                    PlanMeal::create([
                        'ai_plan_id' => $clone->id,
                        'meal_id' => $planMeal->meal_id,
                        'day_of_week' => $planMeal->day_of_week,
                        'meal_type' => $planMeal->meal_type,
                        'suggested_time' => $planMeal->suggested_time,
                        'ai_notes' => $planMeal->ai_notes,
                    ]);
                }
            }

            return $clone->load(['planWorkouts.exercises', 'planMeals']);
        });

        return response()->json([
            'message' => 'Plan duplicated successfully!',
            'plan' => $newPlan,
        ], 201);
    }
}
