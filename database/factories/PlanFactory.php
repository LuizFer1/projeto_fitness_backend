<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'       => fake()->unique()->randomElement(['free', 'plus', 'pro']),
            'name'       => fake()->word(),
            'is_active'  => true,
            'trial_days' => fake()->randomElement([0, 30]),
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'code' => 'free', 'name' => 'Free', 'trial_days' => 0,
        ]);
    }

    public function plus(): static
    {
        return $this->state(fn () => [
            'code' => 'plus', 'name' => 'Plus', 'trial_days' => 30,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'code' => 'pro', 'name' => 'Pro', 'trial_days' => 30,
        ]);
    }
}
