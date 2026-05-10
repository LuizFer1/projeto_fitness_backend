<?php

namespace Tests\Feature\AiPlans;

use App\Models\AiPlan;
use App\Models\Exercise;
use App\Models\PlanWorkout;
use App\Models\PlanWorkoutExercise;
use App\Models\PlanWorkoutExerciseComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPlanRefineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.groq.api_key' => 'test-key']);
    }

    private function fakeGroqJson(array $payload): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => json_encode($payload)],
                ]],
            ], 200),
        ]);
    }

    private function makeActivePlan(User $user): array
    {
        $plan = AiPlan::create([
            'user_id' => $user->id,
            'type' => 'workout',
            'version' => 1,
            'status' => 'active',
            'content_json' => ['plan_name' => 'Plano A', 'plan_goal' => 'hipertrofia', 'days_per_week' => 3, 'workouts' => []],
            'valid_from' => Carbon::today(),
            'valid_until' => Carbon::today()->addWeeks(8),
        ]);

        $workout = PlanWorkout::create([
            'ai_plan_id' => $plan->id,
            'day_of_week' => 1,
            'workout_name' => 'Treino A',
        ]);

        $exercise = Exercise::factory()->create(['name' => 'Supino reto']);

        $planExercise = PlanWorkoutExercise::create([
            'plan_workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
            'rec_sets' => 4,
            'rec_reps' => 10,
            'rec_weight_kg' => 60,
            'rest_sec' => 90,
        ]);

        return [$plan, $planExercise];
    }

    public function test_refine_creates_new_active_plan_and_replaces_old_one(): void
    {
        $user = User::factory()->create();
        [$plan, $planExercise] = $this->makeActivePlan($user);

        PlanWorkoutExerciseComment::create([
            'user_id' => $user->id,
            'plan_workout_exercise_id' => $planExercise->id,
            'type' => 'heavy',
            'text' => 'Tá pesado demais',
        ]);

        $this->fakeGroqJson([
            'plan_name' => 'Plano A v2',
            'plan_goal' => 'hipertrofia',
            'days_per_week' => 3,
            'workouts' => [[
                'day_of_week' => 1,
                'workout_name' => 'Treino A (ajustado)',
                'workout_observations' => 'Reduzimos a carga.',
                'exercises' => [[
                    'exercise_name' => 'Supino reto com halter',
                    'sets' => 4,
                    'repetitions' => 12,
                    'rest_seconds' => 90,
                    'ai_observations' => 'Carga mais leve',
                    'suggested_weight_kg' => 45,
                ]],
            ]],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/plans/{$plan->id}/refine", ['note' => 'Quero aliviar a carga.']);

        $response->assertCreated()
            ->assertJsonPath('plan.version', 2)
            ->assertJsonPath('plan.status', 'active');

        $this->assertDatabaseHas('ai_plans', ['id' => $plan->id, 'status' => 'replaced']);
        $this->assertDatabaseHas('ai_plans', ['user_id' => $user->id, 'version' => 2, 'status' => 'active']);
    }

    public function test_refine_returns_502_when_groq_fails_and_does_not_modify_plan(): void
    {
        $user = User::factory()->create();
        [$plan] = $this->makeActivePlan($user);

        Http::fake([
            '*/chat/completions' => Http::response('boom', 500),
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/plans/{$plan->id}/refine", []);

        $response->assertStatus(502);

        $this->assertDatabaseHas('ai_plans', ['id' => $plan->id, 'status' => 'active']);
        $this->assertDatabaseMissing('ai_plans', ['user_id' => $user->id, 'version' => 2]);
    }

    public function test_refine_returns_404_for_foreign_user(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        [$plan] = $this->makeActivePlan($owner);

        $response = $this->actingAs($stranger)->postJson("/api/v1/plans/{$plan->id}/refine", []);
        $response->assertNotFound();
    }
}
