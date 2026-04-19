<?php

namespace Tests\Feature\Meals;

use App\Models\User;
use App\Models\UserGamification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MealLogControllerTest extends TestCase
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

    public function test_analyze_text_creates_meal_log_and_grants_xp(): void
    {
        $user = User::factory()->create();
        UserGamification::create(['user_id' => $user->id, 'current_streak' => 0, 'xp_total' => 0, 'current_level' => 1, 'current_week_xp' => 0, 'current_month_xp' => 0, 'total_workouts' => 0, 'total_water_days' => 0, 'max_streak' => 0, 'xp_to_next' => 200]);

        $this->fakeGroqJson([
            'calorias_totais' => 520,
            'macros_totais' => ['proteinas_g' => 35, 'carboidratos_g' => 45, 'gorduras_g' => 20],
            'feedback_breve' => 'Boa refeição!',
            'itens_detalhados' => [['nome' => 'Frango', 'quantidadeDada' => '150g', 'calorias' => 200]],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/meals/analyze-text', [
            'date' => '2026-04-18',
            'meal_type' => 'lunch',
            'text_description' => 'Comi 150g de frango e 200g de arroz',
        ]);

        $response->assertCreated()
            ->assertJsonPath('log.calories_consumed', 520);

        $this->assertDatabaseHas('meal_logs', [
            'user_id' => $user->id,
            'meal_type' => 'lunch',
            'calories_consumed' => 520,
        ]);

        $this->assertDatabaseHas('xp_transactions', ['user_id' => $user->id]);
    }

    public function test_analyze_text_requires_valid_meal_type(): void
    {
        $user = User::factory()->create();
        UserGamification::create(['user_id' => $user->id, 'current_streak' => 0, 'xp_total' => 0, 'current_level' => 1, 'current_week_xp' => 0, 'current_month_xp' => 0, 'total_workouts' => 0, 'total_water_days' => 0, 'max_streak' => 0, 'xp_to_next' => 200]);

        $response = $this->actingAs($user)->postJson('/api/v1/meals/analyze-text', [
            'date' => '2026-04-18',
            'meal_type' => 'invalid',
            'text_description' => 'x',
        ]);

        $response->assertStatus(422);
    }

    public function test_analyze_image_creates_meal_log(): void
    {
        $user = User::factory()->create();
        UserGamification::create(['user_id' => $user->id, 'current_streak' => 0, 'xp_total' => 0, 'current_level' => 1, 'current_week_xp' => 0, 'current_month_xp' => 0, 'total_workouts' => 0, 'total_water_days' => 0, 'max_streak' => 0, 'xp_to_next' => 200]);

        $this->fakeGroqJson([
            'calorias_totais_estimadas' => 600,
            'macros_estimados' => ['proteinas_g' => 40, 'carboidratos_g' => 60, 'gorduras_g' => 25],
            'feedback_breve' => 'ok',
            'itens_identificados' => [],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/meals/analyze-image', [
            'date' => '2026-04-18',
            'meal_type' => 'dinner',
            'image_base64' => base64_encode('fake-image'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('log.calories_consumed', 600);
    }
}
