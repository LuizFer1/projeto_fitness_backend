<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'João',
            'last_name' => 'Silva',
            'email' => 'joao@example.com',
            'cpf' => '123.456.789-00',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ], $overrides);
    }

    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'joao@example.com',
        ]);
    }

    public function test_registration_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'joao@example.com']);

        $response = $this->postJson('/api/register', $this->validPayload());

        $response->assertStatus(422);
    }

    public function test_registration_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]));

        $response->assertStatus(422);
    }
}
