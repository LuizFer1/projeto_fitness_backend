<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logout realizado com sucesso.']);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }
}
