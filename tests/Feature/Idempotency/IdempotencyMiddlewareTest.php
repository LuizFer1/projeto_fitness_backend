<?php

namespace Tests\Feature\Idempotency;

use App\Models\IdempotencyKey;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_key_request_proceeds_normally(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/posts', [
            'content' => 'x', 'visibility' => 'public',
        ])->assertCreated();

        $this->assertSame(1, Post::count());
    }

    public function test_same_key_same_body_returns_cached_response(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'abc-123'])
            ->postJson('/api/v1/posts', ['content' => 'hello', 'visibility' => 'public'])
            ->assertCreated();

        $second = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'abc-123'])
            ->postJson('/api/v1/posts', ['content' => 'hello', 'visibility' => 'public'])
            ->assertCreated();

        $this->assertSame(1, Post::count(), 'Handler should run only once.');
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('true', $second->headers->get('Idempotent-Replay'));
    }

    public function test_same_key_different_body_returns_409_conflict(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'abc-123'])
            ->postJson('/api/v1/posts', ['content' => 'first', 'visibility' => 'public'])
            ->assertCreated();

        $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'abc-123'])
            ->postJson('/api/v1/posts', ['content' => 'different', 'visibility' => 'public'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_CONFLICT');
    }

    public function test_different_users_can_reuse_same_key(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)
            ->withHeaders(['Idempotency-Key' => 'shared-key'])
            ->postJson('/api/v1/posts', ['content' => 'a', 'visibility' => 'public'])
            ->assertCreated();

        $this->actingAs($b)
            ->withHeaders(['Idempotency-Key' => 'shared-key'])
            ->postJson('/api/v1/posts', ['content' => 'b', 'visibility' => 'public'])
            ->assertCreated();

        $this->assertSame(2, Post::count());
    }

    public function test_key_too_long_is_rejected(): void
    {
        $user = User::factory()->create();

        $longKey = str_repeat('a', 101);

        $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => $longKey])
            ->postJson('/api/v1/posts', ['content' => 'x', 'visibility' => 'public'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_TOO_LONG');
    }

    public function test_expired_key_is_replaced(): void
    {
        $user = User::factory()->create();

        IdempotencyKey::create([
            'key' => 'old-key',
            'user_id' => $user->id,
            'method' => 'POST',
            'path' => 'api/v1/posts',
            'request_hash' => 'stale',
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'response_status' => 201,
            'response_body' => ['stale' => true],
            'expires_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'old-key'])
            ->postJson('/api/v1/posts', ['content' => 'fresh', 'visibility' => 'public'])
            ->assertCreated()
            ->assertJsonPath('data.content', 'fresh');
    }

    public function test_prune_command_removes_expired_keys(): void
    {
        $user = User::factory()->create();

        IdempotencyKey::create([
            'key' => 'k1', 'user_id' => $user->id,
            'method' => 'POST', 'path' => 'x', 'request_hash' => 'h',
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'expires_at' => now()->subHour(),
        ]);
        IdempotencyKey::create([
            'key' => 'k2', 'user_id' => $user->id,
            'method' => 'POST', 'path' => 'x', 'request_hash' => 'h',
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('idempotency:prune')->assertExitCode(0);

        $this->assertDatabaseMissing('idempotency_keys', ['key' => 'k1']);
        $this->assertDatabaseHas('idempotency_keys', ['key' => 'k2']);
    }

    public function test_subscription_creation_is_idempotent(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $first = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'sub-create-1'])
            ->postJson('/api/v1/subscriptions', [
                'plan_code' => 'plus', 'billing_period' => 'monthly',
            ])->assertCreated();

        $second = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'sub-create-1'])
            ->postJson('/api/v1/subscriptions', [
                'plan_code' => 'plus', 'billing_period' => 'monthly',
            ])->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, \App\Models\Subscription::where('user_id', $user->id)->count());
    }
}
