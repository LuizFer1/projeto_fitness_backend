<?php

namespace Tests\Feature\Privacy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrivacyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_returns_expected_shape(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/privacy/my-data');

        $response->assertOk()
            ->assertJsonStructure([
                'profile' => ['id', 'name', 'email'],
                'onboarding',
                'goals',
                'workout_logs',
                'meal_logs',
                'xp_transactions',
                'achievements',
                'gamification',
                'exported_at',
            ]);
    }

    public function test_delete_account_requires_password(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('correct')]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/privacy/delete-account', [
            'password' => 'wrong',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_account_removes_user_with_correct_password(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('correct')]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/privacy/delete-account', [
            'password' => 'correct',
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
