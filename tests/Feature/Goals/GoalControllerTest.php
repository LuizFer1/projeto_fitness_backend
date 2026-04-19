<?php

namespace Tests\Feature\Goals;

use App\Models\User;
use App\Models\UserGoal;
use App\Models\UserOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_null_shape_when_no_goal(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/goals')->assertOk();
        $this->assertNull($response->json('goal_calories_day'));
    }

    public function test_update_exercise_goals_creates_goal(): void
    {
        $user = User::factory()->create();
        UserGoal::create(['user_id' => $user->id, 'is_active' => true, 'main_goal' => 'maintenance']);

        $this->actingAs($user)->putJson('/api/goals/exercise', [
            'goal_steps_day' => 10000,
            'goal_workouts_week' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('user_goals', [
            'user_id' => $user->id,
            'goal_steps_day' => 10000,
            'goal_workouts_week' => 5,
        ]);
    }

    public function test_update_alimentation_auto_calculates_macros_from_onboarding(): void
    {
        $user = User::factory()->create();
        UserGoal::create(['user_id' => $user->id, 'is_active' => true, 'main_goal' => 'maintenance']);

        UserOnboarding::create([
            'user_id' => $user->id,
            'gender' => 'male',
            'age' => 30,
            'height_cm' => 180,
            'weight_kg' => 80,
            'work_style' => 'moderate',
        ]);

        $response = $this->actingAs($user)->putJson('/api/goals/alimentation', [
            'diet_objective' => 'maintenance',
        ])->assertOk();

        $calories = (int) $response->json('goal.goal_calories_day');
        $protein  = (float) $response->json('goal.goal_protein_g');

        $this->assertGreaterThan(1500, $calories);
        $this->assertGreaterThan(50, $protein);
    }
}
