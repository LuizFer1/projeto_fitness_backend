<?php

namespace Tests\Feature\Privacy;

use App\Models\User;
use App\Models\UserPrivacySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacySettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_default_settings_when_none_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/privacy-settings')->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('share_weight', $data);
        $this->assertArrayHasKey('share_macros', $data);
        $this->assertArrayHasKey('share_one_rm', $data);
        $this->assertArrayHasKey('share_streak', $data);
        $this->assertArrayHasKey('share_achievements', $data);
        $this->assertArrayHasKey('share_workouts', $data);
        // Defaults: weight and macros are false
        $this->assertFalse($data['share_weight']);
        $this->assertFalse($data['share_macros']);
    }

    public function test_show_returns_existing_settings(): void
    {
        $user = User::factory()->create();

        UserPrivacySetting::create([
            'user_id' => $user->id,
            'share_weight' => true,
            'share_macros' => true,
            'share_one_rm' => false,
            'share_streak' => true,
            'share_achievements' => true,
            'share_workouts' => false,
        ]);

        $data = $this->actingAs($user)->getJson('/api/v1/privacy-settings')
            ->assertOk()
            ->json('data');

        $this->assertTrue($data['share_weight']);
        $this->assertTrue($data['share_macros']);
        $this->assertFalse($data['share_one_rm']);
        $this->assertFalse($data['share_workouts']);
    }

    public function test_update_persists_changes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/v1/privacy-settings', [
                'share_weight' => true,
                'share_macros' => true,
            ])
            ->assertOk();

        $data = $response->json('data');
        $this->assertTrue($data['share_weight']);
        $this->assertTrue($data['share_macros']);

        $this->assertDatabaseHas('user_privacy_settings', [
            'user_id' => $user->id,
            'share_weight' => true,
            'share_macros' => true,
        ]);
    }

    public function test_update_validates_boolean_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/privacy-settings', ['share_weight' => 'not-a-boolean'])
            ->assertUnprocessable();
    }

    public function test_partial_update_does_not_reset_other_fields(): void
    {
        $user = User::factory()->create();

        UserPrivacySetting::create([
            'user_id' => $user->id,
            'share_weight' => true,
            'share_macros' => false,
            'share_one_rm' => true,
            'share_streak' => true,
            'share_achievements' => true,
            'share_workouts' => true,
        ]);

        $data = $this->actingAs($user)
            ->putJson('/api/v1/privacy-settings', ['share_macros' => true])
            ->assertOk()
            ->json('data');

        $this->assertTrue($data['share_weight']);
        $this->assertTrue($data['share_macros']);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/privacy-settings')->assertUnauthorized();
        $this->putJson('/api/v1/privacy-settings', [])->assertUnauthorized();
    }
}
