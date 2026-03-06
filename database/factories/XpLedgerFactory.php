<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class XpLedgerFactory extends Factory
{
    public function definition(): array
    {
        $actions = [
            'checkin'          => 10,
            'workout'          => 50,
            'water_goal'       => 20,
            'sleep_goal'       => 20,
            'weight'           => 15,
            'mission_complete' => 80,
            'badge_unlock'     => 100,
        ];

        $action = fake()->randomElement(array_keys($actions));

        return [
            'user_uuid'  => User::factory(),
            'action'     => $action,
            'xp'         => $actions[$action],
            'day'        => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'ref_table'  => null,
            'ref_uuid'   => null,
            'metadata'   => null,
        ];
    }
}
