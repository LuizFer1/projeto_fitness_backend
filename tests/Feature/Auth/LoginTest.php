<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'joao@example.com',
            'password_hash' => bcrypt('secret1234'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'joao@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'joao@example.com',
            'password_hash' => bcrypt('secret1234'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'joao@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertStatus(422);
    }
}
