<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now();

        return [
            'user_id'               => User::factory(),
            'plan_id'               => Plan::factory(),
            'plan_price_id'         => null,
            'status'                => 'active',
            'started_at'            => $startedAt,
            'trial_ends_at'         => null,
            'current_period_end'    => $startedAt->copy()->addMonth(),
            'canceled_at'           => null,
            'cancel_at_period_end'  => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function trialing(): static
    {
        $now = now();
        return $this->state(fn () => [
            'status'         => 'trialing',
            'started_at'     => $now,
            'trial_ends_at'  => $now->copy()->addDays(30),
        ]);
    }
}
