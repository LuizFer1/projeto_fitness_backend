<?php

namespace Tests\Feature\Posts;

use App\Models\Friendship;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_post_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/posts', [
            'content' => 'Treino de hoje',
            'visibility' => 'public',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.content', 'Treino de hoje')
            ->assertJsonPath('data.visibility', 'public');
    }

    public function test_feed_includes_own_and_friend_posts(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();

        Friendship::create([
            'requester_id' => $me->id,
            'addressee_id' => $friend->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        Post::create(['user_id' => $me->id, 'type' => 'text', 'content' => 'me', 'visibility' => 'public']);
        Post::create(['user_id' => $friend->id, 'type' => 'text', 'content' => 'friend', 'visibility' => 'friends_only']);
        Post::create(['user_id' => $stranger->id, 'type' => 'text', 'content' => 'stranger', 'visibility' => 'public']);

        $response = $this->actingAs($me)->getJson('/api/v1/feed')->assertOk();

        $contents = collect($response->json('data'))->pluck('content')->all();
        $this->assertContains('me', $contents);
        $this->assertContains('friend', $contents);
        $this->assertNotContains('stranger', $contents);
    }

    public function test_like_toggle_updates_count(): void
    {
        $user = User::factory()->create();
        $post = Post::create(['user_id' => $user->id, 'type' => 'text', 'content' => 'x', 'visibility' => 'public']);

        $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/like")
            ->assertOk()->assertJsonPath('data.liked', true)->assertJsonPath('data.like_count', 1);

        $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/like")
            ->assertOk()->assertJsonPath('data.liked', false)->assertJsonPath('data.like_count', 0);
    }

    public function test_destroy_only_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::create(['user_id' => $user->id, 'type' => 'text', 'content' => 'x', 'visibility' => 'public']);

        $this->actingAs($other)->deleteJson("/api/v1/posts/{$post->id}")->assertStatus(403);
        $this->actingAs($user)->deleteJson("/api/v1/posts/{$post->id}")->assertOk();
    }
}
