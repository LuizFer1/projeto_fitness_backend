<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AiPlan;
use App\Models\Exercise;
use App\Models\PlanMeal;
use App\Models\PlanWorkout;
use App\Models\PlanWorkoutExercise;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AiPlanController extends Controller
{
    private $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    #[OA\Post(
        path: '/api/v1/plans/generate-workout',
        summary: 'Generate a personalized AI workout plan',
        description: 'Sends user preferences to Gemini AI and receives a structured workout plan that is saved to the database.',
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

        $user = $request->user() ?? \App\Models\User::first();
        $locationRule = $validated['location'] === 'home'
            ? 'Home training: consider NO equipment (bodyweight only). Do not include machines, barbells, dumbbells, or cables.'
            : 'Gym training: you may include standard gym equipment and machines when appropriate.';

        $prompt = "You are an experienced Master Personal Trainer and an Expert in Workout Protocol Creation.\n"
            . "Create a complete workout plan for the user based on the following data:\n"
            . "- Primary Goal: {$validated['goal']}\n"
            . "- Focus Muscles: " . ($validated['muscles'] ?? 'balanced / full body') . "\n"
            . "- Experience Level: {$validated['level']}\n"
            . "- Training Days per Week: {$validated['days_per_week']} days\n"
            . "- Available Time per Workout: {$validated['workout_time_minutes']} minutes\n"
            . "- Physical Limitations / Injuries: " . ($validated['limitations'] ?? 'none') . "\n"
            . "- Training Location: {$validated['location']} (home or gym)\n\n"
            . "Given the available time is {$validated['workout_time_minutes']} minutes, choose the number of exercises, sets, and repetitions wisely to ensure an effective workout within this time limit. "
            . "Consider hypertrophy and appropriate progression for a {$validated['level']} level. Adapt exercise selection to the training location.\n\n"
            . "LOCATION CONSTRAINT:\n"
            . "- {$locationRule}\n\n"
            . "IMPORTANT LANGUAGE RULES:\n"
            . "- All exercise names in \"exercise_name\" MUST be in Brazilian Portuguese.\n"
            . "- All workout names and observations should also be written in Brazilian Portuguese.\n"
            . "- Do NOT use English names for exercises.\n\n"
            . "You MUST return the response EXCLUSIVELY in a valid JSON format, without markdown formatting. The structure MUST be exactly this:\n"
            . "{\"plan_name\": \"string\", \"plan_goal\": \"string\", \"days_per_week\": number, \"workouts\": [{\"day_of_week\": number, \"workout_name\": \"string\", \"workout_observations\": \"string\", \"exercises\": [{\"exercise_name\": \"string\", \"sets\": number, \"repetitions\": number, \"rest_seconds\": number, \"ai_observations\": \"string\", \"suggested_weight_kg\": number}]}]}\n\n"
            . "Note for \"day_of_week\": 0=Sunday, 1=Monday, 2=Tuesday, etc. If the plan is ABC (sequential, no fixed days), you can number them from 1 to N.";

        try {
            $aiResponse = $this->geminiService->generateTextResponse(null, $prompt);

            // Save the plan inside a transaction
            $plan = DB::transaction(function () use ($user, $validated, $aiResponse, $prompt) {

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

                // Create plan_workouts and plan_workout_exercises
                foreach ($aiResponse['workouts'] ?? [] as $workoutData) {
                    $planWorkout = PlanWorkout::create([
                        'ai_plan_id' => $aiPlan->id,
                        'day_of_week' => $workoutData['day_of_week'] ?? 0,
                        'workout_name' => $workoutData['workout_name'] ?? null,
                        'ai_observations' => $workoutData['workout_observations'] ?? null,
                    ]);

                    foreach ($workoutData['exercises'] ?? [] as $order => $exerciseData) {
                        $exerciseName = trim((string) ($exerciseData['exercise_name'] ?? ''));
                        if ($exerciseName === '') {
                            continue;
                        }

                        // Try to match a catalog exercise; create one if it's new.
                        $exercise = Exercise::where('name', 'LIKE', '%' . $exerciseName . '%')->first();
                        if (!$exercise) {
                            $exercise = Exercise::create([
                                'id' => (string) Str::uuid(),
                                'name' => Str::limit($exerciseName, 150, ''),
                                'category' => 'strength',
                                'difficulty' => 'beginner',
                                'is_active' => true,
                            ]);
                        }

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

                return $aiPlan->load('planWorkouts.exercises');
            });

            return response()->json([
                'message' => 'Workout plan generated successfully!',
                'plan' => $plan,
            ], 201);

        } catch (\Throwable $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status > 599) {
                $status = 422;
            }

            return response()->json(['error' => 'Failed to generate workout plan: ' . $e->getMessage()], $status);
        }
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
        $user = $request->user() ?? \App\Models\User::first();

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
        $user = $request->user() ?? \App\Models\User::first();

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
        $user = $request->user() ?? \App\Models\User::first();

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
        $user = $request->user() ?? \App\Models\User::first();

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
        $user = $request->user() ?? \App\Models\User::first();

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
                'generation_reason' => 'Duplicated from plan ' . $original->id,
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
