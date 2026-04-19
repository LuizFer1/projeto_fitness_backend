<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\User;
use App\Models\UserGamification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkoutLogControllerTest extends TestCase
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

    public function test_finish_creates_workout_log_and_exercises(): void
    {
        $user = User::factory()->create();
        UserGamification::create(['user_id' => $user->id, 'current_streak' => 0, 'xp_total' => 0, 'current_level' => 1, 'current_week_xp' => 0, 'current_month_xp' => 0, 'total_workouts' => 0, 'total_water_days' => 0, 'max_streak' => 0, 'xp_to_next' => 200]);
        $exercise = Exercise::factory()->create();

        $this->fakeGroqJson([
            'informacoes_treino' => 'Bom treino!',
            'musculos_treinados' => ['Peito', 'Tríceps'],
            'calorias_gastas_estimadas' => 450,
            'tempo_medio_por_exercicio_minutos' => 4.5,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/workouts/finish', [
            'date' => '2026-04-18',
            'time_start' => '14:00:00',
            'time_end' => '15:30:00',
            'observations' => 'Foi intenso.',
            'exercises' => [
                ['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => 12, 'weight_kg' => 20.5],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('log.calories_burned', 450);

        $this->assertDatabaseHas('workout_logs', ['user_id' => $user->id, 'calories_burned' => 450]);
        $this->assertDatabaseHas('workout_exercise_logs', ['exercise_id' => $exercise->id, 'sets' => 3]);

        $xpCount = \App\Models\XpTransaction::where('user_id', $user->id)->count();
        $this->assertGreaterThanOrEqual(1, $xpCount);
    }

    public function test_finish_validates_required_exercises(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/workouts/finish', [
            'date' => '2026-04-18',
            'time_start' => '14:00:00',
            'time_end' => '15:30:00',
            'exercises' => [],
        ]);

        $response->assertStatus(422);
    }
}
