<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<XpTransaction>
 */
class XpTransactionFactory extends Factory
{
    protected $model = XpTransaction::class;

    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'amount' => fake()->numberBetween(5, 100),
            'reason' => fake()->randomElement(['login_daily', 'workout_done', 'goal_checkin']),
            'reference_type' => null,
            'reference_id' => null,
            'meta' => null,
        ];
    }
}
