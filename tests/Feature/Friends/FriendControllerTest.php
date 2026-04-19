<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_changes_status_and_sets_accepted_at(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();

        $f = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($addressee)->postJson("/api/v1/friends/{$f->id}/accept")->assertOk();

        $this->assertSame('accepted', $f->fresh()->status);
        $this->assertNotNull($f->fresh()->accepted_at);
    }

    public function test_non_addressee_cannot_accept(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();
        $other = User::factory()->create();

        $f = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($other)->postJson("/api/v1/friends/{$f->id}/accept")->assertStatus(403);
        $this->actingAs($requester)->postJson("/api/v1/friends/{$f->id}/accept")->assertStatus(403);
    }

    public function test_reject_deletes_pending_friendship(): void
    {
        $requester = User::factory()->create();
        $addressee = User::factory()->create();

        $f = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($addressee)->postJson("/api/v1/friends/{$f->id}/reject")->assertOk();
        $this->assertDatabaseMissing('friendships', ['id' => $f->id]);
    }

    public function test_block_sets_status(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $f = Friendship::create([
            'requester_id' => $a->id,
            'addressee_id' => $b->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($a)->postJson("/api/v1/friends/{$f->id}/block")->assertOk();
        $this->assertSame('blocked', $f->fresh()->status);
    }

    public function test_index_lists_accepted_friends(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();

        Friendship::create(['requester_id' => $me->id, 'addressee_id' => $friend->id, 'status' => 'accepted', 'accepted_at' => now()]);
        Friendship::create(['requester_id' => $me->id, 'addressee_id' => $stranger->id, 'status' => 'pending']);

        $response = $this->actingAs($me)->getJson('/api/v1/friends')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$friend->id], $ids);
    }
}
