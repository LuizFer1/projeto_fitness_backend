<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-6 months', 'now');
        $billingPeriod = fake()->randomElement(['monthly', 'semiannual', 'annual']);

        $periodMonths = match ($billingPeriod) {
            'monthly'    => 1,
            'semiannual' => 6,
            'annual'     => 12,
        };

        $periodEnd = (clone $startedAt)->modify("+{$periodMonths} months");

        return [
            'user_uuid'            => User::factory(),
            'plan_uuid'            => Plan::factory(),
            'price_uuid'           => null,
            'status'               => fake()->randomElement(['active', 'trialing', 'canceled', 'expired']),
            'billing_period'       => $billingPeriod,
            'started_at'           => $startedAt,
            'current_period_start' => $startedAt,
            'current_period_end'   => $periodEnd,
            'trial_start'          => null,
            'trial_end'            => null,
            'cancel_at'            => null,
            'canceled_at'          => null,
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
            'status'      => 'trialing',
            'trial_start' => $now,
            'trial_end'   => $now->copy()->addDays(30),
        ]);
    }
}
