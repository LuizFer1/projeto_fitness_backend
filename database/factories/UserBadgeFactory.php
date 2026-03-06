<?php

namespace Database\Factories;

use App\Models\Badge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserBadgeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_uuid'   => User::factory(),
            'badge_uuid'  => Badge::factory(),
            'earned_at' => fake()->dateTimeBetween('-90 days', 'now'),
            'metadata'  => null,
        ];
    }
}
