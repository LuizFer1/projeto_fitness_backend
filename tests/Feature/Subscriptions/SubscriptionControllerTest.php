<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_me_returns_free_when_no_active_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/subscriptions/me');

        $response->assertOk()
            ->assertJsonPath('data.plan_code', 'free')
            ->assertJsonPath('data.status', null);
    }

    public function test_store_creates_trialing_subscription_for_plus(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/subscriptions', [
            'plan_code' => 'plus',
            'billing_period' => 'monthly',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.plan_code', 'plus')
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.billing_period', 'monthly');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'trialing',
        ]);
    }

    public function test_store_creates_active_subscription_for_free_without_billing_period(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/subscriptions', [
            'plan_code' => 'free',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.plan_code', 'free')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_store_rejects_duplicate_active_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/subscriptions', [
            'plan_code' => 'plus',
            'billing_period' => 'monthly',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/subscriptions', [
            'plan_code' => 'pro',
            'billing_period' => 'annual',
        ])->assertStatus(409);
    }

    public function test_store_requires_billing_period_for_paid_plans(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/subscriptions', [
            'plan_code' => 'plus',
        ]);

        $response->assertStatus(422);
    }

    public function test_cancel_marks_subscription_to_end_at_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/subscriptions', [
            'plan_code' => 'plus',
            'billing_period' => 'monthly',
        ])->assertCreated();

        $response = $this->actingAs($user)->postJson('/api/v1/subscriptions/cancel');

        $response->assertOk()
            ->assertJsonPath('data.cancel_at_period_end', true);
    }

    public function test_resume_reverts_cancellation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/subscriptions', [
            'plan_code' => 'plus',
            'billing_period' => 'monthly',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/subscriptions/cancel')->assertOk();

        $response = $this->actingAs($user)->postJson('/api/v1/subscriptions/resume');

        $response->assertOk()
            ->assertJsonPath('data.cancel_at_period_end', false)
            ->assertJsonPath('data.canceled_at', null);
    }

    public function test_expire_command_marks_ended_subscriptions(): void
    {
        $user = User::factory()->create();

        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => \App\Models\Plan::where('code', 'plus')->first()->id,
            'status' => 'active',
            'current_period_end' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->assertSame('expired', $sub->fresh()->status);
    }
}
