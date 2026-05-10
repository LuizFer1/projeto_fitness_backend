<?php

namespace Tests\Feature\Nutrition;

use App\Models\DietAdjustment;
use App\Models\NutritionDaily;
use App\Models\User;
use App\Models\UserGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NutritionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_returns_summary_with_pending_compensation(): void
    {
        $user = User::factory()->create();
        UserGoal::create([
            'user_id' => $user->id,
            'main_goal' => 'maintenance',
            'goal_calories_day' => 2000,
            'goal_protein_g' => 140,
            'goal_carbs_g' => 220,
            'goal_fat_g' => 70,
            'is_active' => true,
        ]);

        NutritionDaily::create([
            'user_id' => $user->id,
            'day' => today()->toDateString(),
            'calories_goal' => 2000,
            'protein_goal_g' => 140,
            'carbs_goal_g' => 220,
            'fat_goal_g' => 70,
            'calories_consumed' => 1500,
            'protein_consumed_g' => 90,
            'carbs_consumed_g' => 160,
            'fat_consumed_g' => 50,
            'delta_kcal' => -500,
            'adjustment_ratio' => 0,
            'dilution_active' => false,
        ]);

        DietAdjustment::create([
            'user_id' => $user->id,
            'target_date' => today()->toDateString(),
            'delta_kcal' => 200,
            'delta_protein_g' => 10,
            'delta_carbs_g' => 25,
            'delta_fat_g' => 5,
            'mode' => 'dilution',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/nutrition/today')
            ->assertOk();

        $response->assertJsonPath('goals.calories', 2000)
            ->assertJsonPath('consumed.calories', 1500)
            ->assertJsonPath('compensation_kcal', 200)
            ->assertJsonPath('remaining_calories', 300);
    }

    public function test_today_requires_authentication(): void
    {
        $this->getJson('/api/v1/nutrition/today')->assertUnauthorized();
    }

    public function test_adjustments_returns_today_by_default(): void
    {
        $user = User::factory()->create();

        DietAdjustment::create([
            'user_id' => $user->id,
            'target_date' => today()->toDateString(),
            'delta_kcal' => 150,
            'delta_protein_g' => 5,
            'delta_carbs_g' => 20,
            'delta_fat_g' => 3,
            'mode' => 'same_day',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/diet-adjustments')
            ->assertOk();

        $this->assertEquals(today()->toDateString(), $response->json('date'));
        $this->assertCount(1, $response->json('adjustments'));
    }

    public function test_adjustments_filters_by_date_query_param(): void
    {
        $user = User::factory()->create();

        DietAdjustment::create([
            'user_id' => $user->id,
            'target_date' => '2026-04-01',
            'delta_kcal' => 100,
            'delta_protein_g' => 5,
            'delta_carbs_g' => 10,
            'delta_fat_g' => 2,
            'mode' => 'same_day',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/diet-adjustments?date=2026-04-01')
            ->assertOk();

        $this->assertCount(1, $response->json('adjustments'));
    }
}
