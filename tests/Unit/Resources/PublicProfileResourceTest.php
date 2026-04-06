<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\PublicProfile\PublicProfileResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PublicProfileResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_exposes_only_public_fields(): void
    {
        $user = User::factory()->create([
            'username'  => 'johndoe',
            'name'      => 'John',
            'avatar_url' => 'https://example.com/avatar.jpg',
            'bio'       => 'Hello world',
            'xp_points' => 600,
            'level'     => 2,
        ]);

        $user->achievements_count = 3;
        $user->goals_completed_count = 5;

        $resource = (new PublicProfileResource($user))
            ->withFriendshipStatus('accepted');

        $data = $resource->toArray(Request::create('/'));

        $this->assertSame('johndoe', $data['username']);
        $this->assertSame('John', $data['name']);
        $this->assertSame('https://example.com/avatar.jpg', $data['avatar']);
        $this->assertSame('Hello world', $data['bio']);
        $this->assertSame(2, $data['level']);
        $this->assertSame('Dedicado', $data['level_name']);
        $this->assertSame(600, $data['xp_points']);
        $this->assertSame(3, $data['achievements_count']);
        $this->assertSame(5, $data['goals_completed_count']);
        $this->assertSame('accepted', $data['friendship_status']);
    }

    public function test_resource_does_not_expose_pii_fields(): void
    {
        $user = User::factory()->create([
            'email'     => 'secret@example.com',
            'cpf'       => '123.456.789-00',
        ]);

        $resource = new PublicProfileResource($user);
        $data = $resource->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('email_verified_at', $data);
        $this->assertArrayNotHasKey('birth_date', $data);
        $this->assertArrayNotHasKey('weight_kg', $data);
        $this->assertArrayNotHasKey('height_cm', $data);
        $this->assertArrayNotHasKey('gender', $data);
        $this->assertArrayNotHasKey('health_profile', $data);
        $this->assertArrayNotHasKey('primary_goal', $data);
        $this->assertArrayNotHasKey('fitness_level', $data);
        $this->assertArrayNotHasKey('cpf', $data);
        $this->assertArrayNotHasKey('password_hash', $data);
    }

    public function test_resource_defaults_friendship_status_to_none(): void
    {
        $user = User::factory()->create();

        $resource = new PublicProfileResource($user);
        $data = $resource->toArray(Request::create('/'));

        $this->assertSame('none', $data['friendship_status']);
    }

    public function test_resource_defaults_level_and_xp_for_new_user(): void
    {
        $user = User::factory()->create();

        $resource = new PublicProfileResource($user);
        $data = $resource->toArray(Request::create('/'));

        $this->assertSame(1, $data['level']);
        $this->assertSame('Iniciante', $data['level_name']);
        $this->assertSame(0, $data['xp_points']);
    }

    public function test_resource_includes_all_expected_keys(): void
    {
        $user = User::factory()->create();

        $resource = new PublicProfileResource($user);
        $data = $resource->toArray(Request::create('/'));

        $expectedKeys = [
            'username', 'name', 'avatar', 'bio', 'level', 'level_name',
            'xp_points', 'achievements_count', 'goals_completed_count',
            'friendship_status',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Missing key: {$key}");
        }

        $this->assertCount(count($expectedKeys), $data, 'Resource has unexpected extra keys');
    }
}
