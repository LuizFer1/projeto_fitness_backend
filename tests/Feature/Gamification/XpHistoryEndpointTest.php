<?php

namespace Tests\Feature\Gamification;

use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XpHistoryEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_xp_history_returns_paginated_transactions(): void
    {
        $user = User::factory()->create();

        XpTransaction::factory()->count(35)->create([
            'user_uuid' => $user->uuid,
            'reason' => 'workout_done',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/xp-history');

        $response->assertOk();
        $this->assertCount(30, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.last_page'));
    }

    public function test_xp_history_ordered_by_created_at_desc(): void
    {
        $user = User::factory()->create();

        $older = XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'reason' => 'login_daily',
            'created_at' => now()->subDays(2),
        ]);
        $newer = XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'reason' => 'workout_done',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/xp-history');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($newer->id, $data[0]['id']);
        $this->assertEquals($older->id, $data[1]['id']);
    }

    public function test_xp_history_resource_shows_breakdown(): void
    {
        $user = User::factory()->create();

        XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'amount' => 55,
            'reason' => 'workout_done',
            'reference_type' => 'plan_day',
            'meta' => [
                'base_amount' => 40,
                'streak_bonus' => 15,
                'streak_day' => 4,
            ],
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/xp-history');

        $response->assertOk();
        $item = $response->json('data.0');
        $this->assertEquals(55, $item['amount']);
        $this->assertEquals(40, $item['base_amount']);
        $this->assertEquals(15, $item['streak_bonus']);
        $this->assertEquals(4, $item['streak_day']);
        $this->assertEquals('workout_done', $item['reason']);
        $this->assertEquals('Treino concluído', $item['reason_label']);
        $this->assertEquals('plan_day', $item['reference_type']);
        $this->assertArrayHasKey('created_at', $item);
    }

    public function test_xp_history_shows_only_own_transactions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        XpTransaction::factory()->create(['user_uuid' => $user->uuid, 'reason' => 'login_daily']);
        XpTransaction::factory()->create(['user_uuid' => $other->uuid, 'reason' => 'login_daily']);

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/xp-history');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_xp_history_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/gamification/xp-history');

        $response->assertUnauthorized();
    }

    public function test_xp_history_without_meta_uses_defaults(): void
    {
        $user = User::factory()->create();

        XpTransaction::factory()->create([
            'user_uuid' => $user->uuid,
            'amount' => 100,
            'reason' => 'goal_completed',
            'meta' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/xp-history');

        $response->assertOk();
        $item = $response->json('data.0');
        $this->assertEquals(100, $item['base_amount']);
        $this->assertEquals(0, $item['streak_bonus']);
        $this->assertEquals(0, $item['streak_day']);
    }

    public function test_xp_history_reason_labels(): void
    {
        $user = User::factory()->create();

        $reasons = [
            'login_daily' => 'Login diário',
            'workout_done' => 'Treino concluído',
            'goal_checkin' => 'Check-in de meta',
            'goal_completed' => 'Meta concluída',
            'meal_followed' => 'Refeição seguida',
            'achievement_unlocked' => 'Conquista desbloqueada',
            'friend_added' => 'Amigo adicionado',
            'post_liked' => 'Post curtido',
        ];

        foreach ($reasons as $reason => $label) {
            XpTransaction::factory()->create([
                'user_uuid' => $user->uuid,
                'reason' => $reason,
                'reference_id' => $reason, // unique per reason
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/gamification/xp-history');

        $response->assertOk();
        $data = collect($response->json('data'));

        foreach ($reasons as $reason => $label) {
            $item = $data->firstWhere('reason', $reason);
            $this->assertNotNull($item, "Missing transaction for reason: {$reason}");
            $this->assertEquals($label, $item['reason_label'], "Wrong label for reason: {$reason}");
        }
    }
}
