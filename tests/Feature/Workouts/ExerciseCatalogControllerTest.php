<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_active_exercises(): void
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['name' => 'Barbell Squat', 'is_active' => true]);
        Exercise::factory()->create(['name' => 'Hidden Move', 'is_active' => false]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/exercises')
            ->assertOk();

        $names = collect($response->json('data.data'))->pluck('name')->all();
        $this->assertContains('Barbell Squat', $names);
        $this->assertNotContains('Hidden Move', $names);
    }

    public function test_index_filters_by_search_term(): void
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['name' => 'Bench Press', 'is_active' => true]);
        Exercise::factory()->create(['name' => 'Deadlift', 'is_active' => true]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/exercises?search=bench')
            ->assertOk();

        $names = collect($response->json('data.data'))->pluck('name')->all();
        $this->assertEquals(['Bench Press'], $names);
    }

    public function test_index_filters_by_muscle_group(): void
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['name' => 'Leg Press', 'muscle_group' => 'legs', 'is_active' => true]);
        Exercise::factory()->create(['name' => 'Pull Up',   'muscle_group' => 'back', 'is_active' => true]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/exercises?muscle_group=legs')
            ->assertOk();

        $names = collect($response->json('data.data'))->pluck('name')->all();
        $this->assertEquals(['Leg Press'], $names);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/exercises')->assertUnauthorized();
    }
}
