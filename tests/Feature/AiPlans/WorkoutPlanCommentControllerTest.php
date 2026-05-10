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
use Tests\TestCase;

class WorkoutPlanCommentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makePlanForUser(User $user): array
    {
        $plan = AiPlan::create([
            'user_id' => $user->id,
            'type' => 'workout',
            'version' => 1,
            'status' => 'active',
            'content_json' => ['plan_name' => 'Plan A'],
            'valid_from' => Carbon::today(),
            'valid_until' => Carbon::today()->addWeeks(8),
        ]);

        $workout = PlanWorkout::create([
            'ai_plan_id' => $plan->id,
            'day_of_week' => 1,
            'workout_name' => 'Treino A',
            'ai_observations' => null,
        ]);

        $exercise = Exercise::factory()->create();

        $planExercise = PlanWorkoutExercise::create([
            'plan_workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
            'rec_sets' => 4,
            'rec_reps' => 10,
            'rec_weight_kg' => 60,
            'rest_sec' => 90,
            'ai_notes' => null,
        ]);

        return [$plan, $planExercise];
    }

    public function test_create_comment_returns_201(): void
    {
        $user = User::factory()->create();
        [$plan, $planExercise] = $this->makePlanForUser($user);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/plans/{$plan->id}/exercises/{$planExercise->id}/comments",
            ['type' => 'pain', 'text' => 'Senti dor no ombro direito.']
        );

        $response->assertCreated()
            ->assertJsonPath('comment.type', 'pain')
            ->assertJsonPath('comment.text', 'Senti dor no ombro direito.');

        $this->assertDatabaseHas('plan_workout_exercise_comments', [
            'user_id' => $user->id,
            'plan_workout_exercise_id' => $planExercise->id,
            'type' => 'pain',
        ]);
    }

    public function test_create_comment_rejects_unknown_type(): void
    {
        $user = User::factory()->create();
        [$plan, $planExercise] = $this->makePlanForUser($user);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/plans/{$plan->id}/exercises/{$planExercise->id}/comments",
            ['type' => 'BAD']
        );

        $response->assertStatus(422);
    }

    public function test_index_lists_comments_with_exercise_details(): void
    {
        $user = User::factory()->create();
        [$plan, $planExercise] = $this->makePlanForUser($user);

        PlanWorkoutExerciseComment::create([
            'user_id' => $user->id,
            'plan_workout_exercise_id' => $planExercise->id,
            'type' => 'heavy',
            'text' => 'Carga pesada demais',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/plans/{$plan->id}/comments");

        $response->assertOk()
            ->assertJsonPath('comments.0.type', 'heavy')
            ->assertJsonPath('comments.0.exercise.id', $planExercise->exercise_id);
    }

    public function test_destroy_removes_comment(): void
    {
        $user = User::factory()->create();
        [$plan, $planExercise] = $this->makePlanForUser($user);

        $comment = PlanWorkoutExerciseComment::create([
            'user_id' => $user->id,
            'plan_workout_exercise_id' => $planExercise->id,
            'type' => 'broken',
            'text' => 'Equipamento quebrado',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/plans/{$plan->id}/comments/{$comment->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('plan_workout_exercise_comments', ['id' => $comment->id]);
    }

    public function test_foreign_user_cannot_read_comments(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        [$plan] = $this->makePlanForUser($owner);

        $response = $this->actingAs($stranger)->getJson("/api/v1/plans/{$plan->id}/comments");
        $response->assertForbidden();
    }

    public function test_foreign_user_cannot_create_comment(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        [$plan, $planExercise] = $this->makePlanForUser($owner);

        $response = $this->actingAs($stranger)->postJson(
            "/api/v1/plans/{$plan->id}/exercises/{$planExercise->id}/comments",
            ['type' => 'pain', 'text' => 'x']
        );

        $response->assertForbidden();
    }

    public function test_cannot_create_comment_on_exercise_from_a_different_plan(): void
    {
        $user = User::factory()->create();
        [$planA] = $this->makePlanForUser($user);
        [, $exerciseB] = $this->makePlanForUser($user);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/plans/{$planA->id}/exercises/{$exerciseB->id}/comments",
            ['type' => 'pain']
        );

        $response->assertNotFound();
    }
}
