<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FriendshipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_uuid'   => User::factory(),
            'friend_uuid' => User::factory(),
            'status'      => 'accepted',
        ];
    }

    public function blocked(): static
    {
        return $this->state(['status' => 'blocked']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }
}
