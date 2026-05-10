<?php

namespace Tests\Feature\Dashboard;

use App\Models\Exercise;
use App\Models\MealLog;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserGoal;
use App\Models\UserOnboarding;
use App\Models\WaterLog;
use App\Models\WorkoutExerciseLog;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function gamificationFor(User $user): UserGamification
    {
        return UserGamification::create([
            'user_id' => $user->id,
            'xp_total' => 1200,
            'current_level' => 3,
            'current_streak' => 9,
            'max_streak' => 12,
            'total_workouts' => 4,
            'total_water_days' => 0,
            'current_week_xp' => 200,
            'current_month_xp' => 800,
            'xp_to_next' => 1500,
        ]);
    }

    private function goalFor(User $user): UserGoal
    {
        return UserGoal::create([
            'user_id' => $user->id,
            'main_goal' => 'maintenance',
            'goal_calories_day' => 2200,
            'goal_protein_g' => 150,
            'goal_carbs_g' => 250,
            'goal_fat_g' => 70,
            'goal_workouts_week' => 5,
            'goal_water_liters' => 3.0,
            'is_active' => true,
        ]);
    }

    public function test_index_aggregates_today_meals_water_workouts_and_gamification(): void
    {
        $user = User::factory()->create();
        $this->gamificationFor($user);
        $this->goalFor($user);

        UserOnboarding::create([
            'user_id' => $user->id,
            'gender' => 'male',
            'age' => 30,
            'height_cm' => 180,
            'weight_kg' => 80,
            'work_style' => 'moderate',
        ]);

        MealLog::create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'meal_type' => 'lunch',
            'calories_consumed' => 650,
            'protein_g' => 45,
            'carbs_g' => 70,
            'fat_g' => 15,
        ]);

        WaterLog::create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'liters' => 1.5,
        ]);

        WorkoutLog::create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'duration_min' => 60,
            'calories_burned' => 300,
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard')->assertOk();

        $response->assertJsonPath('today', today()->toDateString())
            ->assertJsonPath('dailyCalories.consumed', 650)
            ->assertJsonPath('dailyCalories.goal', 2200)
            ->assertJsonPath('protein.consumed', 45)
            ->assertJsonPath('weeklyWorkouts.done', 1)
            ->assertJsonPath('weeklyWorkouts.goal', 5)
            ->assertJsonPath('hydration.consumed', 1.5)
            ->assertJsonPath('gamification.current_level', 3)
            ->assertJsonPath('gamification.current_streak', 9)
            ->assertJsonPath('currentWeight', '80.00')
            ->assertJsonStructure([
                'meals',
                'trainingDays',
                'macros' => ['protein' => ['consumed', 'goal'], 'carbs', 'fat'],
                'nutritionChart',
                'suggestedWorkouts',
            ]);
    }

    public function test_index_returns_default_water_goal_when_user_has_no_goal(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard')->assertOk();
        $this->assertEquals(2.0, $response->json('hydration.goal'));
        $this->assertNull($response->json('weeklyWorkouts.goal'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_alimentation_groups_today_meals_by_type(): void
    {
        $user = User::factory()->create();
        $this->goalFor($user);

        MealLog::create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'meal_type' => 'breakfast',
            'calories_consumed' => 300,
            'protein_g' => 20,
            'carbs_g' => 40,
            'fat_g' => 10,
        ]);
        MealLog::create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'meal_type' => 'lunch',
            'calories_consumed' => 700,
            'protein_g' => 50,
            'carbs_g' => 80,
            'fat_g' => 20,
        ]);
        MealLog::create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'meal_type' => 'lunch',
            'calories_consumed' => 200,
            'protein_g' => 10,
            'carbs_g' => 20,
            'fat_g' => 5,
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/alimentation')->assertOk();

        $response->assertJsonPath('dailyGoal.calories', 2200)
            ->assertJsonPath('consumed.calories', 1200)
            ->assertJsonPath('consumed.protein_g', 80)
            ->assertJsonPath('macros.protein', 80);

        $groups = collect($response->json('mealGroups'));
        $lunch = $groups->firstWhere('type', 'lunch');

        $this->assertEquals(2, count($lunch['meals']));
        $this->assertEquals(900, $lunch['calories']);
    }

    public function test_alimentation_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/alimentation')->assertUnauthorized();
    }

    public function test_exercise_returns_week_breakdown_and_history(): void
    {
        $user = User::factory()->create();
        $this->goalFor($user);
        $exercise = Exercise::factory()->create();

        $log = WorkoutLog::create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'duration_min' => 45,
            'calories_burned' => 250,
            'muscles_trained' => ['chest', 'triceps'],
        ]);
        WorkoutExerciseLog::create([
            'workout_log_id' => $log->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 10,
            'weight_kg' => 60,
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/exercise')->assertOk();

        $response->assertJsonPath('weeklyGoal', 5)
            ->assertJsonPath('weeklyDone', 1)
            ->assertJsonPath('stats.total_workouts', 1)
            ->assertJsonPath('stats.total_duration', 45)
            ->assertJsonPath('stats.calories_burned', 250)
            ->assertJsonStructure(['weekDays', 'history', 'todayWorkout', 'active_plan']);

        $weekDays = $response->json('weekDays');
        $this->assertCount(7, $weekDays);
        $doneDay = collect($weekDays)->first(fn ($d) => $d['done']);
        $this->assertNotNull($doneDay);
        $this->assertSame(today()->toDateString(), $doneDay['date']);
    }

    public function test_exercise_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/exercise')->assertUnauthorized();
    }
}
