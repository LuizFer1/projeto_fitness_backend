<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserMissionFactory extends Factory
{
    public function definition(): array
    {
        $status   = fake()->randomElement(['active', 'completed', 'expired']);
        $progress = $status === 'completed' ? 100 : fake()->numberBetween(0, 80);

        return [
            'user_uuid'    => User::factory(),
            'mission_uuid'   => Mission::factory(),
            'status'       => $status,
            'progress'     => $progress,
            'started_at'   => fake()->dateTimeBetween('-30 days', '-1 day'),
            'completed_at' => $status === 'completed' ? fake()->dateTimeBetween('-7 days', 'now') : null,
            'window_start' => fake()->dateTimeBetween('-30 days', '-7 days')->format('Y-m-d'),
            'window_end'   => fake()->dateTimeBetween('now', '+7 days')->format('Y-m-d'),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'       => 'completed',
            'progress'     => 100,
            'completed_at' => now(),
        ]);
    }
}
