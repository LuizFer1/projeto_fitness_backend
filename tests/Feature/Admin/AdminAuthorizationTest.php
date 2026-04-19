<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->getJson('/api/v1/admin/badges')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/v1/admin/quests')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/v1/admin/exercises')->assertStatus(403);
    }

    public function test_guest_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/v1/admin/badges')->assertStatus(401);
    }

    public function test_admin_can_access_admin_endpoints(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->getJson('/api/v1/admin/badges')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/admin/quests')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/admin/exercises')->assertOk();
    }

    public function test_promote_command_sets_is_admin_flag(): void
    {
        $user = User::factory()->create(['email' => 'promote@example.com', 'is_admin' => false]);

        $this->artisan('admin:promote promote@example.com')->assertExitCode(0);

        $this->assertTrue($user->fresh()->is_admin);

        $this->artisan('admin:promote promote@example.com --demote')->assertExitCode(0);

        $this->assertFalse($user->fresh()->is_admin);
    }
}
