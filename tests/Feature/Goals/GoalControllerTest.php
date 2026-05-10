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

        $response->assertJsonStructure([
            'id', 'user_id', 'main_goal', 'diet_objective',
            'goal_calories_day', 'goal_steps_day', 'goal_weight_kg',
            'goal_protein_g', 'goal_carbs_g', 'goal_fat_g',
            'goal_workouts_week', 'goal_water_liters',
            'deadline', 'is_active', 'created_at', 'updated_at',
        ]);
        $this->assertNull($response->json('id'));
        $this->assertNull($response->json('goal_calories_day'));
        $this->assertSame($user->id, $response->json('user_id'));
        $this->assertFalse($response->json('is_active'));
    }

    public function test_index_returns_same_shape_when_goal_exists(): void
    {
        $user = User::factory()->create();
        UserGoal::create([
            'user_id' => $user->id,
            'is_active' => true,
            'main_goal' => 'hypertrophy',
            'diet_objective' => 'muscle_gain',
            'goal_calories_day' => 2200,
        ]);

        $response = $this->actingAs($user)->getJson('/api/goals')->assertOk();

        $response->assertJsonStructure([
            'id', 'user_id', 'main_goal', 'diet_objective',
            'goal_calories_day', 'goal_steps_day', 'goal_weight_kg',
            'goal_protein_g', 'goal_carbs_g', 'goal_fat_g',
            'goal_workouts_week', 'goal_water_liters',
            'deadline', 'is_active', 'created_at', 'updated_at',
        ]);
        $this->assertSame('muscle_gain', $response->json('diet_objective'));
        $this->assertSame(2200, $response->json('goal_calories_day'));
        $this->assertTrue($response->json('is_active'));
    }

    public function test_update_alimentation_persists_diet_objective(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/goals/alimentation', [
            'diet_objective' => 'muscle_gain',
            'goal_calories_day' => 2400,
            'goal_protein_g' => 180,
            'goal_carbs_g' => 280,
            'goal_fat_g' => 70,
        ])->assertOk();

        $this->assertSame('muscle_gain', $response->json('goal.diet_objective'));
        $this->assertDatabaseHas('user_goals', [
            'user_id' => $user->id,
            'diet_objective' => 'muscle_gain',
            'goal_calories_day' => 2400,
        ]);
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
        $protein = (float) $response->json('goal.goal_protein_g');

        $this->assertGreaterThan(1500, $calories);
        $this->assertGreaterThan(50, $protein);
    }
}
