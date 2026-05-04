<?php

namespace Tests\Feature\Onboarding;

use App\Models\User;
use App\Models\UserOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TdeeConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private function userWithOnboarding(array $overrides = []): User
    {
        $user = User::factory()->create();

        UserOnboarding::create(array_merge([
            'user_id'          => $user->id,
            'gender'           => 'male',
            'age'              => 30,
            'height_cm'        => 175.00,
            'weight_kg'        => 80.00,
            'exercise_frequency' => 3,
            'work_style'       => 'sedentary',
            'tdee_formula'     => 'mifflin',
            'activity_factor'  => null,
        ], $overrides));

        return $user;
    }

    public function test_update_formula_recalculates_bmr(): void
    {
        $user = $this->userWithOnboarding();

        $response = $this->actingAs($user)
            ->putJson('/api/v1/onboarding/tdee-config', ['formula' => 'harris'])
            ->assertOk();

        $response->assertJsonStructure(['formula', 'activity_factor', 'bmr', 'tdee']);
        $this->assertEquals('harris', $response->json('formula'));
        $this->assertNotNull($response->json('bmr'));
        $this->assertNotNull($response->json('tdee'));
    }

    public function test_update_activity_factor_overrides_default(): void
    {
        $user = $this->userWithOnboarding();

        $response = $this->actingAs($user)
            ->putJson('/api/v1/onboarding/tdee-config', ['activity_factor' => 1.72])
            ->assertOk();

        $this->assertEquals(1.72, $response->json('activity_factor'));
    }

    public function test_update_both_formula_and_factor(): void
    {
        $user = $this->userWithOnboarding();

        $response = $this->actingAs($user)
            ->putJson('/api/v1/onboarding/tdee-config', [
                'formula'         => 'harris',
                'activity_factor' => 1.55,
            ])
            ->assertOk();

        $this->assertEquals('harris', $response->json('formula'));
        $this->assertEquals(1.55, $response->json('activity_factor'));
    }

    public function test_returns_404_when_no_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/onboarding/tdee-config', ['formula' => 'mifflin'])
            ->assertNotFound();
    }

    public function test_validates_formula_enum(): void
    {
        $user = $this->userWithOnboarding();

        $this->actingAs($user)
            ->putJson('/api/v1/onboarding/tdee-config', ['formula' => 'invalid'])
            ->assertUnprocessable();
    }

    public function test_validates_activity_factor_range(): void
    {
        $user = $this->userWithOnboarding();

        $this->actingAs($user)
            ->putJson('/api/v1/onboarding/tdee-config', ['activity_factor' => 2.5])
            ->assertUnprocessable();

        $this->actingAs($user)
            ->putJson('/api/v1/onboarding/tdee-config', ['activity_factor' => 0.5])
            ->assertUnprocessable();
    }

    public function test_requires_authentication(): void
    {
        $this->putJson('/api/v1/onboarding/tdee-config', ['formula' => 'mifflin'])
            ->assertUnauthorized();
    }
}
