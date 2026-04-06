<?php

namespace Tests\Feature\PublicProfile;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $authUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authUser = User::factory()->create();
    }

    public function test_authenticated_user_can_view_active_user_profile(): void
    {
        $target = User::factory()->create([
            'username'  => 'targetuser',
            'name'      => 'Target',
            'bio'       => 'Hello',
            'xp_points' => 500,
            'level'     => 2,
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/targetuser');

        $response->assertOk();
        $response->assertJsonPath('data.username', 'targetuser');
        $response->assertJsonPath('data.name', 'Target');
        $response->assertJsonPath('data.friendship_status', 'none');
        $response->assertJsonStructure([
            'data' => [
                'username', 'name', 'avatar', 'bio',
                'level', 'level_name', 'xp_points',
                'achievements_count', 'goals_completed_count',
                'friendship_status',
            ],
        ]);
    }

    public function test_inactive_user_returns_404(): void
    {
        User::factory()->create([
            'username'  => 'inactiveuser',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/inactiveuser');

        $response->assertNotFound();
    }

    public function test_nonexistent_user_returns_404(): void
    {
        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/doesnotexist');

        $response->assertNotFound();
    }

    public function test_blocked_by_target_returns_404(): void
    {
        $target = User::factory()->create(['username' => 'blocker']);

        // Target blocked auth user
        Friendship::factory()->blocked()->create([
            'user_uuid'   => $target->uuid,
            'friend_uuid' => $this->authUser->uuid,
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/blocker');

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'NOT_FOUND');
        $response->assertJsonPath('error.message', 'Usuário não encontrado');
    }

    public function test_auth_user_blocked_target_returns_404(): void
    {
        $target = User::factory()->create(['username' => 'blockeduser']);

        // Auth user blocked target
        Friendship::factory()->blocked()->create([
            'user_uuid'   => $this->authUser->uuid,
            'friend_uuid' => $target->uuid,
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/blockeduser');

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_pii_fields_are_never_exposed(): void
    {
        User::factory()->create([
            'username'   => 'piiuser',
            'email'      => 'secret@example.com',
            'cpf'        => '123.456.789-00',
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/piiuser');

        $response->assertOk();
        $response->assertJsonMissing(['email' => 'secret@example.com']);
        $response->assertJsonMissingPath('data.email');
        $response->assertJsonMissingPath('data.birth_date');
        $response->assertJsonMissingPath('data.weight_kg');
        $response->assertJsonMissingPath('data.cpf');
    }

    public function test_soft_deleted_user_returns_404(): void
    {
        $target = User::factory()->create(['username' => 'deleteduser']);
        $target->delete(); // soft delete

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/deleteduser');

        $response->assertNotFound();
    }

    public function test_unauthenticated_returns_401(): void
    {
        User::factory()->create(['username' => 'someuser']);

        $response = $this->getJson('/api/v1/users/someuser');

        $response->assertUnauthorized();
    }

    public function test_friendship_status_shown_for_friends(): void
    {
        $target = User::factory()->create(['username' => 'frienduser']);

        Friendship::factory()->create([
            'user_uuid'   => $this->authUser->uuid,
            'friend_uuid' => $target->uuid,
            'status'      => 'accepted',
        ]);

        $response = $this->actingAs($this->authUser)
            ->getJson('/api/v1/users/frienduser');

        $response->assertOk();
        $response->assertJsonPath('data.friendship_status', 'accepted');
    }
}
