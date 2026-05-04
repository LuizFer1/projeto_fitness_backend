<?php

namespace Tests\Feature\Nutrition;

use App\Jobs\NutritionExportJob;
use App\Models\NutritionExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NutritionExportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_enqueues_job_and_returns_202(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/nutrition/export?period=7d')
            ->assertStatus(202);

        $this->assertEquals('pending', $response->json('status'));
        Queue::assertPushed(NutritionExportJob::class);
        $this->assertDatabaseHas('nutrition_exports', [
            'user_id' => $user->id,
            'period'  => '7d',
            'status'  => 'pending',
        ]);
    }

    public function test_export_requires_period_param(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/nutrition/export')
            ->assertUnprocessable();
    }

    public function test_export_validates_period_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/nutrition/export?period=14d')
            ->assertUnprocessable();
    }

    public function test_export_returns_existing_ready_export_today(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $export = NutritionExport::create([
            'user_id'      => $user->id,
            'period'       => '30d',
            'format'       => 'csv',
            'status'       => 'ready',
            'date_from'    => today()->subDays(30)->toDateString(),
            'date_to'      => today()->toDateString(),
            'download_url' => 'https://example.com/file.csv',
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/nutrition/export?period=30d&format=csv')
            ->assertOk();

        $this->assertEquals('ready', $response->json('status'));
        Queue::assertNothingPushed();
    }

    public function test_status_returns_export_state(): void
    {
        $user = User::factory()->create();

        $export = NutritionExport::create([
            'user_id'   => $user->id,
            'period'    => '7d',
            'format'    => 'csv',
            'status'    => 'pending',
            'date_from' => today()->subDays(7)->toDateString(),
            'date_to'   => today()->toDateString(),
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/nutrition/export/status/{$export->id}")
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('period', '7d');
    }

    public function test_status_404_for_other_users_export(): void
    {
        [$owner, $other] = User::factory()->count(2)->create()->all();

        $export = NutritionExport::create([
            'user_id'   => $owner->id,
            'period'    => '7d',
            'format'    => 'csv',
            'status'    => 'pending',
            'date_from' => today()->subDays(7)->toDateString(),
            'date_to'   => today()->toDateString(),
        ]);

        $this->actingAs($other)
            ->getJson("/api/v1/nutrition/export/status/{$export->id}")
            ->assertNotFound();
    }

    public function test_export_requires_authentication(): void
    {
        $this->getJson('/api/v1/nutrition/export?period=7d')->assertUnauthorized();
    }
}
